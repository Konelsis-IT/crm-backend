<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Procurement\CatalogItemKind;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\RecordArchivedException;
use App\Models\Procurement\CatalogCategory;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\CodeGenerator;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Support\Procurement\CatalogNames;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Katalog kategorileri (B52, D-189). Kod kullanicidan alinmaz, addan uretilir
 * (D-130). Excel aktarimi bolum > grup yolunu `ensurePath()` ile bulur ya da
 * bir kez acar. Urun ve Hizmet ayni kategori agacini paylasir (tur product).
 * Silme yok, arsiv var (D-156).
 */
final class CatalogCategoryService extends AbstractService
{
    protected string $orderBy = 'sort_order';

    /** @var list<string> */
    private const FIELDS = ['kind', 'parent_id', 'code', 'name', 'sort_order'];

    /** @var array<string, CatalogCategory> */
    private array $pathCache = [];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly CodeGenerator $codes,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * Bolum (ve varsa grup) kategorisini bulur; yoksa acar.
     */
    public function ensurePath(CatalogItemKind $kind, ?string $section, ?string $group = null): ?CatalogCategory
    {
        $section = trim((string) $section);

        if ($section === '') {
            return null;
        }

        $kind = self::treeKind($kind);
        $parent = $this->ensureChild($kind, null, $section);
        $group = trim((string) $group);

        if ($group === '' || CatalogNames::normalize($group) === CatalogNames::normalize($section)) {
            return $parent;
        }

        return $this->ensureChild($kind, $parent, $group);
    }

    public function archive(Model|int|string $record): CatalogCategory
    {
        return $this->transactions->run(function () use ($record): CatalogCategory {
            /** @var CatalogCategory $category */
            $category = $this->lockForUpdate($record);

            if ($category->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $category->forceFill(['archived_at' => Carbon::now('UTC')]);
            $this->saveWithoutVersion($category);
            $this->recordActivity($category, 'archived');

            return $category;
        });
    }

    public function restore(Model|int|string $record): CatalogCategory
    {
        return $this->transactions->run(function () use ($record): CatalogCategory {
            /** @var CatalogCategory $category */
            $category = $this->lockForUpdate($record);

            if (! $category->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $category->forceFill(['archived_at' => null]);
            $this->saveWithoutVersion($category);
            $this->recordActivity($category, 'restored');

            return $category;
        });
    }

    public function update(Model|int|string $record, array $data): Model
    {
        $current = $this->show($record);

        if ($current instanceof CatalogCategory && $current->isArchived()) {
            throw RecordArchivedException::make();
        }

        return parent::update($record, $data);
    }

    /** Urun ve Hizmet ayni agaci kullanir. */
    public static function treeKind(CatalogItemKind $kind): CatalogItemKind
    {
        return $kind === CatalogItemKind::Service ? CatalogItemKind::Product : $kind;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        $values = array_intersect_key($data, array_flip(self::FIELDS));

        // Urun ve Hizmet ayni agaci kullanir.
        if (array_key_exists('kind', $values)) {
            $kind = CatalogItemKind::tryFrom(self::kindValue($values['kind']));
            $values['kind'] = $kind !== null ? self::treeKind($kind)->value : self::kindValue($values['kind']);
        }

        // Kategori kendi alt dalina baglanamaz (dongu).
        if ($record !== null && filled($values['parent_id'] ?? null)) {
            $cursor = CatalogCategory::query()->find((int) $values['parent_id']);
            $guard = 0;

            while ($cursor instanceof CatalogCategory && $guard++ < 50) {
                if ((int) $cursor->getKey() === (int) $record->getKey()) {
                    throw InvalidTransitionException::make();
                }

                $cursor = $cursor->parent;
            }
        }

        if (array_key_exists('name', $values)) {
            $values['name'] = mb_substr(trim((string) $values['name']), 0, 200);
        }

        // Kod degismez (D-130): yalniz ilk kayitta addan uretilir.
        if ($record === null && blank($values['code'] ?? null)) {
            $values['code'] = $this->codes->unique('KTG '.($values['name'] ?? ''), CatalogCategory::class, 'KTG', [], 64);
        } elseif ($record !== null) {
            unset($values['code']);
        }

        if ($record === null && ! array_key_exists('sort_order', $values)) {
            $values['sort_order'] = (int) CatalogCategory::query()->where('kind', self::kindValue($values['kind'] ?? null))->max('sort_order') + 1;
        }

        return $values;
    }

    private function ensureChild(CatalogItemKind $kind, ?CatalogCategory $parent, string $name): CatalogCategory
    {
        $key = $kind->value.'|'.($parent?->getKey() ?? 0).'|'.CatalogNames::normalize($name);

        if (isset($this->pathCache[$key])) {
            return $this->pathCache[$key];
        }

        $normalized = CatalogNames::normalize($name);
        $existing = CatalogCategory::query()
            ->where('kind', $kind->value)
            ->where('parent_id', $parent?->getKey())
            ->get()
            ->first(fn (CatalogCategory $category): bool => CatalogNames::normalize((string) $category->getAttribute('name')) === $normalized);

        if ($existing instanceof CatalogCategory) {
            return $this->pathCache[$key] = $existing;
        }

        /** @var CatalogCategory $created */
        $created = $this->create(['kind' => $kind->value, 'parent_id' => $parent?->getKey(), 'name' => $name]);

        return $this->pathCache[$key] = $created;
    }

    private static function kindValue(mixed $kind): string
    {
        return $kind instanceof BackedEnum ? (string) $kind->value : (string) $kind;
    }
}
