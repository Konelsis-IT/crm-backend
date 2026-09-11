<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\RelationManagers;

use App\Enums\Activity\ActivityChannel;
use App\Filament\Support\FieldGrid;
use App\Models\Activity\PersonnelActivity;
use App\Query\Activity\ActivityFilterOptions;
use App\Support\ActivityLabels;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Personel Hareketleri: bu personelin ne zaman ne yaptigi.
 *
 * Ayri bir menu degildir; personelin kendi kartinin altinda gorunur.
 * Kayitlar salt okunurdur.
 */
class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedClipboardDocumentList;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('activity.plural');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('activity.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextEntry::make('occurred_at')
                        ->label(__('activity.fields.occurred_at'))
                        ->icon(Heroicon::OutlinedClock)
                        ->dateTime('d.m.Y H:i:s'),
                    TextEntry::make('action_code')
                        ->label(__('activity.fields.action'))
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => ActivityLabels::action($state)),
                    TextEntry::make('subject_type')
                        ->label(__('activity.fields.subject'))
                        ->formatStateUsing(fn (?string $state): string => ActivityLabels::subject($state)),
                    TextEntry::make('channel')
                        ->label(__('activity.fields.channel'))
                        ->badge(),
                    TextEntry::make('ip_address')
                        ->label(__('activity.fields.ip_address'))
                        ->placeholder('-'),
                ])),
            Section::make(__('activity.sections.changes'))
                ->components([
                    TextEntry::make('changes')
                        ->hiddenLabel()
                        ->listWithLineBreaks()
                        ->state(fn (PersonnelActivity $record): array => ActivityLabels::changeLines($record->changes))
                        ->placeholder(__('activity.messages.no_change')),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('activity.plural'))
            ->description(__('activity.help.relation'))
            ->columns([
                TextColumn::make('occurred_at')
                    ->label(__('activity.fields.occurred_at'))
                    ->icon(Heroicon::OutlinedClock)
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('action_code')
                    ->label(__('activity.fields.action'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ActivityLabels::action($state)),
                TextColumn::make('subject_type')
                    ->label(__('activity.fields.subject'))
                    ->formatStateUsing(fn (?string $state): string => ActivityLabels::subject($state))
                    ->sortable(),
                TextColumn::make('changes')
                    ->label(__('activity.fields.summary'))
                    ->state(fn (PersonnelActivity $record): string => implode(' | ', ActivityLabels::changeLines($record->changes)))
                    ->limit(60)
                    ->placeholder('-'),
                TextColumn::make('channel')
                    ->label(__('activity.fields.channel'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('subject_type')
                    ->label(__('activity.fields.subject'))
                    ->options(fn (): array => app(ActivityFilterOptions::class)->subjectTypes()),
                SelectFilter::make('action_code')
                    ->label(__('activity.fields.action'))
                    ->options(fn (): array => app(ActivityFilterOptions::class)->actionCodes()),
                SelectFilter::make('channel')
                    ->label(__('activity.fields.channel'))
                    ->options(ActivityChannel::class),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('occurred_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading(__('activity.messages.empty'))
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList);
    }
}
