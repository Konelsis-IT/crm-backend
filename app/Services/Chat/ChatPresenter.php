<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Enums\Chat\MessageKind;
use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Chat\Conversation;
use App\Models\Chat\ConversationMembership;
use App\Models\Chat\Message;
use App\Models\Chat\MessageAttachment;
use App\Models\Document\Document;
use App\Models\Personnel\Personnel;
use App\Services\Platform\SchemaReadiness;
use App\Support\RoleLabels;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sohbet verisini tarayiciya giden JSON bicimine cevirir (salt okuma).
 * Kimlik degerleri yalniz istemcinin kayit secmesi icin tasinir; ekranda
 * gosterilmez.
 */
final class ChatPresenter
{
    public function __construct(
        private readonly ChatPresence $presence,
        private readonly InAppLinkResolver $links,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function person(Personnel $personnel, ?bool $online = null): array
    {
        return [
            'id' => (int) $personnel->getKey(),
            'name' => (string) $personnel->full_name,
            'initials' => $this->initials((string) $personnel->full_name),
            'photo' => $personnel->getFilamentAvatarUrl(),
            'role' => $this->roleNames($personnel),
            'department' => $personnel->orgUnit?->name,
            'job_title' => $personnel->job_title,
            'online' => $online ?? $this->presence->isOnline((int) $personnel->getKey()),
        ];
    }

    /**
     * Sohbet listesi ogesi; kisi sohbeti temizlemis ve sonrasinda mesaj
     * gelmemisse null (listede gorunmez).
     *
     * @param  list<int>  $typingIds
     * @return array<string, mixed>|null
     */
    public function conversation(Conversation $conversation, ConversationMembership $mine, Personnel $me, int $unread, array $typingIds = []): ?array
    {
        $hiddenFrom = $mine->history_visible_from;

        if ($hiddenFrom !== null && ($conversation->last_message_at === null || $conversation->last_message_at->lte($hiddenFrom))) {
            return null;
        }

        $others = $conversation->memberships
            ->filter(fn (ConversationMembership $membership): bool => (int) $membership->personnel_id !== (int) $me->getKey())
            ->map(fn (ConversationMembership $membership) => $membership->personnel)
            ->filter()
            ->values();

        $other = $conversation->isDirect() ? $others->first() : null;
        $latest = $conversation->latestMessage;

        if ($latest !== null && $hiddenFrom !== null && $latest->sent_at !== null && $latest->sent_at->lte($hiddenFrom)) {
            $latest = null;
        }

        $typingNames = $others
            ->filter(fn (Personnel $personnel): bool => in_array((int) $personnel->getKey(), $typingIds, true))
            ->map(fn (Personnel $personnel): string => (string) $personnel->full_name)
            ->values()
            ->all();

        return [
            'id' => (int) $conversation->getKey(),
            'type' => $conversation->conversation_type->value,
            'title' => $conversation->isDirect() ? ($other?->full_name ?? __('chat.values.unknown_person')) : (string) $conversation->title,
            'subtitle' => $conversation->isDirect()
                ? $this->personSubtitle($other)
                : __('chat.values.members_count', ['count' => $others->count() + 1]),
            'photo' => $other?->getFilamentAvatarUrl(),
            'initials' => $this->initials($conversation->isDirect() ? (string) ($other?->full_name ?? '?') : (string) $conversation->title),
            'other' => $other !== null ? $this->person($other) : null,
            'members' => $others->map(fn (Personnel $personnel): array => $this->person($personnel))->all(),
            'online' => $other !== null ? $this->presence->isOnline((int) $other->getKey()) : null,
            'typing' => $typingNames,
            'pinned' => $mine->isPinned(),
            'pinned_at' => $mine->pinned_at?->toIso8601String(),
            'unread' => $unread,
            'last_sequence' => (int) $conversation->last_message_sequence,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'last_message' => $latest !== null ? [
                'preview' => $this->preview($latest),
                'mine' => (int) $latest->author_personnel_id === (int) $me->getKey(),
                'at' => $latest->sent_at?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function message(Message $message, Personnel $viewer): array
    {
        $author = $message->author;
        $link = null;

        if (filled($message->link_url) && $message->message_kind !== MessageKind::DocumentShare) {
            $resolved = $this->links->resolve((string) $message->link_url, $viewer);
            $link = [
                'url' => (string) $message->link_url,
                'in_app' => $resolved['in_app'],
                'accessible' => $resolved['accessible'],
                'label' => $resolved['label'] ?? $this->linkLabel((string) $message->link_url),
            ];
        }

        return [
            'id' => (int) $message->getKey(),
            'sequence' => (int) $message->conversation_sequence,
            'kind' => $message->message_kind->value,
            'body' => $message->body,
            'sent_at' => $message->sent_at?->toIso8601String(),
            'mine' => (int) $message->author_personnel_id === (int) $viewer->getKey(),
            'author' => $author !== null ? [
                'id' => (int) $author->getKey(),
                'name' => (string) $author->full_name,
                'initials' => $this->initials((string) $author->full_name),
                'photo' => $author->getFilamentAvatarUrl(),
            ] : null,
            'link' => $link,
            'attachments' => $message->attachments
                ->map(fn (MessageAttachment $attachment): array => $this->attachment($attachment))
                ->values()
                ->all(),
        ];
    }

    public function preview(Message $message): string
    {
        return match ($message->message_kind) {
            MessageKind::FileShare => '📎 '.($message->attachments->first()?->fileObject?->original_name ?? __('chat.values.file')),
            MessageKind::DocumentShare => '📄 '.($message->attachments->first()?->revision?->document?->title ?? __('chat.values.document')),
            MessageKind::LinkShare => filled($message->body) ? Str::limit((string) $message->body, 60) : '🔗 '.$this->linkLabel((string) $message->link_url),
            default => Str::limit((string) $message->body, 60),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function attachment(MessageAttachment $attachment): array
    {
        if ($attachment->isDocument()) {
            $document = $attachment->revision?->document;
            $accessible = $document instanceof Document && Gate::allows('view', $document);

            return [
                'kind' => 'document',
                'id' => (int) $attachment->getKey(),
                'title' => $document?->title ?? __('chat.values.document'),
                'document_no' => $document?->document_no,
                'url' => $document !== null ? $this->safeUrl(fn () => DocumentResource::getUrl('view', ['record' => $document])) : null,
                'accessible' => $accessible,
            ];
        }

        $file = $attachment->fileObject;
        $url = $this->safeUrl(fn () => route('filament.admin.chat.attachment', ['attachment' => $attachment]));

        return [
            'kind' => 'file',
            'id' => (int) $attachment->getKey(),
            'name' => $file?->original_name ?? __('chat.values.file'),
            'size' => $file?->humanSize(),
            'mime' => $file?->mime_type,
            'is_image' => (bool) $file?->isImage(),
            'url' => $url,
            'download_url' => $url !== null ? $url.'?disposition=download' : null,
            'thumbnail_url' => $url !== null && (bool) $file?->isImage() ? $url.'?variant=thumbnail' : null,
        ];
    }

    private function roleNames(Personnel $personnel): string
    {
        if (! SchemaReadiness::hasBatch('B05')) {
            return '';
        }

        return RoleLabels::list($personnel->getRoleNames());
    }

    private function personSubtitle(?Personnel $personnel): string
    {
        if ($personnel === null) {
            return '';
        }

        $parts = array_filter([
            $this->roleNames($personnel),
            $personnel->orgUnit?->name,
        ]);

        return implode(' · ', $parts);
    }

    private function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $letters = array_map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)), array_slice(array_filter($words), 0, 2));

        return implode('', $letters) ?: '?';
    }

    private function linkLabel(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);

        return trim(($host ?? '').($path ?? ''), '/') ?: $url;
    }

    private function safeUrl(callable $resolver): ?string
    {
        try {
            return (string) $resolver();
        } catch (Throwable) {
            return null;
        }
    }
}
