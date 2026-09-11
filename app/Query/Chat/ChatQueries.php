<?php

declare(strict_types=1);

namespace App\Query\Chat;

use App\Enums\Chat\ConversationStatus;
use App\Exceptions\RecordNotFoundException;
use App\Models\Chat\Conversation;
use App\Models\Chat\ConversationMembership;
use App\Models\Chat\Message;
use App\Models\Document\Document;
use App\Models\Personnel\Personnel;
use App\Models\Reference\SecurityClassification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Sohbet okuma sorgulari (D-83). Gorunurluk kurallari burada uygulanir:
 * tek tarafli silme (history_visible_from) ve gizlenen mesajlar (message_hides).
 */
final class ChatQueries
{
    /** Yeni sohbetin gizlilik sinifi: `internal`, yoksa en dusuk sira. */
    public function defaultClassificationId(): int
    {
        $id = SecurityClassification::query()->where('code', 'internal')->value('id')
            ?? SecurityClassification::query()->orderBy('rank')->value('id');

        if ($id === null) {
            throw RecordNotFoundException::make();
        }

        return (int) $id;
    }

    public function membership(int $conversationId, int $personnelId): ?ConversationMembership
    {
        return ConversationMembership::query()
            ->where('conversation_id', $conversationId)
            ->where('personnel_id', $personnelId)
            ->whereNull('left_at')
            ->first();
    }

