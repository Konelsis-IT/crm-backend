<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Enums\Document\DocumentTemplateOutputKind;
use App\Enums\Document\DocumentTemplateStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\DocumentTemplatePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Belge/rapor uretim sablonu katalogu.
 */
#[Table('document_templates')]
#[Fillable(['code', 'name', 'output_kind', 'status'])]
#[UsePolicy(DocumentTemplatePolicy::class)]
class DocumentTemplate extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'output_kind' => DocumentTemplateOutputKind::class,
            'status' => DocumentTemplateStatus::class,
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentTemplateVersion::class, 'document_template_id');
    }
}
