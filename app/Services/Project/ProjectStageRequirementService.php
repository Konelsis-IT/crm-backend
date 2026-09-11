<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Models\Project\ProjectStageRequirement;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Gate gereksinimi servisi (11 SS2.7). Snapshot alanlari devirde donar;
 * yalniz uygulanabilirlik, sahip, termin ve sonuc notu duzenlenir. Durum
 * kanit/muafiyet/inceleme servisleriyle degisir.
 */
final class ProjectStageRequirementService extends AbstractService
{
    protected string $model = ProjectStageRequirement::class;

    protected string $orderBy = 'requirement_code_snapshot';

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        return parent::update($record, array_intersect_key($data, array_flip([
            'applicability', 'owner_personnel_id', 'due_at', 'outcome_note', 'row_version',
        ])));
    }
}
