<?php

declare(strict_types=1);

namespace App\Services\SocialMedia;

use App\Enums\Shared\ActiveStatus;
use App\Exceptions\DuplicateRecordException;
use App\Models\SocialMedia\SocialProfile;
use App\Models\SocialMedia\SocialSpecialDay;
use App\Services\AbstractService;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Ozel gun servisi (B31, D-106).
 *
 * Ozel gun ay + gun ile tanimlanir; `year` bos ise her yil tekrarlar,
 * `profile_id` bos ise butun hesaplara gecerlidir. Ayni (ad, ay, gun, yil,
 * hesap) ikinci kez girilemez (A7) -> DuplicateRecordException. Ozel gun
 * silinmez, pasife alinir; bu yuzden delete() cagrilmaz.
 *
 * Takvimde olmayan tarih (31 Subat gibi) denetleyicide dogrulanir; buradaki
 * kontrol cagiran hatasina karsi son emniyettir.
 */
final class SocialSpecialDayService extends AbstractService
{
    protected string $model = SocialSpecialDay::class;

    protected string $orderBy = 'month';

    /** Her yil tekrarlayan gunlerde 29 Subat'i da kabul eden artik yil. */
    private const LEAP_YEAR = 2024;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $data = $this->normalize($data, null);

        return $this->transactions->run(function () use ($data): Model {
            $this->assertUnique($data, null);

            return parent::create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        return $this->transactions->run(function () use ($record, $data): Model {
            /** @var SocialSpecialDay $day */
            $day = $this->lockForUpdate($record);
            $data = $this->normalize($data, $day);

            $this->assertUnique($data + [
                'name' => $day->name,
                'month' => $day->month,
                'day' => $day->day,
                'year' => $day->year,
                'profile_id' => $day->profile_id,
            ], (int) $day->getKey());

            $day->fill($this->prepare($data, $day));
            $changes = $this->saveWithoutVersion($day);

            if ($changes !== []) {
                $this->recordActivity($day, 'updated', $this->labelled($changes));
            }

            return $day;
        });
    }

    /** Verilen ay / gun / yil takvimde var mi (yil bos ise artik yil kabul edilir)? */
    public static function isValidDate(int $month, int $day, ?int $year): bool
    {
        return checkdate($month, $day, $year ?? self::LEAP_YEAR);
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        return array_filter([
            'name' => $record->getAttribute('name'),
            'month' => $record->getAttribute('month'),
            'day' => $record->getAttribute('day'),
            'year' => $record->getAttribute('year'),
            'profile_id' => $this->display('profile_id', $record->getAttribute('profile_id')),
            'status' => $this->display('status', $record->getAttribute('status')),
        ], fn ($value): bool => $value !== null);
    }

    /**
     * Gelen veriyi kolonlara indirger. Guncellemede gonderilmeyen alan oldugu
     * gibi kalir; `year`, `profile_id` ve `note` anahtari gonderilip bos
     * birakilirsa deger temizlenir.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, ?SocialSpecialDay $record): array
    {
        $clean = [];

        if (array_key_exists('name', $data) || $record === null) {
            $clean['name'] = trim((string) ($data['name'] ?? ''));
        }

        foreach (['month', 'day'] as $key) {
            if (array_key_exists($key, $data) || $record === null) {
                $clean[$key] = (int) ($data[$key] ?? 0);
            }
        }

        foreach (['year', 'profile_id'] as $key) {
            if (array_key_exists($key, $data)) {
                $clean[$key] = filled($data[$key]) ? (int) $data[$key] : null;
            } elseif ($record === null) {
                $clean[$key] = null;
            }
        }

        if (array_key_exists('note', $data)) {
            $note = trim((string) ($data['note'] ?? ''));
            $clean['note'] = $note === '' ? null : mb_substr($note, 0, 300);
        }

        if (filled($data['status'] ?? null)) {
            $status = $data['status'] instanceof ActiveStatus ? $data['status'] : ActiveStatus::tryFrom((string) $data['status']);

            if ($status !== null) {
                $clean['status'] = $status->value;
            }
        } elseif ($record === null) {
            $clean['status'] = ActiveStatus::Active->value;
        }

        $month = (int) ($clean['month'] ?? $record?->month ?? 0);
        $day = (int) ($clean['day'] ?? $record?->day ?? 0);
        $year = array_key_exists('year', $clean) ? $clean['year'] : $record?->year;

        if (! self::isValidDate($month, $day, $year === null ? null : (int) $year)) {
            throw new InvalidArgumentException('Ozel gun tarihi takvimde yok; denetleyici dogrulamasi atlanmis.');
        }

        return $clean;
    }

    /**
     * Ayni ad + ay + gun + yil + hesap ikinci kez girilemez (bos yil ve bos
     * hesap da esitlik sayilir).
     *
     * @param  array<string, mixed>  $data
     */
    private function assertUnique(array $data, ?int $exceptId): void
    {
        $year = $data['year'] ?? null;
        $profileId = $data['profile_id'] ?? null;

        $exists = SocialSpecialDay::query()
            ->where('name', (string) ($data['name'] ?? ''))
            ->where('month', (int) ($data['month'] ?? 0))
            ->where('day', (int) ($data['day'] ?? 0))
            ->when($year === null, fn ($query) => $query->whereNull('year'), fn ($query) => $query->where('year', (int) $year))
            ->when($profileId === null, fn ($query) => $query->whereNull('profile_id'), fn ($query) => $query->where('profile_id', (int) $profileId))
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($exists) {
            throw DuplicateRecordException::make();
        }
    }

    /**
     * @param  array<string, array{onceki: mixed, yeni: mixed}>  $changes
     * @return array<string, array{onceki: mixed, yeni: mixed}>
     */
    private function labelled(array $changes): array
    {
        foreach ($changes as $column => $pair) {
            $changes[$column] = [
                'onceki' => $this->display((string) $column, $pair['onceki'] ?? null),
                'yeni' => $this->display((string) $column, $pair['yeni'] ?? null),
            ];
        }

        return $changes;
    }

    /** Hareket kaydinda gosterilecek deger: durum etiketle, hesap adiyla yazilir (kimlik yazilmaz). */
    private function display(string $column, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($column === 'status') {
            $status = $value instanceof ActiveStatus ? $value : ActiveStatus::tryFrom((string) $value);

            return $status?->getLabel() ?? (string) $value;
        }

        if ($column === 'profile_id') {
            return (string) (SocialProfile::query()->whereKey((int) $value)->value('name') ?? '');
        }

        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
