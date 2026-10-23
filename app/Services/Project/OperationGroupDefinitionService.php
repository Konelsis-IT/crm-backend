<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Shared\ActiveStatus;
use App\Exceptions\CodeAlreadyInUseException;
use App\Models\Personnel\OrgUnit;
use App\Models\Project\OperationGroupDefinition;
use App\Query\Project\ProjectCatalogQueries;
use App\Services\AbstractService;
use App\Services\Support\CodeGenerator;
use Illuminate\Database\Eloquent\Model;

/**
 * Operasyon grubu katalogu servisi (11 SS1.4, D-22). Kod benzersizdir ve duzenlemede degistirilemez.
 */
final class OperationGroupDefinitionService extends AbstractService
{
    protected string $model = OperationGroupDefinition::class;

    protected string $orderBy = 'code';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        // Kod arayuzde girilmez (D-130): verilmezse Turkce addan uretilir.
        $code = filled($data['code'] ?? null)
            ? strtoupper(trim((string) $data['code']))
            : app(CodeGenerator::class)->unique((string) ($data['name_tr'] ?? ''), OperationGroupDefinition::class, 'GRUP');

        if (OperationGroupDefinition::query()->where('code', $code)->exists()) {
            throw CodeAlreadyInUseException::make(['code' => $code]);
        }

        return parent::create([...$data, 'code' => $code]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        unset($data['code']);

        return parent::update($record, $data);
    }

    /**
     * Departmanin taahhutlerde calismasi (D-239: operasyon grubu = departman,
     * Organizasyon birimleri ekranindan). Acilinca grup departmanin adiyla
     * acilir ya da etkinlesir; kapatilinca pasife alinir (silinmez: taahhutlerin
     * Departmanlar satirlari ona bagli). Ad departmanla ayni tutulur.
     */
    public function syncForUnit(OrgUnit $unit, bool $inProjects, int $sortOrder): ?OperationGroupDefinition
    {
        $group = app(ProjectCatalogQueries::class)->groupForUnit((string) $unit->code);
        $data = [
            'name_tr' => (string) $unit->name,
            'default_sort_order' => max(0, min(255, $sortOrder)),
            'status' => $inProjects ? ActiveStatus::Active->value : ActiveStatus::Inactive->value,
        ];

        if ($group === null) {
            if (! $inProjects) {
                return null;
            }

            /** @var OperationGroupDefinition */
            return $this->create([...$data, 'code' => OperationGroupDefinition::codeForUnit((string) $unit->code)]);
        }

        /** @var OperationGroupDefinition */
        return $this->update($group, $data);
    }
}
