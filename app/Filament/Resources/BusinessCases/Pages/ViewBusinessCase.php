<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases\Pages;

use App\Enums\Platform\Feature;
use App\Filament\Exports\BusinessCaseExporter;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\ChecklistSchema;
use App\Filament\Support\DealTrack;
use App\Filament\Support\DocumentBundleAction;
use App\Services\Platform\FeatureFlags;
use App\Filament\Support\ExportActions;
use App\Filament\Support\StatusButton;
use App\Models\Acquisition\BusinessCase;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\Action;
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
 * (gorusme notlari, firsat, ihale ilanlari, sozlesmeler, Operasyona devirler)
 * yaninda. D-155: Aktiviteler sekmesi kaldirildi (gorusme notlari yeterli);
 * kontrol listesi (sicaklik) ve belgeler kartlari kartlarin altinda.
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

        // B43 (D-155): kontrol listesi ve belgeler kartlari.
        if (SchemaReadiness::hasBatch('B43')) {
            $case->loadMissing(['checklistAnswers', 'caseDocuments.document.revisions.files.fileObject']);
        }

        $checklist = app(ChecklistSchema::class);

        // Kart yarim genislik, yaninda olusturmada girilen ayrintilar (22 Eylul
        // 2026 kullanici karari); her kart kendi boyunu korur (kc-grid-top).
        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'xl' => 2])
                ->extraAttributes(['class' => 'kc-grid-top'])
                ->components([
                    $wizard->headerCard($case),
                    $wizard->detailsCard($case),
                ]),
            // D-158: kontrol listesi duzenleme ekranindaki tahtanin salt okunur hali.
            ...array_filter([$checklist->viewBoard($case), $checklist->documentsCard($case)]),
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
            // Durum (D-178, D-182): durum acilir dugmesi yeni durumu burada
            // kaydeder; ozellik kapaliyken D-161'deki sabit dugme.
            ...StatusButton::businessCaseHeader($case),
            // "Duzenle" potansiyel is adimini acar (D-160: hangi ekranda basildiysa
            // onun adimi; teklif / proje adimi degil).
            EditAction::make()
                ->url(fn (): string => BusinessCaseResource::getUrl('edit', [
                    'record' => $this->getRecord(),
                    'step' => BusinessCaseWizard::STEP_CASE,
                ])),
            Action::make('open_project')
                ->label(__('project.actions.open_workspace'))
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->color('gray')
                ->visible(fn (): bool => $this->getRecord()->project !== null)
                ->url(fn (): string => ProjectResource::getUrl('view', ['record' => $this->getRecord()->project])),
            $wizard->convertAction($case),
            // D-184: teklifle ayni duzen; "Tum belgeleri indir" baslikta yalniz simge
            // (Belgeler kartindaki dugme kalir).
            DocumentBundleAction::businessCaseHeader($case),
            ExportActions::record(BusinessCaseExporter::class),
        ];
    }
}
