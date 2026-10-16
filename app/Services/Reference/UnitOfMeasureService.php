<?php

declare(strict_types=1);

namespace App\Services\Reference;

use App\Enums\Reference\UomDimension;
use App\Enums\Shared\ActiveStatus;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\Procurement\UnitDimensionLockedException;
use App\Exceptions\Procurement\UnitExistsException;
use App\Models\Reference\UnitOfMeasure;
use App\Query\Reference\UnitQueries;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Support\Procurement\UnitResolver;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Ayarlar > Birimler (D-194; kullanici 12 Ekim 2026: "birimler sabit olacak.
 * Istersen onu da ayarlara ekleyelim").
 *
 * - Kullanici yalniz Ad, Simge ve Tur girer. Kod simgeden uretilir (ASCII,
 *   ayni turde benzersiz; "m²" -> m2, "gün" -> gun) ve degismez (D-130);
 *   Ingilizce ad Turkce addan doldurulur (D-131).
 * - Ayni ad (Turkce harf farki gozetilmez) ya da ayni turde ayni simge ikinci
 *   kez eklenemez (UnitExistsException).
 * - Taban birim donusumu olan birimin (Metre / Milimetre gibi) turu degismez.
 * - Silme yok: pasife alinan birim secim listelerinden kalkar, var olan
 *   kayitlar onu gostermeye devam eder; tekrar aktif yapilabilir.
 */
final class UnitOfMeasureService extends AbstractService
{
    protected string $model = UnitOfMeasure::class;

    protected string $orderBy = 'name_tr';

    /** Kayitlardaki birim metni (`unit`) 40 karakterdir; ad ona sigar. */
    public const NAME_LENGTH = 40;

    public const SYMBOL_LENGTH = 32;

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly UnitQueries $queries,
        private readonly UnitResolver $resolver,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    public function create(array $data): Model
    {
        $unit = parent::create($data);
        $this->resolver->flush();

        return $unit;
    }

    public function update(Model|int|string $record, array $data): Model
    {
        $unit = parent::update($record, $data);
        $this->resolver->flush();

        return $unit;
    }

    /** Pasife al: secim listelerinden kalkar, kayitlar gostermeye devam eder. */
    public function deactivate(Model|int|string $record): UnitOfMeasure
    {
        return $this->changeStatus($record, ActiveStatus::Inactive, 'deactivated');
    }

    public function activate(Model|int|string $record): UnitOfMeasure
    {
        return $this->changeStatus($record, ActiveStatus::Active, 'activated');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        /** @var UnitOfMeasure|null $record */
        $values = array_intersect_key($data, array_flip(['name_tr', 'symbol', 'dimension']));

        if (array_key_exists('name_tr', $values) || $record === null) {
            $values['name_tr'] = Str::limit(trim((string) ($values['name_tr'] ?? '')), self::NAME_LENGTH, '');
            // D-131: Ingilizce ad arayuzde yoktur; Turkce addan doldurulur.
            $values['name_en'] = $values['name_tr'];
        }

        if (array_key_exists('symbol', $values) || $record === null) {
            $values['symbol'] = Str::limit(trim((string) ($values['symbol'] ?? '')), self::SYMBOL_LENGTH, '');
        }

        if (array_key_exists('dimension', $values) || $record === null) {
            $values['dimension'] = self::dimension($values['dimension'] ?? null)->value;
        }

        $name = (string) ($values['name_tr'] ?? $record?->getAttribute('name_tr'));
        $symbol = (string) ($values['symbol'] ?? $record?->getAttribute('symbol'));
        $dimension = (string) ($values['dimension'] ?? $record?->dimensionEnum()->value);

        if ($name === '' || $symbol === '') {
            throw InvalidTransitionException::make();
        }

        $this->assertUnique($name, $symbol, $dimension, $record);

        if ($record === null) {
            $values['code'] = $this->generateCode($dimension, $symbol, $name);
            $values['status'] = ActiveStatus::Active->value;
        } elseif ($dimension !== $record->dimensionEnum()->value) {
            // Kod degismez (D-130); yeni turde ayni kod ya da donusum bagi varsa tur degismez.
            if ($this->queries->hasConversions($record)) {
                throw UnitDimensionLockedException::make();
            }

            if ($this->queries->codeTaken($dimension, (string) $record->getAttribute('code'), (int) $record->getKey())) {
                throw UnitExistsException::make(['name' => $name, 'symbol' => $symbol]);
            }
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        /** @var UnitOfMeasure $record */
        return [
            'ad' => $record->getAttribute('name_tr'),
            'simge' => $record->getAttribute('symbol'),
            'tur' => $record->dimensionEnum()->value,
        ];
    }

    private function changeStatus(Model|int|string $record, ActiveStatus $to, string $operation): UnitOfMeasure
    {
        $unit = $this->transactions->run(function () use ($record, $to, $operation): UnitOfMeasure {
            /** @var UnitOfMeasure $unit */
            $unit = $this->lockForUpdate($record);

            if (($unit->isActive() ? ActiveStatus::Active : ActiveStatus::Inactive) === $to) {
                throw InvalidTransitionException::make();
            }

            $unit->forceFill(['status' => $to->value]);
            $this->saveWithoutVersion($unit);
            $this->recordActivity($unit, $operation, ['ad' => $unit->getAttribute('name_tr')]);

            return $unit;
        });

        $this->resolver->flush();

        return $unit;
    }

    /** Ayni ad her yerde, ayni simge ayni turde bir kez (Turkce harf farki gozetilmez). */
    private function assertUnique(string $name, string $symbol, string $dimension, ?UnitOfMeasure $record): void
    {
        $foldedName = UnitResolver::fold($name);
        $foldedSymbol = UnitResolver::fold($symbol);
        $selfId = $record !== null ? (int) $record->getKey() : null;

        foreach ($this->queries->all() as $unit) {
            if ($unit['id'] === $selfId) {
                continue;
            }

            $sameName = UnitResolver::fold($unit['name']) === $foldedName;
            $sameSymbol = $unit['dimension'] === $dimension && UnitResolver::fold($unit['symbol']) === $foldedSymbol;

            if ($sameName || $sameSymbol) {
                throw UnitExistsException::make(['name' => $name, 'symbol' => $symbol]);
            }
        }
    }

    /** Simgeden ASCII kod ("m²" -> m2, "gün" -> gun); ayni turde varsa -2, -3 eklenir. */
    private function generateCode(string $dimension, string $symbol, string $name): string
    {
        $ascii = static fn (string $text): string => (string) preg_replace('/[^A-Za-z0-9]+/', '', Str::ascii(strtr($text, ['²' => '2', '³' => '3', 'ı' => 'i', 'İ' => 'I'])));
        $base = $ascii($symbol);
        $base = $base !== '' ? $base : $ascii($name);
        $base = substr($base !== '' ? $base : 'unit', 0, 28);
        $code = $base;
        $suffix = 2;

        while ($this->queries->codeTaken($dimension, $code)) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }

    private static function dimension(mixed $value): UomDimension
    {
        if ($value instanceof UomDimension) {
            return $value;
        }

        return UomDimension::tryFrom($value instanceof BackedEnum ? (string) $value->value : (string) $value) ?? UomDimension::Other;
    }
}
