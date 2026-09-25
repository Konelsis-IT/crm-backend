<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkRequests\RelationManagers;

use App\Filament\Support\RowDetail;
use App\Models\Activity\PersonnelActivity;
use App\Support\ActivityLabels;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Talebin hareket gecmisi (Personel Hareketleri) — talep kartinin altinda
 * tablo (12 Eylul 2026 kullanici istegi: "gecmis okunakli degil, tablo
 * bicimli iliski olsun"). Salt okunur; kayitlar servis katmaninca yazilir.
 */
class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedClipboardDocumentList;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('work_request.sections.history');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('work_request.sections.history'))
            ->description(__('work_request.help.history'))
            ->columns([
                TextColumn::make('occurred_at')
                    ->label(__('activity.fields.occurred_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('actor')
                    ->label(__('activity.fields.personnel'))
                    ->state(fn (PersonnelActivity $record): string => $record->actorName())
                    ->icon(Heroicon::OutlinedUserCircle),
                TextColumn::make('action_code')
                    ->label(__('activity.fields.action'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ActivityLabels::action($state)),
                TextColumn::make('changes')
                    ->label(__('activity.fields.summary'))
                    ->state(fn (PersonnelActivity $record): array => ActivityLabels::changeLines($record->changes))
                    ->listWithLineBreaks()
                    ->placeholder(__('activity.messages.no_change'))
                    ->wrap(),
                TextColumn::make('channel')
                    ->label(__('activity.fields.channel'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('personnel'))
            ->headerActions([])
            ->recordActions([
                // Satira tiklamak ayrinti penceresini acar (D-125); dugme gorunmez.
                RowDetail::action(),
            ])
            ->toolbarActions([])
            ->defaultSort('occurred_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading(__('work_request.values.no_history'))
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList);
    }
}
