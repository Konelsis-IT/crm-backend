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
use App\Models\Document\DocumentRevision;
use App\Services\Approval\Subjects\WorkRequestSubject;
use App\Services\Authorization\RoleResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Baslangic onay politikasi (B07): dokuman revizyonu icin tek adimli,
 * maker-checker acik standart onay. Onayci: system_admin rolu (ust
 * yonetim rolu tanimlanana kadar). Sirket kendi politikalarini Ayarlar >
 * Onay Politikalari ekranindan ekler; mevcut kayit uzerine yazilmaz.
 */
class ApprovalPolicySeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('approval_policies')) {
            return;
        }

        $this->seedPolicy('DOC_REVISION_STANDARD', 'Doküman revizyonu — standart onay', 'Document revision — standard approval', DocumentRevision::APPROVAL_SUBJECT_TYPE);

        // Talep onayi (D-84): talep sahibi ya da muhatap talebi onaya gonderir.
        $this->seedPolicy('WORK_REQUEST_STANDARD', 'Talep — standart onay', 'Request — standard approval', WorkRequestSubject::TYPE);
    }

    private function seedPolicy(string $code, string $nameTr, string $nameEn, string $subjectType): void
    {

        /** @var ApprovalPolicy $policy */
        $policy = ApprovalPolicy::query()->firstOrCreate(
            ['code' => $code],
            [
                'name_tr' => $nameTr,
                'name_en' => $nameEn,
                'subject_type' => $subjectType,
                'status' => ApprovalPolicyStatus::Draft,
            ],
        );

        if ($policy->current_version_id !== null) {
            return;
        }

        /** @var ApprovalPolicyVersion $version */
        $version = ApprovalPolicyVersion::query()->firstOrCreate(
            ['approval_policy_id' => $policy->getKey(), 'version_no' => 1],
            [
                'mode' => ApprovalMode::Sequential,
                'requires_maker_checker' => true,
                'reapproval_on_change' => true,
                'sla_minutes' => 2880,
                'change_summary' => 'Başlangıç sürümü (B07).',
                'status' => PolicyVersionStatus::Draft,
            ],
        );

        ApprovalStep::query()->firstOrCreate(
            ['approval_policy_version_id' => $version->getKey(), 'step_code' => 'APPROVE'],
            [
                'name_tr' => 'Onay',
                'name_en' => 'Approval',
                'sequence_no' => 1,
                'resolver_type' => ResolverType::RbacRole,
                'role_code' => RoleResolver::SYSTEM_ADMIN,
                'decision_rule' => DecisionRule::AnyOne,
                'is_optional' => false,
                'allows_delegation' => true,
            ],
        );

        $version->forceFill([
            'status' => PolicyVersionStatus::Published,
            'definition_hash' => hash('sha256', $code.'|1|APPROVE|rbac_role|system_admin'),
            'published_at' => Carbon::now('UTC'),
        ])->save();

        $policy->forceFill([
            'current_version_id' => $version->getKey(),
            'status' => ApprovalPolicyStatus::Active,
        ])->save();
    }
}
