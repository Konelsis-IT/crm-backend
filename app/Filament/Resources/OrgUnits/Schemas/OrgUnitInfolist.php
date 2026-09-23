<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrgUnits\Schemas;

use App\Models\Personnel\OrgUnit;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Organizasyon birimi detayi (D-116, 23 Eylul 2026 kullanici istegi:
 * "organizasyon birimlerine tiklandiginda detayi acilmali"): birimin
 * bilgileri; bagli personel ve gorevler sekmelerde gelir.
 */
final class OrgUnitInfolist
{
    public static function make(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('org_unit.sections.main'))
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->columns(['default' => 1, 'md' => 3])
                ->components([
                    TextEntry::make('name')->label(__('org_unit.fields.name'))->weight('semibold'),
                    TextEntry::make('code')->label(__('org_unit.fields.code'))->badge()->color('gray')->placeholder('–'),
                    TextEntry::make('unit_type')->label(__('org_unit.fields.unit_type'))->badge(),
                    TextEntry::make('parent')
                        ->label(__('org_unit.fields.parent'))
                        ->state(fn (OrgUnit $record): ?string => $record->currentParent()?->name)
                        ->placeholder('–'),
                    TextEntry::make('manager.full_name')->label(__('org_unit.fields.manager'))->icon(Heroicon::OutlinedUser)->placeholder('–'),
                    TextEntry::make('cost_center_code')->label(__('org_unit.fields.cost_center_code'))->placeholder('–'),
                    TextEntry::make('personnel_count')
                        ->label(__('org_unit.fields.personnel_count'))
                        ->state(fn (OrgUnit $record): int => $record->personnel()->count())
                        ->badge(),
                    TextEntry::make('position_count')
                        ->label(__('org_unit.fields.position_count'))
                        ->state(fn (OrgUnit $record): int => $record->positions()->count())
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('status')->label(__('org_unit.fields.status'))->badge(),
                ]),
        ]);
    }
}
