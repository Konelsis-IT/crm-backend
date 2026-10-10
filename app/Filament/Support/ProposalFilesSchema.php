<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\ProposalDocumentRole;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalDocument;
use App\Models\Acquisition\ProposalVersion;
use App\Query\Document\AutomaticDocumentQueries;
use App\Query\Document\FixedDocumentQueries;
use App\Services\Acquisition\AcquisitionIntakeService;
use App\Support\UploadLimits;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Teklif belgeleri (B43, D-155; 5 Ekim 2026 kullanici talimati): firmanin
 * beklentileri, teklif mektubu, sartname uygunlugu, deviasyon listesi (eski
 * "Sapmalar"), marka listesi ve sorumluluk matrisi kucuk yukleme kutulari
 * olarak yan yana durur (D-185: CompactUpload kutulari, genis ekranda ucer
 * ucer; kayitli dosyalar notr cip, kucuk "Yukle" dugmesi). Ilk etapta hepsi
 * belgedir (Excel ya da herhangi bir dosya); madde madde giris yoktur.
 *
 * D-176: kutular coklu dosya alir; bir turde birden fazla belge olabilir.
 * Otomatik belgeler acikken "Genel kataloğu ekle" secimi gizlidir (katalog her
 * teklifte kendiliginden gorunur).
 *
 * D-186 (9 Ekim 2026 kullanici karari, teklifte belge revizyonu yok): duzenleme
 * ve "Yeni teklif surumu" ekraninda kutudaki kayitli dosyalar carpili ciplerdir
 * (`{anahtar}_keep`, CompactUpload::editSlot); carpi belgeyi surumden cikarir
 * (Document kaydi Dokumanlar'da kalir), yuklenen her dosya yeni belgedir
 * (AcquisitionIntakeService::updateProposal / newProposalVersion).
 *
 * Sartname uygunlugu: sartnamede sari (konusulacak), yesil (karsilanabilir),
 * kirmizi (karsilanamaz) isaretli maddeleri KonelsisAI ozetleyecek; o gelene
 * kadar belge olarak yuklenir ("KonelsisAI yakinda" etiketi).
 */
final class ProposalFilesSchema
{
    /** Gecici yukleme dizini (DocumentService dosyayi buradan alir). */
    private const UPLOAD_DIRECTORY = 'document-uploads-tmp';

    /**
     * Form anahtari => teklif belgesi rolu (dosya `{anahtar}_file`, ad `{anahtar}_file_name`).
     *
     * @var array<string, ProposalDocumentRole>
     */
    public const DOCUMENTS = [
        'customer_expectations' => ProposalDocumentRole::CustomerExpectations,
        'proposal_letter' => ProposalDocumentRole::ProposalLetter,
        'spec_compliance' => ProposalDocumentRole::SpecCompliance,
        'deviation_list' => ProposalDocumentRole::DeviationList,
        'brand_list' => ProposalDocumentRole::BrandList,
        'responsibility_matrix' => ProposalDocumentRole::ResponsibilityMatrix,
    ];

    /** @var array<string, array<int, array{title: string, revision: string|null, file: string|null, url: string|null}>> Ayni istekte bir kez okunur. */
    private static array $infoCache = [];

    private ?bool $hasReferenceDocument = null;

    private ?bool $hasCatalogDocument = null;

    /** Genel katalog onizleme adresi; false = henuz okunmadi (D-184). */
    private string|false|null $catalogUrl = false;

    /**
     * @param  Closure(Get): bool  $visible
     */
    public function section(Closure $visible): Section
    {
        $cells = [];

        foreach (self::DOCUMENTS as $key => $role) {
            // D-185: her tur kucuk bir kutu: baslik, kayitli dosyalar, altinda kucuk
            // "Yukle" dugmesi (CompactUpload). D-176: bir turde birden fazla belge.
            // D-186: kayitli dosyalar carpili cip (carpi = surumden cikar), her
            // yeni dosya yeni belge.
            $cells[] = CompactUpload::editSlot(
                $key.AcquisitionIntakeService::REMOVED_SUFFIX,
                $role === ProposalDocumentRole::SpecCompliance
                    ? CompactUpload::taggedLabel((string) $role->getLabel(), __('proposal.help.ai_soon_short'), __('checklist.ai_spec_soon'))
                    : (string) $role->getLabel(),
                static fn (?Model $record): array => array_values(self::currentInfos($record, $role)),
                FileUpload::make($key.'_file')
                    ->label($role->getLabel())
                    ->multiple()
                    ->disk('local')
                    ->directory(self::UPLOAD_DIRECTORY)
                    ->storeFileNamesIn($key.'_file_name')
                    ->maxSize(UploadLimits::documentMaxKb()),
                [Hidden::make($key.'_file_name')],
            );
        }

        // D-185: bolum aciklamasi ("Excel ya da herhangi bir dosya ...") kaldirildi.
        return Section::make(__('proposal.sections.documents'))
            ->key('proposal-documents')
            ->icon(Heroicon::OutlinedPaperClip)
            ->headerActions([$this->catalogAction('open_general_catalog')])
            ->compact()
            ->visible($visible)
            ->components([
                Grid::make(['default' => 1, 'sm' => 2, 'xl' => 3])
                    ->components($cells),
                Grid::make(['default' => 1, 'md' => 2])->components([
                    Toggle::make('attach_references')
                        ->label(__('business_case.fields.attach_references'))
                        ->helperText(__('business_case.help.attach_references'))
                        ->default(true)
                        ->inline(false)
                        ->visible(fn (): bool => $this->hasReferenceDocument()),
                    // D-176: otomatik belgeler acikken katalog her teklifte kendiliginden
                    // gorunur; secim ve teklife baglama yok.
                    Toggle::make('attach_catalog')
                        ->label(__('business_case.fields.attach_catalog'))
                        ->helperText(__('business_case.help.attach_catalog'))
                        ->default(true)
                        ->inline(false)
                        ->visible(fn (): bool => ! AutomaticDocumentQueries::enabled() && $this->hasCatalogDocument()),
                    // D-183: "sabit belge yuklenmedi" uyari metni kullanici istegiyle kaldirildi.
                ]),
            ]);
    }

    /** Kaydetmeden sonra ayni istekte form yeniden dolarken eski belge listesi kullanilmasin (D-186). */
    public static function flushCache(): void
    {
        self::$infoCache = [];
    }

    /**
     * Duzenleme formu: sabit belgelerin secimi guncel surumdeki satirlardan;
     * D-186: cipin "x"i ile isaretlenecek belgeler her turde bos dizi.
     *
     * @return array<string, mixed>
     */
    public static function formData(Proposal $proposal): array
    {
        $roles = [];
        $removed = [];

        foreach (array_keys(self::DOCUMENTS) as $key) {
            $removed[$key.AcquisitionIntakeService::REMOVED_SUFFIX] = [];
        }

        foreach ($proposal->currentVersion?->documents ?? [] as $row) {
            /** @var ProposalDocument $row */
            $roles[] = self::roleValue($row);
        }

        return [
            'attach_references' => in_array(ProposalDocumentRole::References->value, $roles, true),
            'attach_catalog' => in_array(ProposalDocumentRole::Catalog->value, $roles, true),
            ...$removed,
        ];
    }

    /**
     * Teklif sayfasindaki belge karti: guncel surumun belgeleri rol adiyla.
     */
    public function recordCard(Proposal $proposal): ?Component
    {
        return $proposal->currentVersion === null ? null : $this->versionCard($proposal->currentVersion, emptyText: false);
    }

    /**
     * Bir surumun belgeleri (D-158 surum penceresi): rol, dosya, revizyon;
     * tiklaninca dosya yeni sekmede iner. $emptyText: belge yoksa kart yerine
     * "Bu surumde belge yok" yazar (pencere) ya da hic cizilmez (sayfa).
     */
    public function versionCard(ProposalVersion $version, string $prefix = 'proposal_document', bool $emptyText = true): ?Component
    {
        $rows = $version->documents;

        if ($rows->isEmpty() && ! $emptyText) {
            return null;
        }

        $entries = [];

        foreach ($rows->sortBy('sort_order') as $row) {
            /** @var ProposalDocument $row */
            $role = ProposalDocumentRole::tryFrom(self::roleValue($row));
            $info = DocumentLine::info($row->documentRevision?->document, $row->documentRevision);

            $entries[] = TextEntry::make($prefix.'_'.$row->getKey())
                ->label($role?->getLabel() ?? self::roleValue($row))
                ->state(DocumentLine::text($info))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->iconColor($role?->getColor() ?? 'gray')
                // D-185: veri baglantisi notr renkte (kirmizi degil).
                ->url($info['url'] ?? null)
                ->openUrlInNewTab();
        }

        return Section::make(__($prefix === 'proposal_document' ? 'proposal.sections.documents' : 'proposal.sections.version_documents'))
            ->key($prefix.'-card')
            ->icon(Heroicon::OutlinedPaperClip)
            ->headerActions($prefix === 'proposal_document' ? [$this->catalogAction('open_general_catalog_card')] : [])
            ->compact()
            ->components($entries === []
                ? [Text::make(__('proposal.versions.no_documents'))->color('gray')]
                : [Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->components($entries)]);
    }

    /**
     * Guncel surumde bu roldeki butun belgeler (D-176: bir rolde birden fazla
     * belge olabilir): revizyon kimligi => belge satiri (D-186 carpili secim).
     *
     * @return array<int, array{title: string, revision: string|null, file: string|null, url: string|null}>
     */
    private static function currentInfos(?Model $record, ProposalDocumentRole $role): array
    {
        if (! $record instanceof Proposal) {
            return [];
        }

        $cacheKey = $record->getKey().':'.$record->current_version_id.':'.$role->value;

        if (array_key_exists($cacheKey, self::$infoCache)) {
            return self::$infoCache[$cacheKey];
        }

        $infos = [];

        foreach ($record->currentVersion?->documents ?? [] as $row) {
            /** @var ProposalDocument $row */
            if (self::roleValue($row) !== $role->value) {
                continue;
            }

            $info = DocumentLine::info($row->documentRevision?->document, $row->documentRevision);

            if ($info !== null) {
                $infos[(int) $row->document_revision_id] = [...$info, 'revision_id' => (int) $row->document_revision_id];
            }
        }

        return self::$infoCache[$cacheKey] = $infos;
    }

    private static function roleValue(ProposalDocument $row): string
    {
        $role = $row->getAttribute('document_role');

        return $role instanceof BackedEnum ? (string) $role->value : (string) $role;
    }

    private function hasReferenceDocument(): bool
    {
        return $this->hasReferenceDocument ??= app(FixedDocumentQueries::class)->referenceDocument() !== null;
    }

    private function hasCatalogDocument(): bool
    {
        return $this->hasCatalogDocument ??= app(FixedDocumentQueries::class)->catalogDocument() !== null;
    }

    /**
     * D-184 (9 Ekim 2026 kullanici talimati: "Teklif belgelerinin yan tarafina
     * action kismina, referansta oldugu gibi; Genel katalog direk tiklanmali, yan
     * sekmede pdf acilir, oradan indirirse indirir"): Teklif belgeleri basliginda
     * "Genel katalog" dugmesi. Dokumanlar'daki en yeni Genel katalog (KAT, D-176)
     * yeni sekmede satir ici acilir; yetki kararini dosya denetleyicisi verir.
     */
    public function catalogAction(string $name): Action
    {
        return Action::make($name)
            ->label(__('proposal.actions.general_catalog'))
            ->tooltip(__('proposal.actions.general_catalog_tooltip'))
            ->icon(Heroicon::OutlinedBookOpen)
            ->button()
            ->size(Size::Small)
            ->color(ActionColors::NEUTRAL)
            ->url(fn (): ?string => $this->catalogUrl())
            ->openUrlInNewTab()
            ->visible(fn (): bool => AutomaticDocumentQueries::enabled() && $this->catalogUrl() !== null);
    }

    private function catalogUrl(): ?string
    {
        if ($this->catalogUrl === false) {
            $revision = app(FixedDocumentQueries::class)->catalogDocument()?->displayRevision();
            $this->catalogUrl = $revision === null ? null : FileLinks::revisionOriginal($revision, 'inline');
        }

        return $this->catalogUrl;
    }
}
