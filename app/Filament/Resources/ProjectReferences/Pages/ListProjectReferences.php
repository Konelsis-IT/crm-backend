<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectReferences\Pages;

use App\Enums\Acquisition\ProjectScopeType;
use App\Filament\Resources\ProjectReferences\ProjectReferenceResource;
use App\Query\Acquisition\ProjectReferenceQueries;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Referanslar listesi (D-177). "Referans ekle" ve "Excel" tablonun ust
 * eylemleridir (ReferenceTable); teklifteki pencereyle ayni yerde durur.
 *
 * D-183 (9 Ekim 2026 kullanici talimati: "proje tipine gore sekmelere ayir.
 * Filtre yerine sekme daha akici bir kullanim olur"): proje tipi suzgeci yerine
 * sekmeler — Tumu ve referansi olan her proje tipi (simgesi ve arsivde olmayan
 * referans sayisiyla). Arama, Arsiv suzgeci (Aktif / Arsivlenenler / Tumu) ve
 * Excel kalir; Excel acik sekmenin tipini verir (ReferenceTable::excelAction).
 */
class ListProjectReferences extends ListRecords
{
    protected static string $resource = ProjectReferenceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $queries = app(ProjectReferenceQueries::class);
        $counts = $queries->countsByType(array_map(static fn (ProjectScopeType $type): string => $type->value, ProjectScopeType::cases()));

        $tabs = [
            'all' => Tab::make(__('project_reference.tabs.all'))
                ->badge($queries->activeTotal()),
        ];

        foreach (ProjectScopeType::cases() as $type) {
            $count = $counts[$type->value] ?? 0;

            if ($count === 0) {
                continue;
            }

            $tabs[$type->value] = Tab::make((string) $type->getLabel())
                ->icon($type->getIcon())
                ->badge($count)
                ->modifyQueryUsing(static fn (Builder $query): Builder => $queries->withTypes($query, [$type->value]));
        }

        return $tabs;
    }
}
