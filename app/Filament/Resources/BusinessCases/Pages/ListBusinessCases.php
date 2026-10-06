<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases\Pages;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Platform\Feature;
use App\Filament\Exports\BusinessCaseExporter;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Support\DraftSupport;
use App\Filament\Support\ExportActions;
use App\Models\Acquisition\BusinessCase;
use App\Query\Acquisition\BusinessCaseQueries;
use App\Services\Platform\FeatureFlags;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListBusinessCases extends ListRecords
{
    protected static string $resource = BusinessCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportActions::table(BusinessCaseExporter::class),
            CreateAction::make()
                ->label(__('business_case.actions.create'))
                ->icon(Heroicon::OutlinedBriefcase),
        ];
    }

    /**
     * Tumu / Is Gelistirme / Teklifte / Taslaklar (D-162, 6 Ekim 2026 kullanici
     * talimati). Taslaklar yalniz kendi sekmesinde; sonuclanan isler (kazanildi,
     * kaybedildi, devir, iptal) Tumu'de. Durum sekmeleri kendi ozellik
     * anahtariyla kapanir; kapaliyken Tumu / Taslaklar (B43, D-155; taslak
     * ozelligi de kapaliysa sekme yok).
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        if (! FeatureFlags::enabled(Feature::BusinessCaseStageTabs)) {
            return DraftSupport::tabs(BusinessCase::class);
        }

        $queries = app(BusinessCaseQueries::class);
        $drafts = DraftSupport::enabled();
        $tabs = ['all' => Tab::make(__('app.tabs.all'))->badge($queries->total())];

        foreach ([
            'business_development' => [AcquisitionStage::BusinessDevelopment],
            'in_offer' => BusinessCaseQueries::OFFER_STAGES,
        ] as $key => $stages) {
            $tabs[$key] = Tab::make(__('business_case.tabs.'.$key))
                ->badge($queries->stageCount($stages, $drafts))
                ->badgeColor($stages[0]->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $queries->withStages($query, $stages, $drafts));
        }

        if ($drafts) {
            $tabs['drafts'] = DraftSupport::draftTab(BusinessCase::class);
        }

        return $tabs;
    }
}
