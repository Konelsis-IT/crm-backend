<?php

declare(strict_types=1);

namespace App\Filament\Resources\Notifications\Pages;

use App\Filament\Resources\Notifications\NotificationResource;
use App\Query\Notification\NotificationInboxQueries;
use App\Services\Notification\NotificationInboxService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/** Tum bildirimler: Tumu / Okunmamis sekmeleri, "Tumunu okundu isaretle". */
class ListNotifications extends ListRecords
{
    protected static string $resource = NotificationResource::class;

    public function getSubheading(): ?string
    {
        return __('notification_inbox.help');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAllRead')
                ->label(__('notification_inbox.actions.mark_all_read'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('gray')
                ->visible(fn (): bool => app(NotificationInboxQueries::class)->unreadCount(NotificationResource::owner()) > 0)
                ->action(fn () => app(NotificationInboxService::class)->markAllRead(NotificationResource::owner())),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $unread = app(NotificationInboxQueries::class)->unreadCount(NotificationResource::owner());

        return [
            'all' => Tab::make(__('notification_inbox.tabs.all'))
                ->icon(Heroicon::OutlinedBell),
            'unread' => Tab::make(__('notification_inbox.tabs.unread'))
                ->icon(Heroicon::OutlinedEnvelope)
                ->badge($unread > 0 ? (string) $unread : null)
                ->modifyQueryUsing(fn (Builder $query): Builder => app(NotificationInboxQueries::class)->applyUnread($query)),
        ];
    }
}
