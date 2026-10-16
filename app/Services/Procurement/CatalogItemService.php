<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Procurement\CatalogItemKind;
use App\Enums\Procurement\CatalogMatchStatus;
use App\Enums\Procurement\UsageArea;
use App\Services\Platform\SchemaReadiness;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\Procurement\CatalogItemExistsException;
use App\Exceptions\RecordArchivedException;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\CatalogItemAlias;
use App\Models\Procurement\PurchaseOrderLine;
use App\Models\Procurement\PurchaseQuotationLine;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\CodeGenerator;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Support\Procurement\CatalogNames;
use App\Support\Procurement\UnitResolver;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Sabit katalog (B52, D-189). Kullanicinin en buyuk kurali (D-187): "Excel
 * yuklendiginde urunleri oradan cekeceksin ve zaten var olan urunlerle
 * eslestireceksin; her seferinde yeni urun / hizmet / genel gider / idari kadro
 * kaydi olusturmayacaksin."
 *
 * - create / update: ad normalize edilir (CatalogNames); ayni turde (Urun ve
 *   Hizmet tek aile) ayni ad varsa yeni kalem acilmaz (CatalogItemExistsException).
 *   Kod addan uretilir, kullanici yazmaz, degismez (D-130).
 * - match(): Excel satirini once ada, sonra esleme adlarina gore var olan kaleme
 *   baglar; cok benzeyen ad "Benzer?" olarak secime sunulur; hic yoksa kalem
 *   bir kez acilir (Yeni). Ayni aktarimda ayni ad ikinci kez acilmaz.
 * - link(): bir yazimi bir kaleme baglar (esleme adi kaydi).
 * - archive / restore (D-156).
 */
final class CatalogItemService extends AbstractService
{
    /** @var list<string> */
    private const FIELDS = [
        'kind', 'usage_area', 'catalog_category_id', 'code', 'name', 'unit', 'currency_code', 'brand', 'specification',
        'requires_approval', 'renewal_months',
        // D-194 (B54): sabit birim; `unit` metni birimin adi olur (UnitResolver).
        'uom_id',
    ];

    /**
     * Esleme bellegi (aile => normalize ad => kalem kimligi); bir aktarim boyunca tutulur.
     *
     * @var array<string, array<string, int>>|null
     */
    private ?array $index = null;

    /** @var array<string, int>|null */
    private ?array $aliasIndex = null;

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly CodeGenerator $codes,
        private readonly CatalogCategoryService $categories,
        private readonly CatalogItemAliasService $aliases,
        private readonly UnitResolver $units,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    public function update(Model|int|string $record, array $data): Model
    {
        $current = $this->show($record);

        if ($current instanceof CatalogItem && $current->isArchived()) {
            throw RecordArchivedException::make();
        }

        $this->forgetIndex();

        return parent::update($record, $data);
    }

    public function create(array $data): Model
    {
        $item = parent::create($data);
        $this->remember($item);

        return $item;
    }

    /**
     * Excel satirini katalogla eslestirir.
     *
     * @param  array{section?: string|null, group?: string|null, unit?: string|null, currency?: string|null}  $context
     * @return array{status: CatalogMatchStatus, item: CatalogItem|null, suggested: CatalogItem|null, score: int|null}
     */
    public function match(string $name, CatalogItemKind $kind, array $context = []): array
    {
        $normalized = CatalogNames::normalize($name);
        $family = self::familyKey($kind);
        $this->loadIndex();

        $id = $this->index[$family][$normalized] ?? $this->aliasIndex[$family.'|'.$normalized] ?? null;

        if ($id !== null) {
            return ['status' => CatalogMatchStatus::Matched, 'item' => CatalogItem::query()->find($id), 'suggested' => null, 'score' => 100];
        }

        [$bestId, $bestScore] = [null, 0];

        foreach ($this->index[$family] ?? [] as $candidate => $candidateId) {
            $score = CatalogNames::similarity($normalized, (string) $candidate);

            if ($score > $bestScore) {
                [$bestId, $bestScore] = [$candidateId, $score];
            }
        }

        if ($bestId !== null && $bestScore >= CatalogNames::SIMILAR_THRESHOLD) {
            return ['status' => CatalogMatchStatus::Similar, 'item' => null, 'suggested' => CatalogItem::query()->find($bestId), 'score' => $bestScore];
        }

        $item = $this->createFromExcel($name, $kind, $context);

        return ['status' => CatalogMatchStatus::New, 'item' => $item, 'suggested' => null, 'score' => null];
    }

