<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Notification\Announcement;
use App\Query\Notification\AnnouncementQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Str;

/**
 * Pano: son duyurular (D-82). Herkes gorur; satira tiklayinca tam metin.
 */
class AnnouncementsWidget extends TableWidget
{
    protected static ?int $sort = 20;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return SchemaReadiness::hasBatch('B11A') && FeatureFlags::enabled('notifications.database');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('announcement.widget.heading'))
            ->description(__('announcement.widget.description'))
            ->query(fn () => app(AnnouncementQueries::class)->latest())
            ->emptyStateHeading(__('announcement.widget.empty'))
            ->emptyStateIcon(Heroicon::OutlinedMegaphone)
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('sent_at')
                    ->label(__('announcement.fields.sent_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('priority')
                    ->label(__('announcement.fields.priority'))
                    ->badge(),
                TextColumn::make('title')
                    ->label(__('announcement.fields.title'))
                    ->weight('semibold')
                    ->description(fn (Announcement $record): string => Str::limit((string) $record->body, 120))
                    ->wrap(),
                TextColumn::make('sender.full_name')
                    ->label(__('announcement.fields.sender'))
                    ->placeholder(__('activity.system')),
                TextColumn::make('audience_label')
                    ->label(__('announcement.fields.audience'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('recipient_count')
                    ->label(__('announcement.fields.recipient_count'))
                    ->numeric()
                    ->alignEnd(),
            ])
            ->recordActions([
                Action::make('read')
                    ->label(__('announcement.actions.read'))
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (Announcement $record): string => (string) $record->title)
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('announcement.actions.close'))
                    ->schema(fn (Schema $schema): Schema => $schema->components([
                        TextEntry::make('sender.full_name')
                            ->label(__('announcement.fields.sender'))
                            ->placeholder(__('activity.system')),
                        TextEntry::make('audience_label')
                            ->label(__('announcement.fields.audience')),
                        TextEntry::make('sent_at')
                            ->label(__('announcement.fields.sent_at'))
                            ->dateTime('d.m.Y H:i'),
                        TextEntry::make('body')
                            ->label(__('announcement.fields.body'))
                            ->columnSpanFull(),
                        TextEntry::make('action_url')
                            ->label(__('announcement.fields.action_url'))
                            ->url(fn (Announcement $record): ?string => $record->action_url)
                            ->visible(fn (Announcement $record): bool => filled($record->action_url))
                            ->columnSpanFull(),
                    ])),
            ]);
    }
}
