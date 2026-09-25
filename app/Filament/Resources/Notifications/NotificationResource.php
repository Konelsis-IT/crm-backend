<?php

declare(strict_types=1);

namespace App\Filament\Resources\Notifications;

use App\Filament\Resources\Notifications\Pages\ListNotifications;
use App\Models\Notification\PanelNotification;
use App\Models\Personnel\Personnel;
use App\Query\Notification\NotificationInboxQueries;
use App\Services\Notification\NotificationInboxService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tum bildirimler (D-122, 24 Eylul 2026 kullanici istegi): zil panelinde
 * gorunenlerin hepsi, eskiler dahil, tablo halinde. Sol menude yoktur;
 * yalniz bildirim panelindeki dugmeyle acilir. Herkes yalniz kendi
 * bildirimlerini gorur.
 */
class NotificationResource extends Resource
{
    protected static ?string $model = PanelNotification::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBell;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'bildirimler';

    public static function getModelLabel(): string
    {
        return __('notification_inbox.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('notification_inbox.plural');
    }

    public static function canAccess(): bool
    {
        return auth()->user() instanceof Personnel
            && FeatureFlags::enabled('notifications.database')
            && SchemaReadiness::hasBatch('B00')
            && parent::canAccess();
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return app(NotificationInboxQueries::class)
            ->applyRecipient(parent::getEloquentQuery(), $user instanceof Personnel ? $user : null);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('read_at')
                    ->label(__('notification_inbox.fields.status'))
                    ->state(fn (PanelNotification $record): string => $record->read_at === null
                        ? __('notification_inbox.status.unread')
                        : __('notification_inbox.status.read'))
                    ->badge()
                    ->color(fn (PanelNotification $record): string => $record->read_at === null ? 'primary' : 'gray'),
                TextColumn::make('title')
                    ->label(__('notification_inbox.fields.title'))
                    ->state(fn (PanelNotification $record): string => $record->title())
                    ->icon(fn (PanelNotification $record): ?string => $record->iconName())
                    ->iconColor(fn (PanelNotification $record): string => $record->iconColorName())
                    ->weight(fn (PanelNotification $record): string => $record->read_at === null ? 'bold' : 'normal')
                    ->wrap(),
                TextColumn::make('body')
                    ->label(__('notification_inbox.fields.body'))
                    ->state(fn (PanelNotification $record): string => $record->body())
                    ->placeholder('-')
                    ->limit(160)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label(__('notification_inbox.fields.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->description(fn (PanelNotification $record): string => (string) $record->created_at?->diffForHumans())
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('read_at')
                    ->label(__('notification_inbox.filters.read'))
                    ->nullable()
                    ->trueLabel(__('notification_inbox.status.read'))
                    ->falseLabel(__('notification_inbox.status.unread')),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('notification_inbox.actions.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->visible(fn (PanelNotification $record): bool => $record->targetUrl() !== null)
                    ->action(function (PanelNotification $record) {
                        app(NotificationInboxService::class)->markRead(self::owner(), [$record]);

                        return redirect()->to((string) $record->targetUrl());
                    }),
                Action::make('markRead')
                    ->label(__('notification_inbox.actions.mark_read'))
                    ->icon(Heroicon::OutlinedEnvelopeOpen)
                    ->color('gray')
                    ->visible(fn (PanelNotification $record): bool => $record->read_at === null)
                    ->action(fn (PanelNotification $record) => app(NotificationInboxService::class)->markRead(self::owner(), [$record])),
                Action::make('markUnread')
                    ->label(__('notification_inbox.actions.mark_unread'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('gray')
                    ->visible(fn (PanelNotification $record): bool => $record->read_at !== null)
                    ->action(fn (PanelNotification $record) => app(NotificationInboxService::class)->markUnread(self::owner(), [$record])),
            ])
            ->recordAction('open')
            ->recordUrl(null)
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markReadBulk')
                        ->label(__('notification_inbox.actions.mark_read'))
                        ->icon(Heroicon::OutlinedEnvelopeOpen)
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => app(NotificationInboxService::class)->markRead(self::owner(), $records)),
                    BulkAction::make('markUnreadBulk')
                        ->label(__('notification_inbox.actions.mark_unread'))
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => app(NotificationInboxService::class)->markUnread(self::owner(), $records)),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateIcon(Heroicon::OutlinedBellSlash)
            ->emptyStateHeading(__('notification_inbox.empty'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotifications::route('/'),
        ];
    }

    /** Oturumdaki kisi (kaynak yalniz personel icin acilir). */
    public static function owner(): Personnel
    {
        $user = auth()->user();

        abort_unless($user instanceof Personnel, 403);

        return $user;
    }
}
