<?php

declare(strict_types=1);

namespace App\Query\Personnel;

use App\Models\Personnel\Competency;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\PersonnelCompetency;
use App\Models\Personnel\Position;

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
