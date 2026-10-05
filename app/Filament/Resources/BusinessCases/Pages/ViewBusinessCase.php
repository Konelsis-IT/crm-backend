<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases\Pages;

use App\Enums\Platform\Feature;
use App\Filament\Exports\BusinessCaseExporter;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\DealTrack;
use App\Services\Platform\FeatureFlags;
use App\Filament\Support\ExportActions;
use App\Models\Acquisition\BusinessCase;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Potansiyel is goruntuleme (D-72, D-113): kart ve ayrinti karti yan yana.
 * D-143 (29 Eylul 2026): eski "Is akisi" sihirbazi ve ayri durum notu yerine
 * "Bu is nerede?" dikey hatti (DealTrack): "Buradasiniz" bu potansiyel is
 * (durum, siradaki durumlar, sonuc, son gorusme), teklifleri kisa satirlarla ve
 * "Teklif olustur", proje ya da acilis kosullari. Tekliflerin tam tablosu
 * (secili yap, duzenle) altta "Teklifler" sekmesinde; diger alt listeler
 * (gorusme notlari, firsat, aktiviteler, ihale ilanlari, sozlesmeler,
 * Operasyona devirler) yaninda.
 */
class ViewBusinessCase extends ViewRecord
{
    protected static string $resource = BusinessCaseResource::class;

    public function getTitle(): string
    {
        /** @var BusinessCase $case */
        $case = $this->getRecord();

        return ($case->caseCode()?->formatted_code ?? '').' · '.$case->title;
    }

    public function content(Schema $schema): Schema
    {
        /** @var BusinessCase $case */
        $case = $this->getRecord();
        $wizard = app(BusinessCaseWizard::class);

        // B29: kart rozetleri ve ayrinti karti kapsamlari tek yuklemeyle okur.
        if (SchemaReadiness::hasBatch('B29')) {
            $case->loadMissing('scopes.scopeDocument.revisions.files.fileObject');
        }

        // Kart yarim genislik, yaninda olusturmada girilen ayrintilar (22 Eylul
        // 2026 kullanici karari); her kart kendi boyunu korur (kc-grid-top).
        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'xl' => 2])
                ->extraAttributes(['class' => 'kc-grid-top'])
                ->components([
                    $wizard->headerCard($case),
                    $wizard->detailsCard($case),
                ]),
            // "Bu iş nerede?" kendi ozellik anahtariyla kapanabilir (D-147).
            ...(FeatureFlags::enabled(Feature::DealTrack) ? [app(DealTrack::class)->forBusinessCase($case)] : []),
            $this->getRelationManagersContentComponent(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        /** @var BusinessCase $case */
        $case = $this->getRecord();
        $wizard = app(BusinessCaseWizard::class);

        return [
            EditAction::make()
                ->url(fn (): string => BusinessCaseResource::getUrl('edit', [
                    'record' => $this->getRecord(),
                    'step' => BusinessCaseWizard::STEP_IDS[$wizard->startStep($this->getRecord(), null) - 1],
                ])),
            Action::make('open_project')
                ->label(__('project.actions.open_workspace'))
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->color('gray')
                ->visible(fn (): bool => $this->getRecord()->project !== null)
                ->url(fn (): string => ProjectResource::getUrl('view', ['record' => $this->getRecord()->project])),
            // Durum yalniz izinli gecislerle degisir; sonuc durumdan gelir (28 Eylul 2026).
            ActionGroup::make(BusinessCaseResource::statusActions())
                ->label(__('business_case.actions.change_status'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->button(),
            $wizard->convertAction($case),
            ExportActions::record(BusinessCaseExporter::class),
        ];
    }
}
