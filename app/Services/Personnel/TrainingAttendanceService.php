<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Exceptions\DuplicateRecordException;
use App\Models\Personnel\TrainingAttendance;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Personelin bir egitime katilim kaydi servisi.
 *
 * create ve update override edilmistir: ayni personel ayni egitime
 * ikinci kez kaydedilemez.
 */
final class TrainingAttendanceService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['training'];

    protected string $orderBy = 'attended_on';

    protected string $orderDirection = 'desc';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $this->assertNotDuplicate(
            (int) ($data['training_id'] ?? 0),
            (int) ($data['personnel_id'] ?? 0),
            null,
        );

        return parent::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        $current = $this->show($record);

        $trainingId = (int) ($data['training_id'] ?? $current->training_id);
        $personnelId = (int) ($data['personnel_id'] ?? $current->personnel_id);

        $this->assertNotDuplicate($trainingId, $personnelId, $current);

        return parent::update($current, $data);
    }

    private function assertNotDuplicate(int $trainingId, int $personnelId, ?Model $except): void
    {
        $query = TrainingAttendance::query()
            ->where('training_id', $trainingId)
            ->where('personnel_id', $personnelId);

        if ($except !== null) {
            $query->whereKeyNot($except->getKey());
        }

        if ($query->exists()) {
            throw DuplicateRecordException::make();
        }
    }
}
