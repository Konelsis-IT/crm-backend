<?php

declare(strict_types=1);

namespace App\Services\SocialMedia;

use App\Enums\Shared\ActiveStatus;
use App\Exceptions\RecordNotFoundException;
use App\Models\Personnel\Position;
use App\Models\SocialMedia\SocialResponsiblePosition;
use App\Query\SocialMedia\SocialResponsibilityQueries;
use App\Services\AbstractService;
use App\Services\Audit\ActivityInput;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;

/**
 * Sosyal medyadan sorumlu gorevler servisi (B31, D-106, A5).
 *
 * Sorumlu personel gorev (pozisyon) ile belirlenir. sync() secimi TAM liste
 * olarak alir ve satir SILMEZ: secilen pozisyonun satiri yoksa acilir, pasifse
 * aktife alinir; secimden cikan aktif satir pasife alinir. Sorumluluk
 * sorgulari yalniz aktif satirlara baktigi icin yetki, rol matrisi yeniden
 * yazilmadan kendiliginden guncellenir.
 *
 * Esitleme tek bir hareket kaydi uretir
 * (`social_responsible_position.synced`, eklenen / cikarilan gorev adlari);
 * degisiklik yoksa kayit yazilmaz. Islem sonunda istek icindeki sorumluluk
 * bellegi bosaltilir.
 */
final class SocialResponsiblePositionService extends AbstractService
{
    protected string $model = SocialResponsiblePosition::class;

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly SocialResponsibilityQueries $responsibility,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * Sorumlu gorevleri verilen listeye esitler.
     *
     * @param  array<int, mixed>  $positionIds  Secili pozisyon kimlikleri (tam liste).
     * @return array{added: list<string>, removed: list<string>} Eklenen ve cikarilan gorev adlari.
     */
    public function sync(array $positionIds): array
    {
        $selected = $this->cleanIds($positionIds);

        $summary = $this->transactions->run(function () use ($selected): array {
            $positions = $selected === []
                ? collect()
                : Position::query()->with('orgUnit')->whereIn('id', $selected)->get()->keyBy(fn (Position $position): int => (int) $position->getKey());

            if ($positions->count() !== count($selected)) {
                throw RecordNotFoundException::make();
            }

            $rows = SocialResponsiblePosition::query()
                ->with('position.orgUnit')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (SocialResponsiblePosition $row): int => (int) $row->position_id);

            $added = [];
            $removed = [];

            foreach ($selected as $positionId) {
                /** @var SocialResponsiblePosition|null $row */
                $row = $rows->get($positionId);

                if ($row === null) {
                    $row = new SocialResponsiblePosition;
                    $row->fill(['position_id' => $positionId, 'status' => ActiveStatus::Active->value])->save();
                    $added[] = $this->positionLabel($positions->get($positionId));

                    continue;
                }

                if (! $row->isActive()) {
                    $row->fill(['status' => ActiveStatus::Active->value])->save();
                    $added[] = $this->positionLabel($positions->get($positionId));
                }
            }

            foreach ($rows as $positionId => $row) {
                if ($row->isActive() && ! in_array((int) $positionId, $selected, true)) {
                    $row->fill(['status' => ActiveStatus::Inactive->value])->save();
                    $removed[] = $this->positionLabel($row->position);
                }
            }

            if ($added !== [] || $removed !== []) {
                $this->activities->record(new ActivityInput(
                    subjectType: $this->subjectType(),
                    subjectId: 0,
                    actionCode: $this->subjectType().'.synced',
                    changes: array_filter([
                        'eklenen' => implode(', ', $added),
                        'cikarilan' => implode(', ', $removed),
                    ], fn (string $value): bool => $value !== ''),
                ));
            }

            return ['added' => $added, 'removed' => $removed];
        });

        $this->responsibility->flush();

        return $summary;
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    private function cleanIds(array $ids): array
    {
        $clean = [];

        foreach ($ids as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $clean[(int) $id] = (int) $id;
            }
        }

        return array_values($clean);
    }

    /** "Gorev - Departman" etiketi (uzun tire ile); kod ya da kimlik yazilmaz. */
    private function positionLabel(?Position $position): string
    {
        if ($position === null) {
            return '';
        }

        $title = trim((string) $position->title);
        $unit = trim((string) ($position->orgUnit?->name ?? ''));

        return $unit === '' ? $title : $title.' — '.$unit;
    }
}