    /**
     * Excel'den gelen yeni ad icin kalemi bir kez acar (kategori yoluyla). Saha
     * maliyet listesinden gelen urun / hizmetin kullanim yeri Saha'dir (D-190);
     * `usage` baglamiyla baska yer verilebilir. Birim (D-194, B54): `uom_id`
     * baglami ya da `unit` metni sabit listeden cozulur (UnitResolver);
     * taninmazsa bos kalir, kalem duzenlenirken secilir.
     */
    public function createFromExcel(string $name, CatalogItemKind $kind, array $context = []): CatalogItem
    {
        $category = $this->categories->ensurePath($kind, $context['section'] ?? null, $context['group'] ?? null);

        /** @var CatalogItem $item */
        $item = $this->create([
            'kind' => $kind->value,
            'usage_area' => $kind->hasUsageArea() ? ($context['usage'] ?? UsageArea::Field->value) : null,
            'catalog_category_id' => $category?->getKey(),
            'name' => $name,
            'unit' => $context['unit'] ?? null,
            'uom_id' => $context['uom_id'] ?? null,
            'currency_code' => $context['currency'] ?? null,
            'requires_approval' => true,
        ]);

        return $item;
    }

    /** Bir yazimi kaleme baglar: kalemin kendi adi degilse esleme adi olarak kaydedilir. */
    public function link(CatalogItem $item, string $name): void
    {
        $normalized = CatalogNames::normalize($name);

        if ($normalized === '' || $normalized === (string) $item->getAttribute('normalized_name')) {
            return;
        }

        $this->aliases->create(['catalog_item_id' => $item->getKey(), 'alias' => $name]);

        if ($this->aliasIndex !== null) {
            $this->aliasIndex[self::familyKey($item->kindEnum()).'|'.$normalized] = (int) $item->getKey();
        }
    }

