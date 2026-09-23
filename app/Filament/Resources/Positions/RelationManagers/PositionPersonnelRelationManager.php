<?php

declare(strict_types=1);

namespace App\Filament\Resources\Positions\RelationManagers;

use App\Filament\Resources\Personnel\PersonnelResource;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\PositionAssignment;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Pozisyon > Atanan personel (D-116, 23 Eylul 2026 kullanici istegi):
 * bu kadroya atanmis kisiler, gecerli atamalar ustte. Salt okunurdur;
 * atama personel kartindaki Pozisyonlar sekmesinden yapilir.
 */
class PositionPersonnelRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedUsers;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('position.relation.personnel');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('viewAny', Personnel::class);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('personnel.label'))
            ->pluralModelLabel(__('personnel.plural'))
            ->heading(__('position.relation.personnel'))
            ->recordTitleAttribute('personnel.full_name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('personnel:id,full_name,job_title,org_unit_id,status'))
            ->recordUrl(function (PositionAssignment $record): ?string {
                $person = $record->personnel;

                return $person instanceof Personnel && Gate::allows('view', $person)
                    ? PersonnelResource::getUrl('view', ['record' => $person])
                    : null;
            })
            ->columns([
                TextColumn::make('personnel.full_name')
                    ->label(__('personnel.fields.full_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('personnel.job_title')
                    ->label(__('personnel.fields.job_title'))
                    ->placeholder('–'),
                IconColumn::make('is_primary')
                    ->label(__('position.fields.is_primary'))
                    ->boolean(),
                TextColumn::make('allocation_pct')
                    ->label(__('position.fields.allocation_pct'))
                    ->suffix('%'),
                TextColumn::make('valid_from')
                    ->label(__('position.fields.valid_from'))
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->label(__('position.fields.valid_until'))
                    ->date('d.m.Y')
                    ->placeholder(__('assignment.messages.ongoing')),
                TextColumn::make('personnel.status')
                    ->label(__('personnel.fields.status'))
                    ->badge(),
            ])
            ->defaultSort('valid_until')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading(__('position.relation.personnel_empty'))
            ->emptyStateIcon(Heroicon::OutlinedUsers);
    }
}
