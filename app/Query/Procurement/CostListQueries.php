<?php

declare(strict_types=1);

namespace App\Query\Procurement;

use App\Enums\Acquisition\ProposalScopeDocumentRole;
use App\Enums\Document\DocumentRevisionFileRole;
use App\Enums\Procurement\CatalogMatchStatus;
use App\Enums\Procurement\CostLineType;
use App\Enums\Procurement\PriceState;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersionScopeDocument;
use App\Models\Document\DocumentRevision;
use App\Models\Document\DocumentRevisionFile;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\CostList;
use App\Models\Procurement\CostListLine;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;

/**
 * Teklif maliyet kalemleri okumalari (B52, D-189): teklif sayfasindaki
 * "Maliyet kalemleri" sekmesi (guncel surumun kalemleri, sekme sayilari,
 * ozet satiri, aktarilabilir maliyet listesi belgeleri) ve katalog kalemi
 * sayfasindaki "Teklif ve projeler" listesi.
 */
final class CostListQueries
{
    /**
     * Teklifin guncel surumunun maliyet kalemleri. Iliski (Relation) doner:
     * teklif sayfasindaki sekme bir RelationManager'dir ve Filament duz sorgu
     * (Builder) kabul etmez (12 Ekim 2026 hatasi: getRelationshipQuery()
     * Query\Builder dondurdu).
     */
    public function linesForVersion(Proposal $proposal, ?int $versionId): HasManyThrough
    {
        $lines = (new CostListLine)->getTable();

        return $proposal->costItems()
            ->where((new CostList)->getTable().'.proposal_version_id', $versionId ?? 0)
            ->with(['catalogItem', 'suggestedItem', 'purchaseNeed', 'costList'])
            ->orderBy($lines.'.cost_list_id')
            ->orderBy($lines.'.sort_order')
            ->orderBy($lines.'.id');
    }

    /**
     * Katalog kalemini isteyen maliyet satirlari (teklif, potansiyel is kodu ve
     * proje ile). Iliski doner (kalem sayfasindaki RelationManager icin).
     */
    public function linesForCatalogItem(CatalogItem $item): HasMany
    {
        return $item->costLines()
            ->with([
                'costList.proposal.businessCase.codes',
                'costList.proposal.businessCase.project',
                'purchaseNeed.project',
            ])
            ->latest('id');
    }

    public function ofType(Builder $query, CostLineType $type): Builder
    {
        return $query->where($query->qualifyColumn('line_type'), $type->value);
    }

