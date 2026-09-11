<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkRequests\Pages;

use App\Enums\WorkRequest\RequestTargetKind;
use App\Exceptions\AbstractException;
use App\Filament\Resources\WorkRequests\WorkRequestResource;
use App\Filament\Support\DomainNotifications;
use App\Models\Chat\ConversationMembership;
use App\Models\Chat\Message;
use App\Query\Chat\ChatQueries;
use App\Services\Platform\SchemaReadiness;
use App\Services\WorkRequest\WorkRequestService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Talep olusturma. Sohbetten gelindiginde (?kaynak_mesaj=ID) baslik, aciklama
 * ve muhatap mesajdan doldurulur; mesaj yalniz sohbetin uyesine acilir.
 */
class CreateWorkRequest extends CreateRecord
{
    protected static string $resource = WorkRequestResource::class;

    public const QUERY_SOURCE_MESSAGE = 'kaynak_mesaj';

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill($this->prefill());

        $this->callHook('afterFill');
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(WorkRequestService::class)->create($data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw ValidationException::withMessages(['title' => $exception->userMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return WorkRequestResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('work_request.messages.created');
    }

    /**
     * @return array<string, mixed>
     */
    private function prefill(): array
    {
        $data = ['target_kind' => RequestTargetKind::Personnel->value];
        $messageId = request()->query(self::QUERY_SOURCE_MESSAGE);

        if (! is_numeric($messageId) || ! SchemaReadiness::hasBatch('B12A')) {
            return $data;
        }

        $message = app(ChatQueries::class)->messageForViewer((int) $messageId, (int) auth()->id());

        if (! $message instanceof Message) {
            return $data;
        }

        $author = $message->author;
        $body = trim((string) $message->body);
        $attachmentName = $message->attachments->first()?->fileObject?->original_name
            ?? $message->attachments->first()?->revision?->document?->title;
        $summary = $body !== '' ? $body : ($attachmentName ?? (string) $message->link_url);

        $data['title'] = Str::limit(Str::squish($summary) !== '' ? Str::squish($summary) : __('work_request.values.from_chat'), 120, '');
        $data['description'] = __('work_request.values.chat_quote', [
            'author' => $author?->full_name ?? '-',
            'date' => $message->sent_at?->timezone(config('app.timezone', 'UTC'))->format('d.m.Y H:i') ?? '-',
        ])."\n".$summary;
        $data['source_message_id'] = (int) $message->getKey();

        $me = (int) auth()->id();
        $counterpart = $author !== null && (int) $author->getKey() !== $me
            ? (int) $author->getKey()
            : $message->conversation?->memberships
                ->map(fn (ConversationMembership $membership): int => (int) $membership->personnel_id)
                ->first(fn (int $id): bool => $id !== $me);

        if ($counterpart !== null) {
            $data['target_personnel_id'] = $counterpart;
        }

        return $data;
    }
}
