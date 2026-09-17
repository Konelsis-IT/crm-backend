<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Parties\PartyResource;
use App\Models\Project\Project;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Projenin musterisine (yatirimci / is veren) ait lisanslar (D-94, 16 Eylul
 * 2026 kullanici istegi: "lisans iliskisi o tarafa ait bir proje
 * olusturuldugunda proje icerisinde de gorulebilmelidir").
 *
 * Salt okunur: kayitlar taraf kartinda yonetilir, burada yalnizca gorunur ve
 * "Yatirimci kartini ac" eylemiyle oraya gidilir. Musterisi olmayan projede
 * liste bos kalir.
 */
class CustomerLicensesRelationManager extends RelationManager
{
    protected static string $relationship = 'customerLicenses';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedIdentification;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('party_license.relation.project_title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Project && $ownerRecord->customer_party_id !== null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('party_license.label'))
            ->heading(__('party_license.relation.project_title'))
            ->description(fn (): ?string => $this->getOwnerRecord()->customerParty?->display_name)
            ->recordTitleAttribute('license_no')
            ->columns([
                TextColumn::make('license_type')
                    ->label(__('party_license.fields.license_type')),
                TextColumn::make('license_no')
                    ->label(__('party_license.fields.license_no'))
                    ->searchable(),
                TextColumn::make('issuer')
                    ->label(__('party_license.fields.issuer'))
                    ->placeholder('-'),
                TextColumn::make('valid_until')
                    ->label(__('party_license.fields.valid_until'))
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('party_license.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                Action::make('open_party')
                    ->label(__('party_license.actions.open_party'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (): ?string => $this->partyUrl())
                    ->visible(fn (): bool => $this->partyUrl() !== null),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('valid_until')
            ->emptyStateHeading(__('party_license.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedIdentification);
    }

    private function partyUrl(): ?string
    {
        $party = $this->getOwnerRecord()->customerParty;

        if ($party === null) {
            return null;
        }

        try {
            return PartyResource::getUrl('view', ['record' => $party]);
        } catch (Throwable) {
            return null;
        }
    }
}
