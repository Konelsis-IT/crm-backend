<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ModelNotResolvedException;
use App\Exceptions\RecordNotFoundException;
use App\Services\Audit\ActivityInput;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use FilesystemIterator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Butun servislerin atasi.
 *
 * Bes temel islemi hazir verir: index, show, create, update, delete.
 * Bos bir servis bile bunlarin hepsine sahiptir:
 *
 *     final class PersonnelService extends AbstractService {}
 *
 *     app(PersonnelService::class)->delete(12);
 *
 * Model, servis adindan bulunur (PersonnelService -> Personnel). Ad
 * eslemesi yetmezse $model ozelligi yazilir.
 *
 * Her yazma islemi tek transaction icinde calisir ve Personel Hareketleri
 * kaydi uretir. Ozel bir kural gerektiginde ilgili metot override edilir;
 * geri kalan dort metot buradan gelmeye devam eder.
 */
abstract class AbstractService
{
    /** Servisin uzerinde calistigi model. Bos ise servis adindan bulunur. */
    protected string $model = '';

    /** Hareket kaydindaki kayit turu. Bos ise model adindan uretilir. */
    protected string $subjectType = '';

    /**
     * Okuma islemlerinde birlikte yuklenecek iliskiler.
     *
     * @var list<string>
     */
    protected array $with = [];

    /** Listelemede varsayilan siralama kolonu. */
    protected string $orderBy = 'id';

    protected string $orderDirection = 'asc';

    /** @var array<string, class-string<Model>>|null */
    private static ?array $modelMap = null;

    public function __construct(
        protected readonly TransactionRunner $transactions,
        protected readonly OptimisticLock $lock,
        protected readonly ActivityRecorder $activities,
    ) {}

    /**
     * Kayitlari listeler.
     *
     * @param  array<string, mixed>  $filters  Kolon => deger; dizi verilirse whereIn.
     */
    public function index(array $filters = []): Collection
    {
        $query = $this->query();

        foreach ($filters as $column => $value) {
            is_array($value)
                ? $query->whereIn($column, $value)
                : $query->where($column, $value);
        }

        return $query->orderBy($this->orderBy, $this->orderDirection)->get();
    }

    /** Tek kayit getirir; yoksa is hatasi atar. */
    public function show(Model|int|string $record): Model
    {
        if ($record instanceof Model) {
            return $record;
        }

        $found = $this->query()->whereKey($record)->first();

        if ($found === null) {
            throw RecordNotFoundException::make();
        }

        return $found;
    }

    /**
     * Yeni kayit olusturur.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            $record = $this->newModel()->newInstance();
            $record->fill($this->prepare($data, null));
            $record->save();

            $this->recordActivity($record, 'created', $this->createdChanges($record));

            return $record;
        });
    }

    /**
     * Kaydi gunceller. Veride row_version varsa surum kontrolu uygulanir.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        return $this->transactions->run(function () use ($record, $data): Model {
            $model = $this->lockForUpdate($record);

            $model->fill($this->prepare($data, $model));

            $changes = array_key_exists('row_version', $data)
                ? $this->lock->save($model, (int) $data['row_version'])
                : $this->saveWithoutVersion($model);

            if ($changes !== []) {
                $this->recordActivity($model, 'updated', $changes);
            }

            return $model;
        });
    }

    /** Kaydi siler. */
    public function delete(Model|int|string $record): bool
    {
        return $this->transactions->run(function () use ($record): bool {
            $model = $this->lockForUpdate($record);

            $this->recordActivity($model, 'deleted', $this->createdChanges($model));

            return (bool) $model->delete();
        });
    }

    /** Servisin modeli icin taze bir sorgu. */
    protected function query(): Builder
    {
        $query = $this->newModel()->newQuery();

        return $this->with === [] ? $query : $query->with($this->with);
    }

    /** Servisin model ornegi. */
    protected function newModel(): Model
    {
        $class = $this->modelClass();

        return new $class;
    }

    /** @return class-string<Model> */
    protected function modelClass(): string
    {
        if ($this->model !== '') {
            return $this->model;
        }

        $guess = Str::beforeLast(class_basename(static::class), 'Service');
        $resolved = self::modelMap()[$guess] ?? null;

        if ($resolved === null) {
            throw ModelNotResolvedException::make(['service' => static::class]);
        }

        return $resolved;
    }

    /** Hareket kaydindaki kayit turu. */
    protected function subjectType(): string
    {
        return $this->subjectType !== ''
            ? $this->subjectType
            : Str::snake(class_basename($this->modelClass()));
    }

    /**
     * Kaydetmeden once veriyi duzenleme noktasi. Alt servisler burayi
     * override ederek alan donusumu veya kural kontrolu ekler.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        unset($data['row_version']);

        return $data;
    }

    /**
     * Olusturma ve silme kaydinda hareket ozeti olarak yazilacak alanlar.
     *
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        $summary = [];

        foreach (['code', 'name', 'full_name', 'email', 'status'] as $attribute) {
            $value = $record->getAttribute($attribute);

            if ($value !== null) {
                $summary[$attribute] = $value instanceof \BackedEnum ? $value->value : $value;
            }
        }

        return $summary;
    }

    /**
     * Hareket kaydi yazar. Islem kodu "{kayit_turu}.{islem}" bicimindedir.
     *
     * @param  array<string, mixed>  $changes
     */
    protected function recordActivity(Model $record, string $operation, array $changes = []): void
    {
        $this->activities->record(new ActivityInput(
            subjectType: $this->subjectType(),
            subjectId: (int) $record->getKey(),
            actionCode: $this->subjectType().'.'.$operation,
            changes: $changes === [] ? null : $changes,
        ));
    }

    /** Kaydi satir kilidiyle okur. */
    protected function lockForUpdate(Model|int|string $record): Model
    {
        $key = $record instanceof Model ? $record->getKey() : $record;
        $model = $this->newModel()->newQuery()->lockForUpdate()->whereKey($key)->first();

        if ($model === null) {
            throw RecordNotFoundException::make();
        }

        return $model;
    }

    /**
     * row_version olmayan tablolarda basit kaydetme; degisiklik ozetini doner.
     *
     * @return array<string, array{onceki: mixed, yeni: mixed}>
     */
    protected function saveWithoutVersion(Model $model): array
    {
        $dirty = $model->getDirty();
        $changes = [];

        foreach (array_keys($dirty) as $attribute) {
            $changes[$attribute] = [
                'onceki' => $model->getOriginal($attribute),
                'yeni' => $model->getAttribute($attribute),
            ];
        }

        if ($changes !== []) {
            $model->save();
        }

        return $changes;
    }

    /**
     * app/Models altindaki siniflari bir kez tarar: kisa ad => tam sinif adi.
     *
     * @return array<string, class-string<Model>>
     */
    private static function modelMap(): array
    {
        if (self::$modelMap !== null) {
            return self::$modelMap;
        }

        $map = [];
        $root = app_path('Models');

        if (! is_dir($root)) {
            return self::$modelMap = $map;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = Str::after($file->getPathname(), $root.DIRECTORY_SEPARATOR);
            $class = 'App\\Models\\'.str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                $relative,
            );

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $map[class_basename($class)] = $class;
        }

        return self::$modelMap = $map;
    }
}