    /** "Tutari olanlar": toplam maliyeti sifirdan buyuk satirlar. */
    public function withCost(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('total_cost'), '>', 0);
    }

    /** Henuz satin almaya gonderilmemis satirlar. */
    public function notSent(Builder $query): Builder
    {
        return $query->whereDoesntHave('purchaseNeed');
    }

    /**
     * Sekme rozetleri: tur basina tutari olan satir sayisi.
     *
     * @return array<string, int>
     */
    public function typeCounts(?int $versionId): array
    {
        $counts = array_fill_keys(array_map(static fn (CostLineType $type): string => $type->value, CostLineType::cases()), 0);

        if ($versionId === null || ! SchemaReadiness::hasBatch('B52')) {
            return $counts;
        }

        foreach ($this->withCost($this->versionLines($versionId))->get(['id', 'line_type']) as $line) {
            /** @var CostListLine $line */
            $type = self::value($line->getAttribute('line_type'));

            if (array_key_exists($type, $counts)) {
                $counts[$type]++;
            }
        }

        return $counts;
    }

    /**
     * Ozet satiri: tutari olan kalemler, katalog eslesmeleri, para birimi
     * basina toplam maliyet ve fiyati netlesmeyen tutar.
     *
     * @return array{count: int, matched: int, new: int, similar: int, total: array<string, float>, open: array<string, float>}
     */
    public function summary(?int $versionId, ?CostLineType $type = null): array
    {
        $summary = ['count' => 0, 'matched' => 0, 'new' => 0, 'similar' => 0, 'total' => [], 'open' => []];

        if ($versionId === null || ! SchemaReadiness::hasBatch('B52')) {
            return $summary;
        }

        $query = $this->withCost($this->versionLines($versionId))->with('costList:id,currency_code');

        if ($type !== null) {
            $this->ofType($query, $type);
        }

        foreach ($query->get() as $line) {
            /** @var CostListLine $line */
            $summary['count']++;
            $status = self::value($line->getAttribute('match_status'));

            if (array_key_exists($status, $summary) && in_array($status, ['matched', 'new', 'similar'], true)) {
                $summary[$status]++;
            }

            $currency = strtoupper((string) ($line->getAttribute('currency_code') ?: $line->costList?->getAttribute('currency_code') ?: ''));
            $amount = (float) $line->getAttribute('total_cost');
            $summary['total'][$currency] = ($summary['total'][$currency] ?? 0.0) + $amount;

            if (self::value($line->getAttribute('price_state')) === PriceState::Open->value) {
                $summary['open'][$currency] = ($summary['open'][$currency] ?? 0.0) + $amount;
            }
        }

        return $summary;
    }

    /**
     * Guncel surumun kapsamlarina yuklenmis, bu surume henuz aktarilmamis
     * maliyet listesi belgeleri.
     *
     * @return Collection<int, ProposalVersionScopeDocument>
     */
    public function importableDocuments(Proposal $proposal): Collection
    {
        $versionId = $proposal->getAttribute('current_version_id');

        if ($versionId === null || ! SchemaReadiness::hasBatch('B51') || ! SchemaReadiness::hasBatch('B52')) {
            return new Collection;
        }

        $imported = CostList::query()
            ->where('proposal_version_id', $versionId)
            ->pluck('document_revision_id')
            ->all();

        return ProposalVersionScopeDocument::query()
            ->where('document_role', ProposalScopeDocumentRole::CostList->value)
            ->whereHas('versionScope', fn (Builder $scope): Builder => $scope->where('proposal_version_id', $versionId))
            ->when($imported !== [], fn (Builder $query): Builder => $query->whereNotIn('document_revision_id', $imported))
            ->with(['versionScope', 'documentRevision.document', 'documentRevision.files.fileObject'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();
    }

    /**
     * "Excel'den aktar" secenekleri: dosya adi · proje tipi.
     *
     * @return array<int, string>
     */
    public function importOptions(Proposal $proposal): array
    {
        $options = [];

        foreach ($this->importableDocuments($proposal) as $document) {
            $type = $document->versionScope?->getAttribute('scope_type');
            $typeLabel = $type instanceof HasLabel ? (string) $type->getLabel() : null;
            $options[(int) $document->getKey()] = implode(' · ', array_filter([
                self::fileName($document->documentRevision),
                $typeLabel,
            ], static fn (?string $part): bool => filled($part)));
        }

        return $options;
    }

    /** Secilen belge (yalniz guncel surumun aktarilmamis belgelerinden). */
    public function importableDocument(Proposal $proposal, int $documentId): ?ProposalVersionScopeDocument
    {
        $document = $this->importableDocuments($proposal)->first(fn (ProposalVersionScopeDocument $row): bool => (int) $row->getKey() === $documentId);

        return $document instanceof ProposalVersionScopeDocument ? $document : null;
    }

    /**
     * Aktarim sonucu: satir sayisi ve katalog eslesmeleri.
     *
     * @return array{count: int, matched: int, new: int, similar: int}
     */
    public function importSummary(CostList $list): array
    {
        $summary = ['count' => 0, 'matched' => 0, 'new' => 0, 'similar' => 0];

        foreach (CostListLine::query()->where('cost_list_id', $list->getKey())->pluck('match_status') as $status) {
            $summary['count']++;
            $key = self::value($status);

            if (in_array($key, [CatalogMatchStatus::Matched->value, CatalogMatchStatus::New->value, CatalogMatchStatus::Similar->value], true)) {
                $summary[$key]++;
            }
        }

        return $summary;
    }

    /** Surumun butun maliyet satirlari (yukleme ve siralama olmadan). */
    private function versionLines(?int $versionId): Builder
    {
        return CostListLine::query()
            ->whereHas('costList', fn (Builder $list): Builder => $list->where('proposal_version_id', $versionId ?? 0));
    }

    private static function fileName(?DocumentRevision $revision): ?string
    {
        if (! $revision instanceof DocumentRevision) {
            return null;
        }

        foreach ($revision->files as $file) {
            /** @var DocumentRevisionFile $file */
            if (self::value($file->getAttribute('file_role')) === DocumentRevisionFileRole::Original->value) {
                $name = $file->fileObject?->getAttribute('original_name');

                if (filled($name)) {
                    return (string) $name;
                }
            }
        }

        $title = $revision->document?->getAttribute('title');

        return filled($title) ? (string) $title : null;
    }

    private static function value(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }
}
