<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\ProposalDocumentRole;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalDocument;
use App\Models\Acquisition\ProposalVersion;
use App\Query\Document\FixedDocumentQueries;
use App\Support\UploadLimits;
use BackedEnum;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Teklif belgeleri (B43, D-155; 5 Ekim 2026 kullanici talimati): firmanin
 * beklentileri, teklif mektubu, sartname uygunlugu, deviasyon listesi (eski
 * "Sapmalar"), marka listesi ve sorumluluk matrisi kucuk yukleme kutulari
 * olarak yan yana durur (genis ekranda alti tek satir). Ilk etapta hepsi
 * belgedir (Excel ya da herhangi bir dosya); madde madde giris yoktur.
 *
 * Duzenlemede her kutunun altinda guncel surumdeki dosya yazar; yeni yukleme
 * ayni belgenin yeni revizyonu ve teklifin yeni surumudur, eski dosya silinmez
 * (AcquisitionIntakeService::reviseProposal).
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

    private ?bool $hasReferenceDocument = null;

    private ?bool $hasCatalogDocument = null;

    /**
     * @param  Closure(Get): bool  $visible
     */
    public function section(Closure $visible): Section
    {
        $cells = [];

        foreach (self::DOCUMENTS as $key => $role) {
            $cells[] = Group::make([
                FileUpload::make($key.'_file')
                    ->label($role->getLabel())
                    ->disk('local')
                    ->directory(self::UPLOAD_DIRECTORY)
                    ->storeFileNamesIn($key.'_file_name')
                    ->maxSize(UploadLimits::documentMaxKb()),
                Hidden::make($key.'_file_name'),
                TextEntry::make($key.'_current')
                    ->hiddenLabel()
                    ->state(fn (?Model $record): string => DocumentLine::text(self::currentInfo($record, $role)))
                    ->url(fn (?Model $record): ?string => self::currentInfo($record, $role)['url'] ?? null)
                    ->visible(fn (?Model $record): bool => self::currentInfo($record, $role) !== null)
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->iconColor('primary')
                    ->color('primary')
                    ->size(TextSize::Small)
                    ->dehydrated(false),
                ...($role === ProposalDocumentRole::SpecCompliance
                    ? [ChecklistSchema::aiBadge(__('proposal.help.ai_soon_short'))->tooltip(__('checklist.ai_spec_soon'))]
                    : []),
            ]);
        }

        return Section::make(__('proposal.sections.documents'))
            ->key('proposal-documents')
            ->description(__('proposal.help.documents'))
            ->icon(Heroicon::OutlinedPaperClip)
            ->compact()
            ->visible($visible)
            ->components([
                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 6])
                    ->extraAttributes(['class' => 'kc-doc-grid'])
                    ->components($cells),
                Grid::make(['default' => 1, 'md' => 2])->components([
                    Toggle::make('attach_references')
                        ->label(__('business_case.fields.attach_references'))
                        ->helperText(__('business_case.help.attach_references'))
                        ->default(true)
                        ->inline(false)
                        ->visible(fn (): bool => $this->hasReferenceDocument()),
                    Toggle::make('attach_catalog')
                        ->label(__('business_case.fields.attach_catalog'))
                        ->helperText(__('business_case.help.attach_catalog'))
                        ->default(true)
                        ->inline(false)
                        ->visible(fn (): bool => $this->hasCatalogDocument()),
                    Text::make(__('business_case.help.fixed_document_missing'))
                        ->color('warning')
                        ->icon(Heroicon::OutlinedExclamationTriangle)
                        ->visible(fn (): bool => ! $this->hasReferenceDocument() || ! $this->hasCatalogDocument())
                        ->columnSpanFull(),
                ]),
            ]);
    }

    /**
     * Duzenleme formu: sabit belgelerin secimi guncel surumdeki satirlardan.
     *
     * @return array{attach_references: bool, attach_catalog: bool}
     */
    public static function formData(Proposal $proposal): array
    {
        $roles = [];

        foreach ($proposal->currentVersion?->documents ?? [] as $row) {
            /** @var ProposalDocument $row */
            $roles[] = self::roleValue($row);
        }

        return [
            'attach_references' => in_array(ProposalDocumentRole::References->value, $roles, true),
            'attach_catalog' => in_array(ProposalDocumentRole::Catalog->value, $roles, true),
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
                ->iconColor($role?->getColor() ?? 'primary')
                ->color('primary')
                ->url($info['url'] ?? null)
                ->openUrlInNewTab();
        }

        return Section::make(__($prefix === 'proposal_document' ? 'proposal.sections.documents' : 'proposal.sections.version_documents'))
            ->key($prefix.'-card')
            ->icon(Heroicon::OutlinedPaperClip)
            ->compact()
            ->components($entries === []
                ? [Text::make(__('proposal.versions.no_documents'))->color('gray')]
                : [Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->components($entries)]);
    }

    /**
     * @return array{title: string, revision: string|null, file: string|null, url: string|null}|null
     */
    private static function currentInfo(?Model $record, ProposalDocumentRole $role): ?array
    {
        if (! $record instanceof Proposal) {
            return null;
        }

        /** @var ProposalDocument|null $row */
        $row = $record->currentVersion?->documents->first(static fn (ProposalDocument $row): bool => self::roleValue($row) === $role->value);

        return $row === null ? null : DocumentLine::info($row->documentRevision?->document, $row->documentRevision);
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
}
