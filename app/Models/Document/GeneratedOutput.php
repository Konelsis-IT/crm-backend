<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Enums\Document\GeneratedOutputFormat;
use App\Enums\Document\GeneratedOutputStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\GeneratedOutputPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir sablon suruminden uretilen tek bir cikti (PDF/Excel/Word). Yeniden
 * uretim oncekini ezmez, yeni output_no alir. Bu modul henuz bir uretim
 * pipeline'i tetiklemiyor (M05 rapor motoru gelince baglanir); tablo
 * simdiden hazir.
 */
#[Table('generated_outputs')]
#[Fillable([
    'source_type', 'source_id', 'template_version_id', 'locale', 'output_format', 'output_no',
    'idempotency_key', 'personnel_id', 'requested_at', 'status', 'missing_fields_snapshot',
    'file_object_id', 'output_hash', 'completed_at', 'safe_error_code',
])]
#[UsePolicy(GeneratedOutputPolicy::class)]
class GeneratedOutput extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'output_format' => GeneratedOutputFormat::class,
            'output_no' => 'integer',
            'requested_at' => 'datetime',
            'status' => GeneratedOutputStatus::class,
            'missing_fields_snapshot' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplateVersion::class, 'template_version_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function fileObject(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_object_id');
    }
}
