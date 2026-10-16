<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Enums\Procurement\QuotationDocumentAiStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\DocumentRevision;
use App\Policies\PurchaseQuotationDocumentPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satin alma teklifine yuklenen belge (D-192, B53): tedarikcinin PDF / Word /
 * Excel / fotograf teklifi. Dosya Dokuman yonetiminde (B06) tutulur; burada
 * revizyon baglantisi ve Konelsis AI'nin bu belge icin sonucu (teklif mi,
 * kisa ozet) durur. Satir degismez kayit gibidir (yalniz created_*); AI
 * sonucu servis tarafindan bir kez yazilir.
 */
#[Table('purchase_quotation_documents')]
#[Fillable(['purchase_quotation_id', 'document_revision_id', 'ai_status', 'ai_summary'])]
#[UsePolicy(PurchaseQuotationDocumentPolicy::class)]
class PurchaseQuotationDocument extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ai_status' => QuotationDocumentAiStatus::class,
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuotation::class, 'purchase_quotation_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'document_revision_id');
    }

    public function aiStatusEnum(): ?QuotationDocumentAiStatus
    {
        $status = $this->getAttribute('ai_status');

        return $status instanceof QuotationDocumentAiStatus ? $status : QuotationDocumentAiStatus::tryFrom((string) $status);
    }
}
