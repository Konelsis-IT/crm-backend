<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\RelationManagers;

use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Dogrudan amir gecmisi (line tipi raporlama iliskileri).
 *
 * Personelin kartinin altinda salt okunur bir listedir; ayri bir
 * olusturma/duzenleme formu yoktur, kayitlar PersonnelService/
 * SyncPersonnelManager tarafindan otomatik uretilir.
 */
class ReportingHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'reportingRelationships';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedUserGroup;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('assignment.reporting.title');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('assignment.reporting.title'))
            ->description(__('assignment.reporting.help'))
            ->columns([
                TextColumn::make('manager.full_name')
                    ->label(__('assignment.reporting.manager'))
                    ->placeholder('-'),
                TextColumn::make('valid_from')
                    ->label(__('assignment.fields.effective_from'))
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->label(__('assignment.fields.effective_to'))
                    ->date('d.m.Y')
                    ->placeholder(__('assignment.messages.ongoing')),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('relation_type', 'line'))
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('valid_from', 'desc')
            ->emptyStateHeading(__('assignment.reporting.empty'))
            ->emptyStateIcon(Heroicon::OutlinedUserGroup);
    }
}
