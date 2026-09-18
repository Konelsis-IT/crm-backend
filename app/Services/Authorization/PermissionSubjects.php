<?php

declare(strict_types=1);

namespace App\Services\Authorization;

/**
 * Alt kayitlarin yetkisini ana kayittan devralmasi (D-93, 16 Eylul 2026).
 *
 * Filament Shield izinleri yalniz kaynak (Resource) basina uretilir; taraf
 * rolleri, adresler, dokuman revizyonlari, proje sorunlari gibi ALT tablolarin
 * kendi izin anahtari yoktur. Bu tablolar ekranlarda ana kaydin altinda
 * iliski listesi olarak gorunur ve mantiken ana kayitla ayni yetkiye tabidir:
 * bir tarafi gorebilen onun adresini de gorur, duzenleyebilen adresini de
 * duzenler.
 *
 * Eskiden bu alt tablolar yalniz "tam yetkili" kullaniciya aciliyordu
 * (`system_admin` kisayolu); gercek rol yapisina gecince (D-90) sira disi
 * kullanicilarda iliski listeleri bos kaldi. Artik politika once kendi izin
 * anahtarina, bulamazsa buradaki ANA KONU anahtarina bakar.
 *
 * Yeni bir alt tablo eklerken buraya bir satir yazmak yeterlidir; ana konusu
 * olmayanlar (sohbet, duyuru gibi kendi kurali olanlar) listede yer almaz.
 */
final class PermissionSubjects
{
    /**
     * Alt konu => ana konu (Shield izin anahtarindaki ad).
     *
     * @var array<string, string>
     */
    private const PARENTS = [
        // Taraf (yatirimci / is veren) alt kayitlari
        'Address' => 'Party',
        'CommunicationPoint' => 'Party',
        'ContactRelationship' => 'Party',
        'OrganizationProfile' => 'Party',
        'PersonProfile' => 'Party',
        'PartyRole' => 'Party',
        'PartyLicense' => 'Party',
        'PartyCertificate' => 'Party',
        'PartyAnnualReview' => 'Party',
        'PartyMeetingNote' => 'Party',

        // Dokuman yonetimi
        'DocumentRevision' => 'Document',
        'DocumentRevisionFile' => 'Document',
        'DocumentReview' => 'Document',
        'DocumentDistribution' => 'Document',
        'DocumentAcknowledgement' => 'Document',
        'DocumentLink' => 'Document',
        'DocumentShare' => 'Document',
        'FileObject' => 'Document',
        'GeneratedOutput' => 'Document',
        'DocumentTemplateVersion' => 'DocumentTemplate',
        'LegalHoldDocument' => 'LegalHold',
        'TransmittalItem' => 'Transmittal',

        // Proje
        'ProjectComponent' => 'Project',
        'ProjectIssue' => 'Project',
        'ProjectRisk' => 'Project',
        'ProjectPhoto' => 'Project',
        'ProjectChange' => 'Project',
        'ProjectDecision' => 'Project',
        'ProjectFocusHistory' => 'Project',
        'ProjectTeamMember' => 'Project',
        'Milestone' => 'Project',
        'ProgressSnapshot' => 'Project',
        'ScheduleBaseline' => 'Project',
        'RecoveryAction' => 'Project',
        'CommercialClarification' => 'Project',
        'CommercialExposure' => 'Project',
        'CbsNode' => 'Project',
        'WbsCbsMapping' => 'WbsNode',
        'WorkPackageDependency' => 'WorkPackage',
        'WorkstreamDependency' => 'ProjectWorkstream',
        'ProjectStageRequirement' => 'ProjectStageInstance',
        'StageEvidence' => 'ProjectStageInstance',
        'StageReview' => 'ProjectStageInstance',
        'StageWaiver' => 'ProjectStageInstance',
        'StageDependency' => 'StageNode',
        'StageRequirementDefinition' => 'StageNode',
        'DepartmentHandoffItem' => 'DepartmentHandoff',
        'DepartmentHandoffReview' => 'DepartmentHandoff',

        // Is dosyasi ve ticari surec
        'BusinessCode' => 'BusinessCase',
        'Opportunity' => 'BusinessCase',
        'OpportunityStageHistory' => 'BusinessCase',
        'BusinessDevelopmentActivity' => 'BusinessCase',
        'BusinessDevelopmentActivityParticipant' => 'BusinessCase',
        'BusinessCaseScope' => 'BusinessCase',
        'ProposalDocument' => 'Proposal',
        'ComplianceItem' => 'Proposal',
        'Deviation' => 'Proposal',
        'BrandItem' => 'Proposal',
        'ResponsibilityMatrixItem' => 'Proposal',
        'PricingScenario' => 'Proposal',
        'BoqItem' => 'Proposal',
        'EstimateLine' => 'EstimateVersion',
        'ContractDocument' => 'Contract',
        'ContractParty' => 'Contract',
        'ContractObligation' => 'Contract',
        'ContractMilestone' => 'Contract',
        'TenderRequirement' => 'TenderNotice',
        'TenderDeadline' => 'TenderNotice',
        'HandoffItem' => 'OperationHandoff',
        'HandoffReview' => 'OperationHandoff',

        // Onay motoru
        'ApprovalDecision' => 'ApprovalRequest',
        'ApprovalRequestStep' => 'ApprovalRequest',
        'ApprovalStep' => 'ApprovalPolicy',
        'DelegationSnapshot' => 'Delegation',

        // Personel ve organizasyon
        'PersonnelActivity' => 'Personnel',
        'PersonnelAssignment' => 'Personnel',
        'PersonnelCertification' => 'Personnel',
        'ReportingRelationship' => 'Personnel',
        'PositionAssignment' => 'Position',
        'OrgUnitRelation' => 'OrgUnit',
        'TrainingAttendance' => 'Training',

        // Sosyal medya (B31, D-106): modulun tek izin konusu icerik kaydidir
        'SocialComment' => 'SocialContent',
        'SocialReaction' => 'SocialContent',
        'SocialContentMedia' => 'SocialContent',
        'SocialContentPlatform' => 'SocialContent',
        'SocialContentRevision' => 'SocialContent',
        'SocialCategory' => 'SocialContent',
        'SocialProfile' => 'SocialContent',
        'SocialProfileLink' => 'SocialContent',
        'SocialSpecialDay' => 'SocialContent',
        'SocialWatchAccount' => 'SocialContent',
        'SocialWatchLink' => 'SocialContent',
        'SocialMetricEntry' => 'SocialContent',
        'SocialResponsiblePosition' => 'SocialContent',
    ];

    /** Bu konunun yetkisini devraldigi ana konu; yoksa null. */
    public static function parentOf(string $subject): ?string
    {
        return self::PARENTS[$subject] ?? null;
    }
}
