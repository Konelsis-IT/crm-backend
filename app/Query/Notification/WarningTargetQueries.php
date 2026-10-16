<?php

declare(strict_types=1);

namespace App\Query\Notification;

use App\Enums\Approval\RequestStepStatus;
use App\Filament\Support\RecordLinks;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\TenderNotice;
use App\Models\Approval\ApprovalRequest;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Report\WorkItem;
use App\Query\Personnel\OrganizationQueries;
use App\Services\Notification\WarningTarget;
use App\Support\Dashboard\Departments;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Acil uyarisinin hedef kaydi ve varsayilan alicilari (D-208, kullanici 14
 * Ekim 2026: "acil koduyla ilgili is icin ilgili baglantili olan personel ve
 * departmanlara bildirim gonderebilir").
 *
 * - Is: isin kisisi ve isin departmani.
 * - Satin alma talebi / satin alinacak / siparis: talep eden ya da siparisi
 *   veren ve Satin Alma departmani.
 * - Ihale / potansiyel is: is sahibi, teklif sorumlusu; Is Gelistirme (ihale
 *   icin ayrica Teklif).
 * - Teklif: teklif sorumlusu ve Teklif departmani.
 * - Onay talebi: aktif adimdaki onaycilar.
 */
final class WarningTargetQueries
{
    /** Uyari gonderilebilen kayit turleri. */
    public const TYPES = ['work_item', 'purchase_request', 'purchase_need', 'purchase_order', 'tender_notice', 'business_case', 'proposal', 'approval_request'];

    public function __construct(private readonly OrganizationQueries $organization) {}

    /** Kaydi bulur; yoksa ya da tur desteklenmiyorsa null. */
    public function record(string $type, int $id): ?Model
    {
        return match ($type) {
            'work_item' => WorkItem::query()->with('personnel')->find($id),
            'purchase_request' => PurchaseRequest::query()->find($id),
            'purchase_need' => PurchaseNeed::query()->find($id),
            'purchase_order' => PurchaseOrder::query()->find($id),
            'tender_notice' => TenderNotice::query()->with('businessCase')->find($id),
            'business_case' => BusinessCase::query()->find($id),
            'proposal' => Proposal::query()->find($id),
            'approval_request' => ApprovalRequest::query()->find($id),
            default => null,
        };
    }

    public function target(string $type, Model $record): WarningTarget
    {
        [$title, $personnel, $units] = match (true) {
            $record instanceof WorkItem => [
                (string) $record->title,
                [$record->personnel_id],
                [$record->org_unit_id ?? $record->personnel?->org_unit_id],
            ],
            $record instanceof PurchaseRequest => [
                (string) $record->getAttribute('request_no'),
                [$record->getAttribute('requested_by_personnel_id')],
                [$this->unit(Departments::PURCHASING)],
            ],
            $record instanceof PurchaseNeed => [
                (string) $record->getAttribute('title'),
                [$record->getAttribute('requested_by_personnel_id')],
                [$this->unit(Departments::PURCHASING)],
            ],
            $record instanceof PurchaseOrder => [
                trim($record->getAttribute('order_no').' '.$record->supplierLabel()),
                [$record->getAttribute('ordered_by_personnel_id')],
                [$this->unit(Departments::PURCHASING)],
            ],
            $record instanceof TenderNotice => [
                (string) $record->title,
                [$record->businessCase?->owner_employee_id, $record->businessCase?->proposal_owner_employee_id],
                [$this->unit(Departments::BUSINESS_DEVELOPMENT), $this->unit(Departments::PROPOSALS)],
            ],
            $record instanceof BusinessCase => [
                (string) $record->title,
                [$record->owner_employee_id, $record->proposal_owner_employee_id],
                [$this->unit(Departments::BUSINESS_DEVELOPMENT)],
            ],
            $record instanceof Proposal => [
                (string) ($record->title ?? $record->proposal_no),
                [$record->owner_employee_id],
                [$this->unit(Departments::PROPOSALS)],
            ],
            $record instanceof ApprovalRequest => [
                (string) $record->subject_label,
                $record->steps()->where('status', RequestStepStatus::Active->value)->pluck('personnel_id')->all(),
                [],
            ],
            default => ['-', [], []],
        };

        return new WarningTarget(
            subjectType: $type,
            subjectId: (int) $record->getKey(),
            title: $title !== '' ? $title : '-',
            url: RecordLinks::detailUrl($record, checkRecord: false),
            personnelIds: $this->ids($personnel),
            unitIds: $this->ids($units),
        );
    }

    /**
     * Alicilar: secilen etkin personel ve secilen departmanlarin etkin personeli;
     * gonderen dusulur.
     *
     * @param  list<int>  $personnelIds
     * @param  list<int>  $unitIds
     * @return Collection<int, Personnel>
     */
    public function recipients(array $personnelIds, array $unitIds, ?int $exceptId): Collection
    {
        if ($personnelIds === [] && $unitIds === []) {
            return new Collection;
        }

        return Personnel::query()
            ->where('status', 'active')
            ->where(function ($query) use ($personnelIds, $unitIds): void {
                $query->whereIn('id', $personnelIds === [] ? [0] : $personnelIds)
                    // D-218: departmanda ek gorevi olanlar da uyedir.
                    ->orWhere(fn ($members) => $this->organization->inUnits($members, $unitIds));
            })
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Pencerede gosterilecek adlar: personel ve departmanlar.
     *
     * @param  list<int>  $personnelIds
     * @param  list<int>  $unitIds
     * @return array{personnel: list<string>, units: list<string>}
     */
    public function names(array $personnelIds, array $unitIds): array
    {
        return [
            'personnel' => $personnelIds === [] ? [] : Personnel::query()->whereIn('id', $personnelIds)->orderBy('full_name')->pluck('full_name')->map(fn ($name): string => (string) $name)->all(),
            'units' => $unitIds === [] ? [] : OrgUnit::query()->whereIn('id', $unitIds)->orderBy('name')->pluck('name')->map(fn ($name): string => (string) $name)->all(),
        ];
    }

    /** Departmanin etkin personel sayisi. */
    public function memberCount(int $unitId): int
    {
        return $this->organization->inUnits(Personnel::query()->where('status', 'active'), [$unitId])->count();
    }

    private function unit(string $code): ?int
    {
        $unit = $this->organization->unitByCode($code);

        return $unit instanceof OrgUnit ? (int) $unit->getKey() : null;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<int>
     */
    private function ids(array $values): array
    {
        return array_values(array_unique(array_map('intval', array_filter($values, static fn ($value): bool => $value !== null && (int) $value > 0))));
    }
}
