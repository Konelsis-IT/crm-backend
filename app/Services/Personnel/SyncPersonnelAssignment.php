<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\Personnel;
use App\Models\Personnel\PersonnelAssignment;
use Illuminate\Support\Carbon;

/**
 * Organizasyon birimi veya gorev degistiginde tarihceli bir kayit
 * acar/kapatir. Ekranda ayri bir form yoktur; bu yalniz
 * PersonnelService::update() tarafindan cagrilir. Dogrudan amir gecmisi
 * burada degil SyncPersonnelManager/ReportingRelationship'te tutulur.
 */
final class SyncPersonnelAssignment
{
    /** @var list<string> */
    private const WATCHED_FIELDS = ['org_unit_id', 'job_title'];

    public function syncIfChanged(Personnel $personnel, Personnel $before): void
    {
        $changed = false;

        foreach (self::WATCHED_FIELDS as $field) {
            if ($before->getAttribute($field) != $personnel->getAttribute($field)) {
                $changed = true;

                break;
            }
        }

        if (! $changed) {
            return;
        }

        $today = Carbon::now('UTC')->toDateString();

        $open = PersonnelAssignment::query()
            ->where('personnel_id', $personnel->getKey())
            ->whereNull('effective_to')
            ->first();

        // Ayni gun icinde ikinci degisiklik: acik satiri yerinde guncelle,
        // sifir gunluk gecmis satiri olusturma.
        if ($open !== null && $open->effective_from->toDateString() === $today) {
            $open->forceFill($this->snapshot($personnel))->save();

            return;
        }

        if ($open !== null) {
            $open->forceFill(['effective_to' => $today])->save();
        } elseif ($this->hadAnyAssignment($before)) {
            // Ilk degisiklik: onceki durumu kapali bir gecmis satiri olarak sakla.
            PersonnelAssignment::query()->create([
                ...$this->snapshot($before),
                'personnel_id' => $personnel->getKey(),
                'effective_from' => optional($personnel->hired_on)->toDateString() ?? $personnel->created_at->toDateString(),
                'effective_to' => $today,
            ]);
        }

        PersonnelAssignment::query()->create([
            ...$this->snapshot($personnel),
            'personnel_id' => $personnel->getKey(),
            'effective_from' => $today,
            'effective_to' => null,
        ]);
    }

    private function hadAnyAssignment(Personnel $personnel): bool
    {
        foreach (self::WATCHED_FIELDS as $field) {
            if ($personnel->getAttribute($field) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Personnel $personnel): array
    {
        return [
            'org_unit_id' => $personnel->org_unit_id,
            'job_title' => $personnel->job_title,
        ];
    }
}
