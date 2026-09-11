<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Enums\Chat\ConversationStatus;
use App\Enums\Chat\MessageKind;
use App\Enums\Chat\MessageStatus;
use App\Exceptions\ActorRequiredException;
use App\Exceptions\Chat\ConversationLockedException;
use App\Exceptions\Chat\DocumentNotShareableException;
use App\Exceptions\Chat\EmptyMessageException;
use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Chat\Conversation;
use App\Models\Chat\ConversationReadCursor;
use App\Models\Chat\Message;
use App\Models\Chat\MessageAttachment;
use App\Models\Chat\MessageHide;
use App\Models\Document\Document;
use App\Query\Chat\ChatQueries;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Document\FileObjectService;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Mesaj gonderme ve tek tarafli silme (D-83).
 *
 * Bilincli sadelestirme: duz metin mesajlari Personel Hareketleri'ne
 * yazilmaz (yuksek hacim); dosya ve belge paylasimlari yazilir.
 */
final class MessageService extends AbstractService
{
    public const MAX_BODY = 4000;

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly ChatQueries $queries,
        private readonly ConversationService $conversations,
        private readonly FileObjectService $files,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data  body?, link_url?, document_id?, file_temp_path?, file_original_name?
     */
    public function send(Conversation $conversation, array $data): Message
    {
        $me = $this->actor->personnelId();

        if ($me === null) {
            throw ActorRequiredException::make();
        }

        $body = trim((string) ($data['body'] ?? ''));
        $link = trim((string) ($data['link_url'] ?? ''));
        $documentId = filled($data['document_id'] ?? null) ? (int) $data['document_id'] : null;
        $tempPath = filled($data['file_temp_path'] ?? null) ? (string) $data['file_temp_path'] : null;
        $originalName = filled($data['file_original_name'] ?? null) ? (string) $data['file_original_name'] : null;

        if ($body === '' && $link === '' && $documentId === null && $tempPath === null) {
            throw EmptyMessageException::make();
        }

        return $this->transactions->run(function () use ($conversation, $me, $body, $link, $documentId, $tempPath, $originalName): Message {
            /** @var Conversation $locked */
            $locked = Conversation::query()->lockForUpdate()->whereKey($conversation->getKey())->firstOrFail();

            if ($locked->status !== ConversationStatus::Active) {
                throw ConversationLockedException::make();
            }

            $this->conversations->membershipOf($locked, $me);

            $kind = MessageKind::Text;
            $document = null;
            $revision = null;
            $file = null;

            if ($documentId !== null) {
                $document = $this->queries->findDocument($documentId);

                if (! $document instanceof Document || ! Gate::allows('view', $document)) {
                    throw DocumentNotShareableException::make();
                }

                $revision = $document->displayRevision() ?? $document->currentRevision;

                if ($revision === null) {
                    throw DocumentNotShareableException::make();
                }

                $link = DocumentResource::getUrl('view', ['record' => $document]);
                $kind = MessageKind::DocumentShare;
            } elseif ($tempPath !== null) {
                $file = $this->files->createFromUpload($tempPath, $originalName);
                $kind = MessageKind::FileShare;
            } elseif ($link !== '') {
                $kind = MessageKind::LinkShare;
            }

            $sequence = (int) $locked->last_message_sequence + 1;
            $now = Carbon::now('UTC');

            $message = new Message([
                'conversation_id' => $locked->getKey(),
                'conversation_sequence' => $sequence,
                'author_personnel_id' => $me,
                'message_kind' => $kind->value,
                'body' => $body !== '' ? mb_substr($body, 0, self::MAX_BODY) : null,
                'link_url' => $link !== '' ? $link : null,
                'status' => MessageStatus::Sent->value,
                'sent_at' => $now,
            ]);
            $message->save();

            if ($revision !== null || $file !== null) {
                $attachment = new MessageAttachment([
                    'message_id' => $message->getKey(),
                    'file_object_id' => $file?->getKey(),
                    'document_revision_id' => $revision?->getKey(),
                    'sort_order' => 0,
                ]);
                $attachment->save();

                $this->recordActivity($message, 'created', [
                    'tur' => $kind->value,
                    'dosya' => $file?->original_name,
                    'belge' => $document?->title,
                ]);
            }

            $locked->forceFill(['last_message_sequence' => $sequence, 'last_message_at' => $now])->save();
            $this->advanceCursor($locked, $me, $sequence);

            return $message->load(['author', 'attachments.fileObject', 'attachments.revision.document']);
        });
    }

    /** Tek tarafli silme: mesaj yalniz bu kisiye gorunmez olur. */
    public function hideForMe(Message $message): void
    {
        $me = $this->actor->personnelId();

        if ($me === null) {
            throw ActorRequiredException::make();
        }

        $this->transactions->run(function () use ($message, $me): void {
            $conversation = $message->conversation;

            if ($conversation === null) {
                throw EmptyMessageException::make();
            }

            $this->conversations->membershipOf($conversation, $me);

            $exists = MessageHide::query()
                ->where('message_id', $message->getKey())
                ->where('personnel_id', $me)
                ->exists();

            if (! $exists) {
                $hide = new MessageHide([
                    'message_id' => $message->getKey(),
                    'personnel_id' => $me,
                    'hidden_at' => Carbon::now('UTC'),
                ]);
                $hide->save();
            }
        });
    }

    private function advanceCursor(Conversation $conversation, int $personnelId, int $sequence): void
    {
        /** @var ConversationReadCursor|null $cursor */
        $cursor = ConversationReadCursor::query()
            ->where('conversation_id', $conversation->getKey())
            ->where('personnel_id', $personnelId)
            ->first();

        if ($cursor === null) {
            $cursor = new ConversationReadCursor([
                'conversation_id' => $conversation->getKey(),
                'personnel_id' => $personnelId,
                'last_read_sequence' => $sequence,
            ]);
            $cursor->save();

            return;
        }

        if ($sequence > (int) $cursor->last_read_sequence) {
            $cursor->forceFill(['last_read_sequence' => $sequence])->save();
        }
    }
}
