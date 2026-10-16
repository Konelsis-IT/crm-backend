<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Document\DocumentRevisionFileRole;
use App\Enums\Procurement\CatalogItemKind;
use App\Enums\Procurement\CatalogMatchStatus;
use App\Enums\Procurement\CostLineType;
use App\Exceptions\Procurement\CostListAlreadyImportedException;
use App\Exceptions\Procurement\CostListFormatException;
use App\Exceptions\RecordNotFoundException;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Acquisition\ProposalVersionScopeDocument;
use App\Models\Document\DocumentRevision;
use App\Models\Document\DocumentRevisionFile;
use App\Models\Document\FileObject;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\CostList;
use App\Models\Procurement\CostListLine;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Support\Procurement\CostExcelParser;
use BackedEnum;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Teklif maliyet listesinin aktarimi (B52, D-189). Teklif kapsamina yuklenen
 * maliyet Excel'i (B51 MLY belgesi) kalemlere ayrilir; her kalem katalogla
 * eslesir (CatalogItemService::match, D-187 kurali). Ayni surume ayni belge
 * revizyonu bir kez aktarilir. Benzer cikan kalem secilene kadar katalogsuz
 * kalir (`resolveLine`). Excel'deki satir rengi fiyat durumudur.
 */
final class CostListService extends AbstractService
{
    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly CostExcelParser $parser,
        private readonly CatalogItemService $catalog,
        private readonly CostListLineService $lines,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /** Teklif kapsamindaki maliyet listesi belgesini aktarir. */
    public function importScopeDocument(ProposalVersionScopeDocument $document): CostList
    {
        // ProposalVersionScope'un surum iliskisinin adi `version`dir (12 Ekim 2026 hatasi: proposalVersion yok).
        $scope = $document->versionScope()->with('version')->first();
        $version = $scope?->version;
        $revision = $document->documentRevision;

        if (! $version instanceof ProposalVersion || ! $revision instanceof DocumentRevision) {
            throw RecordNotFoundException::make();
        }

        return $this->import($version, $revision, (int) $scope->getKey());
    }

    /** Teklif surumune bir belge revizyonundaki maliyet Excel'ini aktarir. */
    public function import(ProposalVersion $version, DocumentRevision $revision, ?int $scopeId = null): CostList
    {
        $exists = CostList::query()
            ->where('proposal_version_id', $version->getKey())
            ->where('document_revision_id', $revision->getKey())
            ->exists();

        if ($exists) {
            throw CostListAlreadyImportedException::make();
        }

        [$path, $temp, $fileName] = $this->localFile($revision);

        try {
            $currency = strtoupper((string) ($version->getAttribute('currency_code') ?: 'USD'));
            $parsed = $this->parser->parse($path, $currency);
        } finally {
            if ($temp !== null) {
                @unlink($temp);
            }
        }

        return $this->transactions->run(function () use ($version, $revision, $scopeId, $fileName, $currency, $parsed): CostList {
            $this->catalog->forgetIndex();

            /** @var CostList $list */
            $list = parent::create([
                'proposal_id' => $version->getAttribute('proposal_id'),
                'proposal_version_id' => $version->getKey(),
                'proposal_version_scope_id' => $scopeId,
                'document_revision_id' => $revision->getKey(),
                'source_file_name' => mb_substr($fileName, 0, 255),
                'currency_code' => $currency,
                'total_cost' => round($parsed['total_cost'], 4),
                'total_sale' => $parsed['total_sale'] !== null ? round($parsed['total_sale'], 4) : null,
                'line_count' => count($parsed['lines']),
            ]);

            foreach ($parsed['lines'] as $line) {
                $type = CostLineType::from((string) $line['line_type']);
                $match = $this->catalog->match((string) $line['description'], $type->catalogKind(), [
                    'section' => $line['section_name'] ?? null,
                    'group' => $line['group_name'] ?? null,
                    'unit' => $line['unit'] ?? null,
                    'currency' => $line['currency_code'] ?? null,
                ]);

                $this->lines->createQuietly([
                    ...$line,
                    'cost_list_id' => $list->getKey(),
                    'catalog_item_id' => $match['item']?->getKey(),
                    'match_status' => $match['status']->value,
                    'suggested_catalog_item_id' => $match['suggested']?->getKey(),
                    'match_score' => $match['score'],
                ]);
            }

            return $list;
        });
    }

