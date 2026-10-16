<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Enums\Procurement\PurchaseNeedStatus;
use App\Enums\Procurement\PurchaseRequestStatus;
use App\Enums\Procurement\UsageArea;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Policies\PurchaseRequestPolicy;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Kalemli satin alma talebi (D-192, B53; kullanici: "Birden fazla urun talebi
 * olabilir. O yuzden kalemli bir yapi olmasi lazim"). Baslik: talep eden,
 * birim, kullanim yeri, proje, termin, acil, gerekce, "var olan neden
 * kullanilamadi". Her kalem bir `purchase_needs` satiridir (Satin
 * alinacaklar'a duser). Kod SAT-YYYY-NNNN. Silinmez, arsive alinir (D-156).
 */
#[Table('purchase_requests')]
#[Fillable([
    'request_no', 'requested_by_personnel_id', 'org_unit_id', 'usage_area', 'project_id', 'needed_by', 'is_urgent',
    'justification', 'existing_check', 'note', 'status',
])]
#[UsePolicy(PurchaseRequestPolicy::class)]
class PurchaseRequest extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseRequestStatus::class,
            'usage_area' => UsageArea::class,
            'needed_by' => 'date',
            'is_urgent' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /** Talebin kalemleri (Satin alinacaklar satirlari), formdaki sirayla. */
    public function needs(): HasMany
    {
        return $this->hasMany(PurchaseNeed::class, 'purchase_request_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Talebin basligi (15 Ekim 2026 kullanici: "talebin basligi eksik ... kod'u
     * 2. satira al baslik 1. satirda olsun"). Talepte ayri baslik alani yok:
     * ilk iki kalemin adi ve kalan kalem sayisi ("Dizustu bilgisayar, Monitor
     * +2 kalem"); kalem yoksa gerekcenin basi, o da yoksa SAT kodu. Iptal
     * edilen kalemler sayilmaz.
     */
    public function displayTitle(int $shown = 2): string
    {
        $titles = $this->needs
            ->reject(static fn (PurchaseNeed $need): bool => $need->getAttribute('status') === PurchaseNeedStatus::Cancelled)
            ->map(static fn (PurchaseNeed $need): string => trim((string) $need->getAttribute('title')))
            ->filter()
            ->values();

        if ($titles->isNotEmpty()) {
            $more = $titles->count() - $shown;
            $head = $titles->take($shown)->implode(', ');

            return $more > 0 ? __('purchase_request.values.title_more', ['items' => $head, 'count' => $more]) : $head;
        }

        $justification = Str::squish((string) $this->getAttribute('justification'));

        return $justification !== '' ? Str::limit($justification, 60) : (string) $this->getAttribute('request_no');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'requested_by_personnel_id');
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function companyPurchases(): HasMany
    {
        return $this->hasMany(CompanyPurchase::class, 'purchase_request_id');
    }

    public function statusEnum(): PurchaseRequestStatus
    {
        $status = $this->getAttribute('status');

        return $status instanceof PurchaseRequestStatus ? $status : (PurchaseRequestStatus::tryFrom((string) $status) ?? PurchaseRequestStatus::Open);
    }

    public function usageEnum(): ?UsageArea
    {
        $area = $this->getAttribute('usage_area');

        return $area instanceof UsageArea ? $area : UsageArea::tryFrom((string) $area);
    }

    public function isArchived(): bool
    {
        return $this->getAttribute('archived_at') !== null;
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return SchemaReadiness::hasBatch('B53') ? $query->whereNull($query->qualifyColumn('archived_at')) : $query;
    }
}