    /**
     * @return list<int>
     */
    public function memberIds(int $conversationId): array
    {
        return ConversationMembership::query()
            ->where('conversation_id', $conversationId)
            ->whereNull('left_at')
            ->pluck('personnel_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Kisinin sohbetleri, son mesaja gore; uyelikler ve son mesaj yuklu.
     *
     * @return Collection<int, Conversation>
     */
    public function conversationsFor(int $personnelId): Collection
    {
        $conversationIds = ConversationMembership::query()
            ->where('personnel_id', $personnelId)
            ->whereNull('left_at')
            ->pluck('conversation_id');

        return Conversation::query()
            ->whereIn('id', $conversationIds)
            ->where('status', '!=', ConversationStatus::Archived->value)
            // Hic mesaj yazilmamis sohbet "baslatilmis" sayilmaz (11 Eylul
            // 2026, kullanici bildirimi): "Sohbet baslat"/"Mesaj gonder"
            // karsi tarafla direct() satirini hemen olusturuyordu, bu da
            // mesaj atilmadan Sohbetler listesinde goruntyor. Yeni olusan
            // sohbeti ac(an) uc yine de calisir (bkz. ChatController::
            // createConversation, bu sorguda bulunamayinca dogrudan yukler).
            ->whereNotNull('last_message_at')
            ->with([
                'memberships.personnel.orgUnit',
                'memberships.personnel.roles',
                'latestMessage.author',
                'latestMessage.attachments.fileObject',
                'latestMessage.attachments.revision.document',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Sohbet basina okunmamis mesaj sayisi (tek sorgu).
     *
     * @param  list<int>  $conversationIds
     * @return array<int, int>
     */
    public function unreadCounts(int $personnelId, array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        $rows = Message::query()
            ->from('messages as m')
            ->join('conversation_memberships as cm', function ($join) use ($personnelId): void {
                $join->on('cm.conversation_id', '=', 'm.conversation_id')
                    ->where('cm.personnel_id', '=', $personnelId)
                    ->whereNull('cm.left_at');
            })
            ->leftJoin('conversation_read_cursors as rc', function ($join) use ($personnelId): void {
                $join->on('rc.conversation_id', '=', 'm.conversation_id')
                    ->where('rc.personnel_id', '=', $personnelId);
            })
            ->whereIn('m.conversation_id', $conversationIds)
            ->where('m.author_personnel_id', '!=', $personnelId)
            ->whereRaw('m.conversation_sequence > COALESCE(rc.last_read_sequence, 0)')
            ->whereRaw('(cm.history_visible_from IS NULL OR m.sent_at > cm.history_visible_from)')
            ->whereNotExists(function ($query) use ($personnelId): void {
                $query->selectRaw('1')
                    ->from('message_hides as h')
                    ->whereColumn('h.message_id', 'm.id')
                    ->where('h.personnel_id', $personnelId);
            })
            ->groupBy('m.conversation_id')
            ->selectRaw('m.conversation_id as conversation_id, COUNT(*) as unread')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row->getAttribute('conversation_id')] = (int) $row->getAttribute('unread');
        }

        return $counts;
    }

    /**
     * Sohbetin bu kisiye gorunen mesajlari. `after` verilirse ondan sonrakiler
     * (artan), aksi halde `before`den onceki son N mesaj (artan sirada doner).
     *
     * @return Collection<int, Message>
     */
    public function messages(Conversation $conversation, ConversationMembership $membership, ?int $after, ?int $before, int $limit = 50): Collection
    {
        $query = Message::query()
            ->where('conversation_id', $conversation->getKey())
            ->with(['author', 'attachments.fileObject', 'attachments.revision.document'])
            ->whereDoesntHave('hides', fn (Builder $hides) => $hides->where('personnel_id', $membership->personnel_id));

        if ($membership->history_visible_from !== null) {
            $query->where('sent_at', '>', $membership->history_visible_from);
        }

        if ($after !== null) {
            return $query
                ->where('conversation_sequence', '>', $after)
                ->orderBy('conversation_sequence')
                ->limit($limit)
                ->get();
        }

        if ($before !== null) {
            $query->where('conversation_sequence', '<', $before);
        }

        return $query
            ->orderByDesc('conversation_sequence')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Personel rehberi: aktif kisiler, ad/gorev aramasi.
     *
     * @return Collection<int, Personnel>
     */
    public function directory(?string $term, int $exceptId, int $limit = 80): Collection
    {
        return Personnel::query()
            ->with(['orgUnit', 'roles'])
            ->reachable()
            ->whereKeyNot($exceptId)
            ->when(filled($term), function (Builder $query) use ($term): void {
                $like = '%'.addcslashes((string) $term, '%_\\').'%';
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('full_name', 'like', $like)
                        ->orWhere('job_title', 'like', $like)
                        ->orWhereHas('orgUnit', fn (Builder $unit) => $unit->where('name', 'like', $like));
                });
            })
            ->orderBy('full_name')
            ->limit($limit)
            ->get();
    }

    /**
     * Paylasilabilir belgeler: goruntuleme yetkisi olanlar (policy ile suzulur).
     *
     * @return Collection<int, Document>
     */
    public function documents(?string $term, int $limit = 20): Collection
    {
        $like = filled($term) ? '%'.addcslashes((string) $term, '%_\\').'%' : null;

        return Document::query()
            ->with('currentRevision')
            ->when($like !== null, function (Builder $query) use ($like): void {
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('title', 'like', $like)->orWhere('document_no', 'like', $like);
                });
            })
            ->orderByDesc('id')
            ->limit($limit * 2)
            ->get()
            ->filter(fn (Document $document): bool => Gate::allows('view', $document))
            ->take($limit)
            ->values();
    }

    public function findDocument(int $documentId): ?Document
    {
        return Document::query()->with('currentRevision')->whereKey($documentId)->first();
    }

    /** Mesaj, yalniz sohbetin uyesine (talep acma icin; D-84). */
    public function messageForViewer(int $messageId, int $viewerId): ?Message
    {
        $message = Message::query()
            ->with(['author', 'conversation.memberships', 'attachments.fileObject', 'attachments.revision.document'])
            ->whereKey($messageId)
            ->first();

        if ($message === null || $this->membership((int) $message->conversation_id, $viewerId) === null) {
            return null;
        }

        return $message;
    }

    public function findPersonnel(int $personnelId): ?Personnel
    {
        return Personnel::query()->with(['orgUnit', 'roles'])->whereKey($personnelId)->first();
    }
}
