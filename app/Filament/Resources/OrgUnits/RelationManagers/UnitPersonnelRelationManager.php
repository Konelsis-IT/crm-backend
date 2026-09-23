<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrgUnits\RelationManagers;

use App\Filament\Resources\Personnel\PersonnelResource;
use App\Models\Personnel\Personnel;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Organizasyon birimi > Personel (D-116, 23 Eylul 2026 kullanici istegi):
 * birime bagli personel. Salt okunurdur; kisiyi duzenlemek icin satira
 * tiklanip personel kartina gidilir.
 */
class UnitPersonnelRelationManager extends RelationManager
{
    protected static string $relationship = 'personnel';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedUsers;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('org_unit.relation.personnel');
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
            ->heading(__('org_unit.relation.personnel'))
            ->recordTitleAttribute('full_name')
            ->recordUrl(fn (Personnel $record): ?string => Gate::allows('view', $record)
                ? PersonnelResource::getUrl('view', ['record' => $record])
                : null)
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('personnel.fields.full_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('job_title')
                    ->label(__('personnel.fields.job_title'))
                    ->placeholder('–'),
                TextColumn::make('personnel_no')
                    ->label(__('personnel.fields.personnel_no'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('–')
                    ->toggleable(),
                TextColumn::make('email')
                    ->label(__('personnel.fields.email'))
                    ->placeholder('–')
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label(__('personnel.fields.phone'))
                    ->placeholder('–')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('personnel.fields.status'))
                    ->badge(),
            ])
            ->defaultSort('full_name')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading(__('org_unit.relation.personnel_empty'))
            ->emptyStateIcon(Heroicon::OutlinedUsers);
    }
}
