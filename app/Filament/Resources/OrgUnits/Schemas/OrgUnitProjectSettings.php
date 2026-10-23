<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrgUnits\Schemas;

use App\Enums\Platform\Feature;
use App\Enums\Shared\ActiveStatus;
use App\Filament\Support\FieldGrid;
use App\Models\Personnel\OrgUnit;
use App\Query\Project\ProjectCatalogQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Project\OperationGroupDefinitionService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Arr;

/**
 * Departmanin taahhutlerde calismasi (D-239, 23 Ekim 2026 kullanici:
 * "Operasyon gruplari ile Organizasyon birimleri bir arada calissin tek
 * arayuz tek ozellik olarak kalsin"). Eski Ayarlar > Operasyon gruplari
 * ekrani kalkti; operasyon grubu artik departmanin bu bolumudur. Acik
 * departman her yeni taahhudun Departmanlar sekmesinde yer alir (sorumlu,
 * ilerleme, tarihler); Projelendirme ekrani da buradan beslenir.
 */
final class OrgUnitProjectSettings
{
    private const FIELDS = ['in_projects', 'project_sort'];

    /** Bolum acik mi: taahhut katalogu (B17) ve ozellik. */
    public static function enabled(): bool
    {
        return SchemaReadiness::hasBatch('B17') && FeatureFlags::enabled(Feature::OperationGroups);
    }

    public static function section(): Section
    {
        return Section::make(__('org_unit.sections.projects'))
            ->description(__('org_unit.help.in_projects'))
            ->columns(FieldGrid::COLUMNS)
            ->visible(fn (): bool => self::enabled())
            ->components(FieldGrid::fields([
                Toggle::make('in_projects')
                    ->label(__('org_unit.fields.in_projects'))
                    ->inline(false)
                    ->live()
                    ->afterStateHydrated(function (Toggle $component, ?OrgUnit $record): void {
                        $group = $record !== null ? app(ProjectCatalogQueries::class)->groupForUnit((string) $record->code) : null;
                        $component->state($group !== null && $group->status === ActiveStatus::Active);
                    }),
                TextInput::make('project_sort')
                    ->label(__('org_unit.fields.project_sort'))
                    ->helperText(__('org_unit.help.project_sort'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(255)
                    ->visible(fn (Get $get): bool => (bool) $get('in_projects'))
                    ->afterStateHydrated(function (TextInput $component, ?OrgUnit $record): void {
                        $group = $record !== null ? app(ProjectCatalogQueries::class)->groupForUnit((string) $record->code) : null;
                        $component->state($group?->default_sort_order ?? (max([0, ...array_values(app(ProjectCatalogQueries::class)->unitsInProjects())]) + 1));
                    }),
            ]));
    }

    /**
     * Departman verisi (bolum alanlari OrgUnitService'e gitmez).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function unitData(array $data): array
    {
        return Arr::except($data, self::FIELDS);
    }

    /** @param  array<string, mixed>  $data */
    public static function save(OrgUnit $unit, array $data): void
    {
        if (! self::enabled() || ! array_key_exists('in_projects', $data)) {
            return;
        }

        app(OperationGroupDefinitionService::class)->syncForUnit($unit, (bool) $data['in_projects'], (int) ($data['project_sort'] ?? 0));
    }
}
