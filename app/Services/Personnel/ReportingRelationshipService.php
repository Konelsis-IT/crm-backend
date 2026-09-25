<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Enums\Personnel\ReportingRelationType;
use App\Exceptions\DuplicateRecordException;
use App\Models\Personnel\ReportingRelationship;
use App\Services\AbstractService;
use App\Support\DisplayTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Ek amir iliskileri (islevsel / proje) servisi.
 *
 * 24 Eylul 2026 kullanici karari: bir kisi birden fazla mudure baglidir.
 * Dogrudan amir (line) tektir ve personel formundan degisir
 * (SyncPersonnelManager); ek amirler personel kartindaki Amir gecmisi
 * sekmesinden bu servis ile acilir ve kapatilir.
 */
final class ReportingRelationshipService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['manager'];

    protected string $orderBy = 'valid_from';

    protected string $orderDirection = 'desc';

    /**
     * Ek amir ekler. Dogrudan amir bu yoldan yazilmaz; ayni amir icin
     * acik bir iliski varsa yenisi acilmaz.
     */
    public function addManager(int $personnelId, int $managerId, string $relationType, ?string $validFrom = null): Model
    {
        $type = ReportingRelationType::tryFrom($relationType) ?? ReportingRelationType::Functional;

        if ($type === ReportingRelationType::Line) {
            $type = ReportingRelationType::Functional;
        }

        $this->assertNotOpen($personnelId, $managerId, $type);

        return $this->create([
            'personnel_id' => $personnelId,
            'manager_personnel_id' => $managerId,
            'relation_type' => $type->value,
            'scope_type' => 'all',
            'valid_from' => $validFrom ?? Carbon::now(DisplayTime::zone())->toDateString(),
            'valid_until' => null,
        ]);
    }

    /**
     * Acik bir ek amir iliskisini bugun itibariyla kapatir.
     *
     * ck_reporting_relationships_valid_range bitisin baslangictan buyuk
     * olmasini ister; ayni gun acilip kapatilan iliski hic yururluge
     * girmemistir ve silinir (SyncPersonnelManager ile ayni desen).
     */
    public function close(Model|int|string $record): void
    {
        $row = $this->show($record);
        $today = Carbon::now(DisplayTime::zone())->toDateString();

        if ($row->valid_from->toDateString() >= $today) {
            $this->delete($row);

            return;
        }

        $this->update($row, ['valid_until' => $today]);
    }

    /** Ayni amir icin acik bir iliski varsa ikincisi yazilmaz. */
    private function assertNotOpen(int $personnelId, int $managerId, ReportingRelationType $type): void
    {
        $exists = ReportingRelationship::query()
            ->where('personnel_id', $personnelId)
            ->where('manager_personnel_id', $managerId)
            ->where('relation_type', $type->value)
            ->whereNull('valid_until')
            ->exists();

        if ($exists) {
            throw DuplicateRecordException::make();
        }
    }
}
