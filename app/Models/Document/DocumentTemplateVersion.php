<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Enums\Document\TemplateVersionStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\DocumentTemplateVersionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir sablonun dile ozel surumu (TR/EN ayri satirlardir, fallback yoktur).
 */
#[Table('document_template_versions')]
#[Fillable([
    'document_template_id', 'version_no', 'locale', 'view_key', 'layout_config', 'required_field_keys',
    'status', 'content_hash', 'published_by_personnel_id', 'published_at',
])]
#[UsePolicy(DocumentTemplateVersionPolicy::class)]
class DocumentTemplateVersion extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'layout_config' => 'array',
            'required_field_keys' => 'array',
            'status' => TemplateVersionStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'published_by_personnel_id');
    }
}
