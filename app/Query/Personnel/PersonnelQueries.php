<?php

declare(strict_types=1);

namespace App\Query\Personnel;

use App\Enums\Personnel\PersonnelStatus;
use App\Models\Personnel\Competency;
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