    public function archive(Model|int|string $record): CatalogItem
    {
        return $this->transactions->run(function () use ($record): CatalogItem {
            /** @var CatalogItem $item */
            $item = $this->lockForUpdate($record);

            if ($item->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $item->forceFill(['archived_at' => Carbon::now('UTC')]);
            $this->saveWithoutVersion($item);
            $this->recordActivity($item, 'archived');

            return $item;
        });
    }

    public function restore(Model|int|string $record): CatalogItem
    {
        return $this->transactions->run(function () use ($record): CatalogItem {
            /** @var CatalogItem $item */
            $item = $this->lockForUpdate($record);

            if (! $item->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $item->forceFill(['archived_at' => null]);
            $this->saveWithoutVersion($item);
            $this->recordActivity($item, 'restored');

            return $item;
        });
    }

    /**
     * Aktarimin actigi ama hic kullanilmayan kalemi kaldirir (Excel satiri var
     * olan bir kaleme baglaninca geride kalan cift kayit). Herhangi bir maliyet
     * satiri, siparis kalemi, sirket ici alim ya da esleme adi varsa dokunmaz.
     */
    public function dropUnused(int $itemId): bool
    {
        /** @var CatalogItem|null $item */
        $item = CatalogItem::query()->find($itemId);

        if (! $item instanceof CatalogItem
            || $item->costLines()->exists()
            || $item->needs()->exists()
            || $item->companyPurchases()->exists()
            || $item->aliases()->exists()
            // D-192 (B53): satin alma teklifi ve siparis kalemlerinde kullanilan kalem kalir.
            || (SchemaReadiness::hasBatch('B53') && (
                PurchaseQuotationLine::query()->where('catalog_item_id', $itemId)->exists()
                || PurchaseOrderLine::query()->where('catalog_item_id', $itemId)->exists()
            ))) {
            return false;
        }

        $this->forgetIndex();

        return $this->delete($item);
    }

    /** Bir sonraki aktarim katalogu yeniden okusun. */
    public function forgetIndex(): void
    {
        $this->index = null;
        $this->aliasIndex = null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        $values = array_intersect_key($data, array_flip(self::FIELDS));
        $kind = self::kind($values['kind'] ?? ($record instanceof CatalogItem ? $record->kindEnum() : null));

        if (array_key_exists('name', $values)) {
            $values['name'] = mb_substr(trim((string) $values['name']), 0, 300);
            $values['normalized_name'] = CatalogNames::normalize($values['name']);
        }

        $normalized = $values['normalized_name'] ?? ($record?->getAttribute('normalized_name'));

        if ($normalized !== null) {
            $duplicate = CatalogItem::query()
                ->whereIn('kind', array_map(static fn (CatalogItemKind $case): string => $case->value, $kind->family()))
                ->where('normalized_name', $normalized)
                ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
                ->exists();

            if ($duplicate) {
                throw CatalogItemExistsException::make(['name' => (string) ($values['name'] ?? $record?->getAttribute('name'))]);
            }
        }

        foreach (['unit', 'brand', 'specification', 'currency_code'] as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = filled($values[$field]) ? trim((string) $values[$field]) : null;
            }
        }

        // D-194 (B54): secilen birim ya da metinden cozulen birim; `unit` metni birimin adi.
        // B54 oncesinde `uom_id` yazilmaz, metin oldugu gibi kalir.
        $values = $this->units->apply($values);

        // Kullanim yeri yalniz urun / hizmette (D-190); B52A uygulanmadan kolon yok.
        if (! SchemaReadiness::hasBatch('B52A')) {
            unset($values['usage_area']);
        } elseif (array_key_exists('usage_area', $values) || array_key_exists('kind', $values)) {
            $area = $values['usage_area'] ?? ($record?->getAttribute('usage_area'));
            $area = $area instanceof BackedEnum ? (string) $area->value : (filled($area) ? (string) $area : null);
            $values['usage_area'] = $kind->hasUsageArea() && $area !== null && UsageArea::tryFrom($area) !== null ? $area : null;
        }

        if (array_key_exists('renewal_months', $values)) {
            $values['renewal_months'] = filled($values['renewal_months']) && (int) $values['renewal_months'] > 0 ? (int) $values['renewal_months'] : null;
        }

        if ($record === null) {
            $values['code'] = blank($values['code'] ?? null)
                ? $this->codes->unique((string) ($values['name'] ?? 'KALEM'), CatalogItem::class, 'KALEM', [], 64)
                : $values['code'];
            $values['kind'] = $kind->value;
            $values['requires_approval'] = (bool) ($values['requires_approval'] ?? true);
        } else {
            unset($values['code']);
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        $kind = $record->getAttribute('kind');

        return ['ad' => $record->getAttribute('name'), 'tur' => $kind instanceof BackedEnum ? $kind->value : $kind];
    }

    private function loadIndex(): void
    {
        if ($this->index !== null && $this->aliasIndex !== null) {
            return;
        }

        $this->index = [];
        $this->aliasIndex = [];

        foreach (CatalogItem::query()->notArchived()->get(['id', 'kind', 'normalized_name']) as $item) {
            /** @var CatalogItem $item */
            $this->index[self::familyKey($item->kindEnum())][(string) $item->getAttribute('normalized_name')] = (int) $item->getKey();
        }

        foreach (CatalogItemAlias::query()->with('item:id,kind')->get() as $alias) {
            /** @var CatalogItemAlias $alias */
            $item = $alias->item;

            if ($item instanceof CatalogItem) {
                $this->aliasIndex[self::familyKey($item->kindEnum()).'|'.$alias->getAttribute('normalized_alias')] = (int) $item->getKey();
            }
        }
    }

    private function remember(Model $item): void
    {
        if ($this->index === null || ! $item instanceof CatalogItem) {
            return;
        }

        $this->index[self::familyKey($item->kindEnum())][(string) $item->getAttribute('normalized_name')] = (int) $item->getKey();
    }

    private static function familyKey(CatalogItemKind $kind): string
    {
        return $kind->family()[0]->value;
    }

    private static function kind(mixed $value): CatalogItemKind
    {
        if ($value instanceof CatalogItemKind) {
            return $value;
        }

        return CatalogItemKind::tryFrom($value instanceof BackedEnum ? (string) $value->value : (string) $value) ?? CatalogItemKind::Product;
    }
}
