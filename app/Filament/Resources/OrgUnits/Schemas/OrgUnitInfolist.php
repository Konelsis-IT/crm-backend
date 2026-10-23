<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrgUnits\Schemas;

use App\Models\Personnel\OrgUnit;
use App\Query\Project\ProjectCatalogQueries;
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
                    // Ad sayfanin alt basliginda (D-205); departman kodu gosterilmez (urun karari 2026-09-05).
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
                    // D-239: taahhutlerde calisma (eski operasyon grubu).
                    TextEntry::make('in_projects')
                        ->label(__('org_unit.fields.in_projects'))
                        ->state(function (OrgUnit $record): string {
                            $sort = app(ProjectCatalogQueries::class)->unitsInProjects()[(string) $record->code] ?? null;

                            return $sort !== null ? __('org_unit.fields.in_projects_value', ['sort' => $sort]) : __('org_unit.fields.in_projects_no');
                        })
                        ->badge()
                        ->color(fn (string $state): string => $state === __('org_unit.fields.in_projects_no') ? 'gray' : 'success')
                        ->visible(fn (): bool => OrgUnitProjectSettings::enabled()),
                ]),
        ]);
    }
}
