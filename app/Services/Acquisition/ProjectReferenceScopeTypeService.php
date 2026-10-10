<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\ProjectScopeType;
use App\Models\Acquisition\ProjectReferenceScopeType;
use App\Services\AbstractService;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Referansin proje tipi satirlari (B50, D-177). Yalniz ProjectReferenceService
 * kullanir: referans formundaki coklu secim eksik tipi ekler, kaldirilani siler
 * (alt detay satiri; referansin kendisi silinmez, arsive alinir).
 */
final class ProjectReferenceScopeTypeService extends AbstractService
{
    protected string $model = ProjectReferenceScopeType::class;

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        $type = $record->getAttribute('scope_type');
        $value = $type instanceof BackedEnum ? (string) $type->value : (string) $type;

        return [
            'proje_tipi' => ProjectScopeType::tryFrom($value)?->getLabel() ?? $value,
            'project_reference_id' => $record->getAttribute('project_reference_id'),
        ];
    }
}
