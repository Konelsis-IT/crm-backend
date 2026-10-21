<?php

declare(strict_types=1);

namespace App\Query\Dashboard;

use App\Models\Acquisition\Proposal;
use App\Models\Approval\ApprovalRequest;
use App\Models\Party\MeetingPlan;
use App\Models\Personnel\Personnel;
use App\Models\Report\Report;
use App\Models\Report\WorkItem;
use App\Query\Acquisition\ProposalQueries;
use App\Query\Approval\ApprovalQueries;
use App\Query\Party\MeetingPlanQueries;
use App\Query\Report\ReportQueries;
use App\Query\Report\WorkItemQueries;
use App\Services\Platform\SchemaReadiness;

/**
 * Pano uyari seridinin sayilari (D-227). Her sayi, uyariya tiklaninca acilan
 * listenin (sekme + suzgec) kullandigi sorgunun aynisidir; liste ayni kisiye
 * ayni sayida kayit gosterir:
 *  - incelenecek rapor: Raporlar > Incelemem gerekenler (ReportQueries::applyReviewInbox)
 *  - onay: Onaylar > Bende bekleyen (ApprovalQueries::applyInbox)
 *  - teklif uyarilari: Teklifler > Uyari suzgeci (ProposalQueries::applyAlert)
 *  - gecmis gorusme: Gorusme plani listesi > Gecikti (+ kisi; arsiv disi)
 *  - kritik is: Isler > Acik + Kritik, kisinin gorebildigi isler (applyVisible)
 */
final class DashboardAlertQueries
{
    public function __construct(
        private readonly ReportQueries $reports,
        private readonly ApprovalQueries $approvals,
        private readonly ProposalQueries $proposals,
        private readonly MeetingPlanQueries $meetings,
        private readonly WorkItemQueries $work,
    ) {}

    public function reviewReports(int $personnelId): int
    {
        return $this->reports->applyReviewInbox(Report::query(), $personnelId)->count();
    }

    public function approvalInbox(int $personnelId): int
    {
        return $this->approvals->applyInbox(ApprovalRequest::query(), $personnelId)->count();
    }

    public function proposals(string $alert): int
    {
        return $this->proposals->applyAlert(Proposal::query(), $alert)->count();
    }

    /** Sonucu girilmemis gecmis gorusmeler; kisi verilirse yalniz onun katildiklari. */
    public function overdueMeetings(?int $personnelId): int
    {
        $query = $this->meetings->tab(MeetingPlan::query(), 'overdue');

        if (SchemaReadiness::hasBatch('B44')) {
            $query = $this->meetings->archiveScope($query, 'active');
        }

        return ($personnelId === null ? $query : $this->meetings->involving($query, $personnelId))->count();
    }

    /** Kisinin gorebildigi acik kritik isler. */
    public function criticalWork(Personnel $viewer): int
    {
        $query = $this->work->applyStatuses($this->work->applyVisible(WorkItem::query(), $viewer), ['open']);

        return (WorkItemQueries::extendedUi()
            ? $this->work->applyPriorities($query, ['critical'])
            : $this->work->applyCritical($query))->count();
    }
}
