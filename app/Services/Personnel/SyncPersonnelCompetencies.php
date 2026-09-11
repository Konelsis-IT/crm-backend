<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\Personnel;
use App\Models\Personnel\PersonnelCompetency;

/**
 * Personelin yetkinlik listesini formdan gelen sete esitler.
 */
final class SyncPersonnelCompetencies
{
    /**
     * @param  array<int, array{competency_id: int|string, level: string, note: ?string}>  $rows
     * @return array<string, mixed> Hareket kaydi icin degisiklik ozeti.
     */
    public function sync(Personnel $personnel, array $rows): array
    {
        $existing = PersonnelCompetency::query()
            ->where('personnel_id', $personnel->getKey())
            ->get()
            ->keyBy(fn (PersonnelCompetency $record): int => (int) $record->competency_id);

        $seen = [];
        $added = [];
        $updated = [];

        foreach ($rows as $row) {
            $competencyId = (int) $row['competency_id'];

            if ($competencyId === 0) {
                continue;
            }

            $seen[] = $competencyId;
            $record = $existing->get($competencyId);

            if ($record === null) {
                PersonnelCompetency::query()->create([
                    'personnel_id' => $personnel->getKey(),
                    'competency_id' => $competencyId,
                    'level' => $row['level'],
                    'note' => $row['note'] ?? null,
                ]);
                $added[] = $competencyId;

                continue;
            }

            $record->fill(['level' => $row['level'], 'note' => $row['note'] ?? null]);

            if ($record->isDirty()) {
                $record->save();
                $updated[] = $competencyId;
            }
        }

        $removed = $existing->keys()->diff($seen)->values()->all();

        if ($removed !== []) {
            PersonnelCompetency::query()
                ->where('personnel_id', $personnel->getKey())
                ->whereIn('competency_id', $removed)
                ->delete();
        }

        $summary = [];

        if ($added !== []) {
            $summary['eklenen'] = count($added);
        }

        if ($updated !== []) {
            $summary['guncellenen'] = count($updated);
        }

        if ($removed !== []) {
            $summary['cikarilan'] = count($removed);
        }

        return $summary;
    }
}
