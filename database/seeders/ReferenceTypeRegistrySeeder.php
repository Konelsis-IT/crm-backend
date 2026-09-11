<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Activity\ReferenceUsageContext;
use App\Enums\Activity\RegistryStatus;
use App\Models\Activity\ReferenceType;
use App\Models\Activity\ReferenceTypeUsage;
use Illuminate\Database\Seeder;

/**
 * Personel Hareketleri kayitlarinda kullanilabilecek kayit turleri.
 * Sonraki moduller kendi turlerini ekler.
 */
class ReferenceTypeRegistrySeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            ['personnel', 'personnel', 'activity.subjects.personnel', 'personnel'],
            ['role', 'roles', 'activity.subjects.role', 'personnel'],
            ['org_unit', 'org_units', 'activity.subjects.org_unit', 'personnel'],
            ['position', 'positions', 'activity.subjects.position', 'personnel'],
            ['position_assignment', 'position_assignments', 'activity.subjects.position_assignment', 'personnel'],
            ['competency', 'competencies', 'activity.subjects.competency', 'personnel'],
            ['certification', 'certifications', 'activity.subjects.certification', 'personnel'],
            ['personnel_certification', 'personnel_certifications', 'activity.subjects.personnel_certification', 'personnel'],
            ['training', 'trainings', 'activity.subjects.training', 'personnel'],
            ['training_attendance', 'training_attendances', 'activity.subjects.training_attendance', 'personnel'],
            ['file_object', 'file_objects', 'activity.subjects.file_object', 'document'],
            ['document_type', 'document_types', 'activity.subjects.document_type', 'document'],
            ['document', 'documents', 'activity.subjects.document', 'document'],
            ['document_revision', 'document_revisions', 'activity.subjects.document_revision', 'document'],
            ['document_revision_file', 'document_revision_files', 'activity.subjects.document_revision_file', 'document'],
            ['document_link', 'document_links', 'activity.subjects.document_link', 'document'],
            ['document_review', 'document_reviews', 'activity.subjects.document_review', 'document'],
            ['document_distribution', 'document_distributions', 'activity.subjects.document_distribution', 'document'],
            ['document_acknowledgement', 'document_acknowledgements', 'activity.subjects.document_acknowledgement', 'document'],
            ['transmittal', 'transmittals', 'activity.subjects.transmittal', 'document'],
            ['transmittal_item', 'transmittal_items', 'activity.subjects.transmittal_item', 'document'],
            ['document_template', 'document_templates', 'activity.subjects.document_template', 'document'],
            ['document_template_version', 'document_template_versions', 'activity.subjects.document_template_version', 'document'],
            ['generated_output', 'generated_outputs', 'activity.subjects.generated_output', 'document'],
            ['legal_hold', 'legal_holds', 'activity.subjects.legal_hold', 'document'],
            ['legal_hold_document', 'legal_hold_documents', 'activity.subjects.legal_hold_document', 'document'],
            ['document_share', 'document_shares', 'activity.subjects.document_share', 'document'],
            ['approval_policy', 'approval_policies', 'activity.subjects.approval_policy', 'approval'],
            ['approval_policy_version', 'approval_policy_versions', 'activity.subjects.approval_policy_version', 'approval'],
            ['approval_step', 'approval_steps', 'activity.subjects.approval_step', 'approval'],
            ['approval_request', 'approval_requests', 'activity.subjects.approval_request', 'approval'],
            ['approval_request_step', 'approval_request_steps', 'activity.subjects.approval_request_step', 'approval'],
            ['approval_decision', 'approval_decisions', 'activity.subjects.approval_decision', 'approval'],
            ['delegation', 'delegations', 'activity.subjects.delegation', 'approval'],
            ['delegation_snapshot', 'delegation_snapshots', 'activity.subjects.delegation_snapshot', 'approval'],
            ['party', 'parties', 'activity.subjects.party', 'party'],
            ['party_role', 'party_roles', 'activity.subjects.party_role', 'party'],
            ['organization_profile', 'organization_profiles', 'activity.subjects.organization_profile', 'party'],
            ['person_profile', 'person_profiles', 'activity.subjects.person_profile', 'party'],
            ['address', 'addresses', 'activity.subjects.address', 'party'],
            ['communication_point', 'communication_points', 'activity.subjects.communication_point', 'party'],
            ['contact_relationship', 'contact_relationships', 'activity.subjects.contact_relationship', 'party'],
            ['party_license', 'party_licenses', 'activity.subjects.party_license', 'party'],
            ['party_certificate', 'party_certificates', 'activity.subjects.party_certificate', 'party'],
            ['party_annual_review', 'party_annual_reviews', 'activity.subjects.party_annual_review', 'party'],
            ['business_case', 'business_cases', 'activity.subjects.business_case', 'acquisition'],
            ['business_code', 'business_codes', 'activity.subjects.business_code', 'acquisition'],
            ['opportunity', 'opportunities', 'activity.subjects.opportunity', 'acquisition'],
            ['opportunity_stage_history', 'opportunity_stage_histories', 'activity.subjects.opportunity_stage_history', 'acquisition'],
            ['business_development_activity', 'business_development_activities', 'activity.subjects.business_development_activity', 'acquisition'],
            ['bd_activity_participant', 'business_development_activity_participants', 'activity.subjects.bd_activity_participant', 'acquisition'],
            ['tender_source', 'tender_sources', 'activity.subjects.tender_source', 'acquisition'],
            ['tender_notice', 'tender_notices', 'activity.subjects.tender_notice', 'acquisition'],
            ['tender_notice_version', 'tender_notice_versions', 'activity.subjects.tender_notice_version', 'acquisition'],
            ['tender_requirement', 'tender_requirements', 'activity.subjects.tender_requirement', 'acquisition'],
            ['tender_deadline', 'tender_deadlines', 'activity.subjects.tender_deadline', 'acquisition'],
            ['proposal', 'proposals', 'activity.subjects.proposal', 'acquisition'],
            ['proposal_version', 'proposal_versions', 'activity.subjects.proposal_version', 'acquisition'],
            ['proposal_document', 'proposal_documents', 'activity.subjects.proposal_document', 'acquisition'],
            ['compliance_item', 'compliance_items', 'activity.subjects.compliance_item', 'acquisition'],
            ['deviation', 'deviations', 'activity.subjects.deviation', 'acquisition'],
            ['brand_item', 'brand_items', 'activity.subjects.brand_item', 'acquisition'],
            ['responsibility_matrix_item', 'responsibility_matrix_items', 'activity.subjects.responsibility_matrix_item', 'acquisition'],
            ['estimate_version', 'estimate_versions', 'activity.subjects.estimate_version', 'acquisition'],
            ['estimate_line', 'estimate_lines', 'activity.subjects.estimate_line', 'acquisition'],
            ['pricing_scenario', 'pricing_scenarios', 'activity.subjects.pricing_scenario', 'acquisition'],
            ['boq_item', 'boq_items', 'activity.subjects.boq_item', 'acquisition'],
            ['contract', 'contracts', 'activity.subjects.contract', 'acquisition'],
            ['contract_version', 'contract_versions', 'activity.subjects.contract_version', 'acquisition'],
            ['contract_party', 'contract_parties', 'activity.subjects.contract_party', 'acquisition'],
            ['contract_document', 'contract_documents', 'activity.subjects.contract_document', 'acquisition'],
            ['contract_obligation', 'contract_obligations', 'activity.subjects.contract_obligation', 'acquisition'],
            ['contract_milestone', 'contract_milestones', 'activity.subjects.contract_milestone', 'acquisition'],
            ['operation_handoff', 'operation_handoffs', 'activity.subjects.operation_handoff', 'acquisition'],
            ['operation_handoff_version', 'operation_handoff_versions', 'activity.subjects.operation_handoff_version', 'acquisition'],
            ['handoff_item', 'handoff_items', 'activity.subjects.handoff_item', 'acquisition'],
            ['handoff_review', 'handoff_reviews', 'activity.subjects.handoff_review', 'acquisition'],
            ['component_definition', 'component_definitions', 'activity.subjects.component_definition', 'project'],
            ['operation_group_definition', 'operation_group_definitions', 'activity.subjects.operation_group_definition', 'project'],
            ['stage_template', 'stage_templates', 'activity.subjects.stage_template', 'project'],
            ['stage_template_version', 'stage_template_versions', 'activity.subjects.stage_template_version', 'project'],
            ['stage_node', 'stage_nodes', 'activity.subjects.stage_node', 'project'],
            ['stage_dependency', 'stage_dependencies', 'activity.subjects.stage_dependency', 'project'],
            ['stage_requirement_definition', 'stage_requirement_definitions', 'activity.subjects.stage_requirement_definition', 'project'],
            ['project', 'projects', 'activity.subjects.project', 'project'],
            ['project_component', 'project_components', 'activity.subjects.project_component', 'project'],
            ['project_workstream', 'project_workstreams', 'activity.subjects.project_workstream', 'project'],
            ['workstream_dependency', 'workstream_dependencies', 'activity.subjects.workstream_dependency', 'project'],
            ['project_focus_history', 'project_focus_histories', 'activity.subjects.project_focus_history', 'project'],
            ['wbs_node', 'wbs_nodes', 'activity.subjects.wbs_node', 'project'],
            ['cbs_node', 'cbs_nodes', 'activity.subjects.cbs_node', 'project'],
            ['wbs_cbs_mapping', 'wbs_cbs_mappings', 'activity.subjects.wbs_cbs_mapping', 'project'],
            ['work_package', 'work_packages', 'activity.subjects.work_package', 'project'],
            ['work_package_dependency', 'work_package_dependencies', 'activity.subjects.work_package_dependency', 'project'],
            ['project_stage_instance', 'project_stage_instances', 'activity.subjects.project_stage_instance', 'project'],
            ['project_stage_requirement', 'project_stage_requirements', 'activity.subjects.project_stage_requirement', 'project'],
            ['stage_evidence', 'stage_evidence', 'activity.subjects.stage_evidence', 'project'],
            ['stage_review', 'stage_reviews', 'activity.subjects.stage_review', 'project'],
            ['stage_waiver', 'stage_waivers', 'activity.subjects.stage_waiver', 'project'],
            ['department_handoff', 'department_handoffs', 'activity.subjects.department_handoff', 'project'],
            ['department_handoff_version', 'department_handoff_versions', 'activity.subjects.department_handoff_version', 'project'],
            ['department_handoff_item', 'department_handoff_items', 'activity.subjects.department_handoff_item', 'project'],
            ['department_handoff_review', 'department_handoff_reviews', 'activity.subjects.department_handoff_review', 'project'],
            ['schedule_baseline', 'schedule_baselines', 'activity.subjects.schedule_baseline', 'project'],
            ['milestone', 'milestones', 'activity.subjects.milestone', 'project'],
            ['progress_snapshot', 'progress_snapshots', 'activity.subjects.progress_snapshot', 'project'],
            ['project_issue', 'project_issues', 'activity.subjects.project_issue', 'project'],
            ['project_photo', 'project_photos', 'activity.subjects.project_photo', 'project'],
            ['project_supply_item', 'project_supply_items', 'activity.subjects.project_supply_item', 'project'],
            ['project_team_member', 'project_team_members', 'activity.subjects.project_team_member', 'project'],
            ['focus_expectation', 'focus_expectations', 'activity.subjects.focus_expectation', 'project'],
            ['project_risk', 'project_risks', 'activity.subjects.project_risk', 'project'],
            ['delay_event', 'delay_events', 'activity.subjects.delay_event', 'project'],
            ['recovery_action', 'recovery_actions', 'activity.subjects.recovery_action', 'project'],
            ['project_change', 'project_changes', 'activity.subjects.project_change', 'project'],
            ['commercial_clarification', 'commercial_clarifications', 'activity.subjects.commercial_clarification', 'project'],
            ['commercial_exposure', 'commercial_exposures', 'activity.subjects.commercial_exposure', 'project'],
            ['project_decision', 'project_decisions', 'activity.subjects.project_decision', 'project'],
            ['announcement', 'announcements', 'activity.subjects.announcement', 'notification'],
            ['business_alert', 'business_alerts', 'activity.subjects.business_alert', 'notification'],
            ['conversation', 'conversations', 'activity.subjects.conversation', 'communication'],
            ['message', 'messages', 'activity.subjects.message', 'communication'],
            ['work_request', 'work_requests', 'activity.subjects.work_request', 'notification'],
        ];

        foreach ($entries as [$targetType, $tableName, $labelKey, $domain]) {
            $referenceType = ReferenceType::query()->updateOrCreate(
                ['target_type' => $targetType],
                [
                    'table_name' => $tableName,
                    'label_key' => $labelKey,
                    'owning_domain' => $domain,
                    'status' => RegistryStatus::Active,
                ],
            );

            ReferenceTypeUsage::query()->firstOrCreate([
                'reference_type_id' => $referenceType->id,
                'usage_context' => ReferenceUsageContext::Activity,
            ]);
        }
    }
}