    /**
     * Benzer cikan satiri cozer: secilen katalog kalemine baglar (yazim esleme
     * adi olur) ya da yeni kalem olarak bir kez acar.
     */
    public function resolveLine(CostListLine $line, ?int $catalogItemId, bool $asNew = false): CostListLine
    {
        return $this->transactions->run(function () use ($line, $catalogItemId, $asNew): CostListLine {
            $type = $line->getAttribute('line_type');
            $type = $type instanceof CostLineType ? $type : CostLineType::from((string) ($type instanceof BackedEnum ? $type->value : $type));
            $description = (string) $line->getAttribute('description');

            if ($asNew) {
                $item = $this->catalog->match($description, $type->catalogKind(), [
                    'section' => $line->getAttribute('section_name'),
                    'group' => $line->getAttribute('group_name'),
                    'unit' => $line->getAttribute('unit'),
                    'currency' => $line->getAttribute('currency_code'),
                ])['item'] ?? $this->catalog->createFromExcel($description, $type->catalogKind(), [
                    'section' => $line->getAttribute('section_name'),
                    'group' => $line->getAttribute('group_name'),
                    'unit' => $line->getAttribute('unit'),
                ]);
                $status = CatalogMatchStatus::New;
            } else {
                /** @var CatalogItem|null $item */
                $item = CatalogItem::query()->find($catalogItemId);

                if (! $item instanceof CatalogItem) {
                    throw RecordNotFoundException::make();
                }

                $this->catalog->link($item, $description);
                $status = CatalogMatchStatus::Matched;
            }

            $previousId = $line->getAttribute('catalog_item_id');

            /** @var CostListLine $updated */
            $updated = $this->lines->update($line, [
                'catalog_item_id' => $item?->getKey(),
                'match_status' => $status->value,
                'suggested_catalog_item_id' => null,
                'match_score' => null,
            ]);

            // Aktarimin "Yeni" diye actigi kalem var olana baglandiysa ve baska yerde
            // kullanilmiyorsa katalogda cift kayit olarak kalmaz (D-187 kurali).
            if ($previousId !== null && (int) $previousId !== (int) $item?->getKey()) {
                $this->catalog->dropUnused((int) $previousId);
            }

            return $updated;
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        return [
            'dosya' => $record->getAttribute('source_file_name'),
            'kalem' => $record->getAttribute('line_count'),
            'teklif_surumu' => $record->getAttribute('proposal_version_id'),
        ];
    }

    /**
     * Revizyonun asil dosyasinin yerel yolu; yerel olmayan diskte gecici kopya.
     *
     * @return array{0: string, 1: string|null, 2: string}
     */
    private function localFile(DocumentRevision $revision): array
    {
        /** @var DocumentRevisionFile|null $row */
        $row = $revision->files()->where('file_role', DocumentRevisionFileRole::Original->value)->with('fileObject')->first();
        $file = $row?->fileObject;

        if (! $file instanceof FileObject) {
            throw RecordNotFoundException::make();
        }

        $name = (string) $file->getAttribute('original_name');

        if (! in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['xlsx', 'xlsm'], true)) {
            throw CostListFormatException::make();
        }

        /** @var Filesystem $disk */
        $disk = Storage::disk($file->getAttribute('storage_disk') ?: 'local');
        $key = (string) $file->getAttribute('storage_key');

        if ($disk instanceof FilesystemAdapter) {
            try {
                $path = $disk->path($key);

                if (is_file($path)) {
                    return [$path, null, $name];
                }
            } catch (Throwable) {
                // Yerel olmayan disk: gecici kopya.
            }
        }

        $temp = tempnam(sys_get_temp_dir(), 'kccl');
        $stream = $disk->readStream($key);

        if ($temp === false || ! is_resource($stream)) {
            throw RecordNotFoundException::make();
        }

        $out = fopen($temp, 'wb');

        if ($out === false) {
            throw RecordNotFoundException::make();
        }

        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);

        return [$temp, $temp, $name];
    }
}
