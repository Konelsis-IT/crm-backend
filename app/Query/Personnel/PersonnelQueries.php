<?php

declare(strict_types=1);

namespace App\Query\Personnel;

use App\Enums\Personnel\PersonnelStatus;
use App\Models\Personnel\Competency;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\PersonnelCompetency;
use App\Models\Personnel\Position;
use App\Models\Personnel\PositionAssignment;
use App\Support\DisplayTime;
use Illuminate\Database\Eloquent\Builder;

/**
 * Personel ekranlarinin okuma sorgulari.
 */
final class PersonnelQueries
{
    /** @var array<int, string>|null Istek ici birim adlari (iki satirli secimler). */
    private static ?array $orgUnitNames = null;

    /**
     * Yetkinlik secim listesi.
     *
     * @return array<int, string>
     */
    public function competencyOptions(): array
    {
        return Competency::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Genel personel secim listesi (sorumlu, gozden geciren, kabul eden vb. alanlar icin).
     *
     * @return array<int, string>
     */
    public function personnelOptions(): array
    {
        return Personnel::query()
            ->orderBy('full_name')
            ->pluck('full_name', 'id')
            ->all();
    }

    /**
     * Yalniz aktif personel (D-175: proje tipi koordinatoru secimi).
     *
     * @return array<int, string>
     */
    public function activePersonnelOptions(): array
    {
        return Personnel::query()
            ->where('status', PersonnelStatus::Active->value)
            ->orderBy('full_name')
            ->pluck('full_name', 'id')
            ->all();
    }

    /**
     * Iki satirli personel secimi (D-192: "Personel ismi / Departman ismi
     * (aciklama gibi silik)"): kimlik => [ad soyad, departman]. Gizli hesap
     * kapsami (HideSystemAccountScope) korunur. Arama ad ya da departman
     * adinda yapilir (duz metin; HTML etiketleri hic aranmaz).
     *
     * @param  list<int>  $excluding
     * @return array<int, array{0: string, 1: string|null}>
     */
    public function personnelRows(bool $activeOnly = false, array $excluding = [], string $search = '', ?int $limit = null): array
    {
        $query = Personnel::query()->orderBy('full_name');

        if ($activeOnly) {
            $query->where('status', PersonnelStatus::Active->value);
        }

        if ($excluding !== []) {
            $query->whereKeyNot($excluding);
        }

        $needle = trim($search);

        if ($needle !== '') {
            $query->where(fn (Builder $match): Builder => $match
                ->where('full_name', 'like', '%'.$needle.'%')
                ->orWhereHas('orgUnit', fn (Builder $unit): Builder => $unit->where('name', 'like', '%'.$needle.'%')));
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $this->rowsOf($query->get(['id', 'full_name', 'org_unit_id']));
    }

    /**
     * Verilen kisilerin iki satirli etiketleri (secili deger; pasif kisi de).
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, array{0: string, 1: string|null}>
     */
    public function personnelRowsFor(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return [];
        }

        return $this->rowsOf(Personnel::query()->whereKey($ids)->get(['id', 'full_name', 'org_unit_id']));
    }

    /**
     * Hazir bir [kimlik => ad] personel listesine departman satirini ekler
     * (ozel suzulmus listeler: rapor konusu, onayci...). Sira ve adlar korunur.
     *
     * @param  array<int|string, string>  $options
     * @return array<int, array{0: string, 1: string|null}>
     */
    public function withDepartments(array $options): array
    {
        $units = [];

        foreach (Personnel::query()->whereKey(array_map('intval', array_keys($options)))->get(['id', 'org_unit_id']) as $person) {
            $units[(int) $person->getKey()] = $this->orgUnitName($person->getAttribute('org_unit_id'));
        }

        $rows = [];

        foreach ($options as $id => $name) {
            $rows[(int) $id] = [(string) $name, $units[(int) $id] ?? null];
        }

        return $rows;
    }

    /** Birim (departman) adi; istek boyunca tek sorguyla onbellekte. */
    public function orgUnitName(mixed $orgUnitId): ?string
    {
        if (blank($orgUnitId)) {
            return null;
        }

        self::$orgUnitNames ??= OrgUnit::query()->pluck('name', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();

        $name = self::$orgUnitNames[(int) $orgUnitId] ?? null;

        return filled($name) ? $name : null;
    }

    /**
     * @param  iterable<Personnel>  $people
     * @return array<int, array{0: string, 1: string|null}>
     */
    private function rowsOf(iterable $people): array
    {
        $rows = [];

        foreach ($people as $person) {
            $rows[(int) $person->getKey()] = [(string) $person->getAttribute('full_name'), $this->orgUnitName($person->getAttribute('org_unit_id'))];
        }

        return $rows;
    }

    /**
     * Pozisyon secim listesi (onay adimi onaycisi "pozisyon" ise).
     *
     * @return array<int, string>
     */
    public function positionOptions(): array
    {
        $options = [];

        foreach (Position::query()->orderBy('title')->get(['id', 'code', 'title']) as $position) {
            $options[(int) $position->id] = trim((string) $position->code.' · '.(string) $position->title, ' ·');
        }

        return $options;
    }

    /**
     * Dogrudan amir secim listesi; verilen id kendisiyle secilemesin diye disarida birakilir.
     *
     * @return array<int, string>
     */
    public function managerOptions(?int $excludingId = null): array
    {
        return Personnel::query()
            ->when($excludingId !== null, fn ($query) => $query->whereKeyNot($excludingId))
            ->orderBy('full_name')
            ->pluck('full_name', 'id')
            ->all();
    }

    /** Personelin ad soyadi (sihirbaz ozet karti icin); personel yoksa null. */
    public function name(int $id): ?string
    {
        $name = Personnel::query()->whereKey($id)->value('full_name');

        return $name === null ? null : (string) $name;
    }

    /**
     * Ust cubuk profil alanindaki ikinci satir (D-145): kisinin gecerli asil
     * pozisyonunun adi; pozisyonu yoksa is unvani, o da yoksa null.
     */
    public function menuTitle(Personnel $personnel): ?string
    {
        $today = DisplayTime::today()->format('Y-m-d');

        $title = PositionAssignment::query()
            ->join('positions', 'positions.id', '=', 'position_assignments.position_id')
            ->where('position_assignments.personnel_id', (int) $personnel->getKey())
            ->where(fn (Builder $query) => $query->whereNull('position_assignments.valid_from')->orWhere('position_assignments.valid_from', '<=', $today))
            ->where(fn (Builder $query) => $query->whereNull('position_assignments.valid_until')->orWhere('position_assignments.valid_until', '>=', $today))
            ->orderByDesc('position_assignments.is_primary')
            ->orderBy('position_assignments.id')
            ->value('positions.title');

        $title = filled($title) ? (string) $title : (string) $personnel->job_title;

        return trim($title) !== '' ? trim($title) : null;
    }

    /**
     * Personelin kayitli yetkinlikleri; formda tekrarlanan alan icin.
     *
     * @return array<int, array{competency_id: int, level: string, note: ?string}>
     */
    public function competencyRows(Personnel $personnel): array
    {
        return PersonnelCompetency::query()
            ->where('personnel_id', $personnel->getKey())
            ->orderBy('competency_id')
            ->get()
            ->map(fn (PersonnelCompetency $row): array => [
                'competency_id' => (int) $row->competency_id,
                'level' => $row->level->value,
                'note' => $row->note,
            ])
            ->all();
    }
}
