<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\Notification\AnnouncementAudience;
use App\Enums\Notification\AnnouncementPriority;
use App\Exceptions\ActorRequiredException;
use App\Exceptions\Notification\NoRecipientsException;
use App\Exceptions\Notification\NotificationScopeNotAllowedException;
use App\Models\Notification\Announcement;
use App\Models\Personnel\Personnel;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Bildirim / duyuru gonderimi (D-82): gonderenin izni olan kitle cozulur,
 * kayit yazilir ve alicilara Filament zili bildirimi dusurulur.
 */
final class AnnouncementService extends AbstractService
{
    protected string $orderBy = 'sent_at';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly AudienceResolver $audiences,
        private readonly PanelNotifier $notifier,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data  audience_kind, audience_id?, audience_ids?, title, body, priority?, action_url?
     */
    public function send(array $data): Announcement
    {
        $senderId = $this->actor->personnelId();
        $sender = $senderId !== null ? Personnel::query()->find($senderId) : null;

        if (! $sender instanceof Personnel) {
            throw ActorRequiredException::make();
        }

        $kind = AnnouncementAudience::from((string) ($data['audience_kind'] ?? ''));

        if (! $this->audiences->permits($sender, $kind)) {
            throw NotificationScopeNotAllowedException::make();
        }

        $audienceId = filled($data['audience_id'] ?? null) ? (int) $data['audience_id'] : null;
        $personnelIds = array_values(array_map('intval', (array) ($data['audience_ids'] ?? [])));
        $resolved = $this->audiences->resolve($kind, $audienceId, $personnelIds, $sender);

        $recipients = $resolved['recipients']->reject(fn (Personnel $personnel): bool => $personnel->is($sender))->values();

        if ($recipients->isEmpty()) {
            throw NoRecipientsException::make();
        }

        $priority = AnnouncementPriority::tryFrom((string) ($data['priority'] ?? '')) ?? AnnouncementPriority::Normal;
        $title = trim((string) $data['title']);
        $body = trim((string) $data['body']);
        $actionUrl = filled($data['action_url'] ?? null) ? trim((string) $data['action_url']) : null;

        return $this->transactions->run(function () use ($sender, $kind, $audienceId, $personnelIds, $resolved, $recipients, $priority, $title, $body, $actionUrl): Announcement {
            /** @var Announcement $announcement */
            $announcement = parent::create([
                'sender_personnel_id' => $sender->getKey(),
                'audience_kind' => $kind->value,
                'audience_id' => $audienceId,
                'audience_ids' => $kind === AnnouncementAudience::Personnel ? $personnelIds : null,
                'audience_label' => $resolved['label'],
                'title' => $title,
                'body' => $body,
                'priority' => $priority->value,
                'action_url' => $actionUrl,
                'recipient_count' => $recipients->count(),
                'sent_at' => Carbon::now('UTC'),
            ]);

            $actions = [];

            if ($actionUrl !== null) {
                $actions[] = Action::make('open')
                    ->label(__('announcement.actions.open'))
                    ->button()
                    ->url($actionUrl)
                    ->markAsRead();
            }

            $this->notifier->send(
                $recipients,
                $title,
                __('announcement.notifications.body', ['sender' => $sender->full_name, 'body' => $body]),
                $priority->getIcon(),
                $priority->getColor(),
                $actions,
            );

            return $announcement;
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        return [
            'baslik' => $record->getAttribute('title'),
            'kitle' => $record->getAttribute('audience_label'),
            'alici_sayisi' => $record->getAttribute('recipient_count'),
        ];
    }
}
