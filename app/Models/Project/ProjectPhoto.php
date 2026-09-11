<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\FileObject;
use App\Policies\ProjectPhotoPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Filament\Facades\Filament;

/** Saha fotografi (11 SS1.10): dosya DMS file_objects uzerinde durur. */
#[Table('project_photos')]
#[Fillable([
    'project_id', 'file_object_id', 'workstream_id', 'caption', 'taken_on', 'sort_order', 'is_cover',
])]
#[UsePolicy(ProjectPhotoPolicy::class)]
class ProjectPhoto extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taken_on' => 'date',
            'sort_order' => 'integer',
            'is_cover' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_object_id');
    }

    public function workstream(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkstream::class, 'workstream_id');
    }

    /**
     * Yetki kontrollu goruntuleme adresi (D-71): panelin dosya ucu
     * ProjectPhotoPolicy::view ile korunur. variant = original|thumbnail.
     */
    public function previewUrl(string $variant = 'original'): ?string
    {
        if ($this->file_object_id === null) {
            return null;
        }

        try {
            return route(Filament::getCurrentOrDefaultPanel()->generateRouteName('files.photo'), [
                'photo' => $this->getKey(),
                'variant' => $variant,
                'disposition' => 'inline',
            ]);
        } catch (\Throwable) {
            return null;
        }
    }
}
