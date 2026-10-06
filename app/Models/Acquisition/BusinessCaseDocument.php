<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\Document;
use App\Policies\BusinessCaseDocumentPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Potansiyel isin belgesi (B43, D-155). Kontrol listesi ana maddesine ait
 * belge (or. Cagri mektubu) sablon + madde kodu tasir; genel belgede ikisi
 * bostur. Yeni yukleme ayni belgenin yeni revizyonudur, satir silinmez.
 */
#[Table('business_case_documents')]
#[Fillable(['business_case_id', 'document_id', 'template_code', 'item_code', 'sort_order'])]
#[UsePolicy(BusinessCaseDocumentPolicy::class)]
class BusinessCaseDocument extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function businessCase(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'business_case_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
