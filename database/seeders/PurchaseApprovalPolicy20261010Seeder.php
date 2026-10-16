<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Approval\ApprovalMode;
use App\Enums\Approval\ApprovalPolicyStatus;
use App\Enums\Approval\DecisionRule;
use App\Enums\Approval\PolicyVersionStatus;
use App\Enums\Approval\ResolverType;
use App\Models\Approval\ApprovalPolicy;
use App\Models\Approval\ApprovalPolicyVersion;
use App\Models\Approval\ApprovalStep;
use App\Models\Personnel\Position;
use App\Services\Approval\Subjects\PurchaseNeedSubject;
use Database\Seeders\Support\ProtectedSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Satin alma siparis onayi, 10.10.2026 (D-189; kullanici: "bazi tutarlara
 * kadar onay yetkisi Satin alma ekibinde, daha buyuk tutarlar Yonetim Kurulu
 * Baskaninda" ve "4000 TL'ye kadar onaylayabilir").
 *
 * - PURCHASE_SA_LIMIT: 0 - 4.000 TL, Satin Alma > Satin alma muduru (SAM2)
 *   onaylar. Maker-checker kapali: mudur kendi actigi siparisi yetkisi
 *   dahilinde onaylar (PurchaseApprovals tek adimda onay talebi acmaz).
 * - PURCHASE_YKB_LIMIT: 4.000,01 TL ve ustu, Yonetim > Yonetim kurulu baskani
 *   (YKB) onaylar.
 *
 * Tutarlar ve onaycilar Ayarlar > Onay Politikalari'ndan degistirilir. Yalniz
 * ekler (D-165): politika varsa dokunulmaz; gorev bulunamazsa satir sonraki
 * kurulumda yeniden denenir.
 */
final class PurchaseApprovalPolicy20261010Seeder extends ProtectedSeeder
{
    public const LIMIT_TRY = 4000.0;

    public function run(): void
    {
        if (! Schema::hasTable('approval_policies') || ! Schema::hasTable('purchase_needs')) {
            $this->command?->warn('B07 / B52 uygulanmamis; satin alma onay politikalari atlandi.');

            return;
        }

        $this->row('policy:PURCHASE_SA_LIMIT', fn (): ?ApprovalPolicy => $this->policy(
            'PURCHASE_SA_LIMIT',
            'Satın alma siparişi — 4.000 ₺\'ye kadar',
            'Purchase order — up to 4,000 ₺',
            0.0,
            self::LIMIT_TRY,
            'SATIN_ALMA',
            'SAM2',
            'Satın alma müdürü onayı',
            'Purchasing manager approval',
            false,
        ));

        $this->row('policy:PURCHASE_YKB_LIMIT', fn (): ?ApprovalPolicy => $this->policy(
            'PURCHASE_YKB_LIMIT',
            'Satın alma siparişi — 4.000 ₺ üstü',
            'Purchase order — above 4,000 ₺',
            self::LIMIT_TRY + 0.01,
            null,
            'YONETIM',
            'YKB',
            'Yönetim kurulu başkanı onayı',
            'Chairman approval',
            true,
        ));
    }

    private function policy(
        string $code,
        string $nameTr,
        string $nameEn,
        float $min,
        ?float $max,
        string $unitCode,
        string $positionCode,
        string $stepTr,
        string $stepEn,
        bool $makerChecker,
    ): ?ApprovalPolicy {
        if (ApprovalPolicy::query()->where('code', $code)->exists()) {
            return ApprovalPolicy::query()->where('code', $code)->first();
        }

        /** @var Position|null $position */
        $position = Position::query()
            ->where('code', $positionCode)
            ->whereHas('orgUnit', fn (Builder $unit): Builder => $unit->where('code', $unitCode))
            ->first();

        if ($position === null) {
            $this->command?->warn(sprintf('%s: %s / %s gorevi bulunamadi; politika sonraki kurulumda yeniden denenecek.', $code, $unitCode, $positionCode));

            return null;
        }

        /** @var ApprovalPolicy $policy */
        $policy = ApprovalPolicy::query()->create([
            'code' => $code,
            'name_tr' => $nameTr,
            'name_en' => $nameEn,
            'subject_type' => PurchaseNeedSubject::TYPE,
            'status' => ApprovalPolicyStatus::Draft,
        ]);

        /** @var ApprovalPolicyVersion $version */
        $version = ApprovalPolicyVersion::query()->create([
            'approval_policy_id' => $policy->getKey(),
            'version_no' => 1,
            'mode' => ApprovalMode::Sequential,
            'requires_maker_checker' => $makerChecker,
            'reapproval_on_change' => true,
            'applies_min_amount' => $min,
            'applies_max_amount' => $max,
            'currency_code' => 'TRY',
            'sla_minutes' => 2880,
            'change_summary' => 'Başlangıç sürümü (D-189, 4.000 ₺ sınırı).',
            'status' => PolicyVersionStatus::Draft,
        ]);

        ApprovalStep::query()->create([
            'approval_policy_version_id' => $version->getKey(),
            'step_code' => 'APPROVE',
            'name_tr' => $stepTr,
            'name_en' => $stepEn,
            'sequence_no' => 1,
            'resolver_type' => ResolverType::Position,
            'resolver_target_id' => $position->getKey(),
            'decision_rule' => DecisionRule::AnyOne,
            'is_optional' => false,
            'allows_delegation' => true,
        ]);

        $version->forceFill([
            'status' => PolicyVersionStatus::Published,
            'definition_hash' => hash('sha256', $code.'|1|APPROVE|position|'.$position->getKey().'|'.$min.'|'.(string) $max),
            'published_at' => Carbon::now('UTC'),
        ])->save();

        $policy->forceFill([
            'current_version_id' => $version->getKey(),
            'status' => ApprovalPolicyStatus::Active,
        ])->save();

        return $policy;
    }
}
