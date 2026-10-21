<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Filament\Resources\ApprovalRequests\ApprovalRequestResource;
use App\Filament\Resources\MeetingPlans\MeetingPlanResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Models\Personnel\Personnel;
use App\Query\Acquisition\ProposalQueries;
use App\Query\Dashboard\DashboardAlertQueries;
use App\Query\Report\WorkItemQueries;
use App\Services\Authorization\ExecutiveDirectory;
use App\Services\Platform\SchemaReadiness;
use App\Support\Dashboard\DepartmentDashboards;

/**
 * Departman panolarinin uyari seridi (D-227, 19 Ekim 2026 kullanici: "Pano
 * bilesenleri => 15 Uyari seridi kismini da dashboard'a alalim. Ama ilgili
 * uyarilar gercekci olmali ve hepsine tiklanabilmeli ve ilgili konuya
 * tiklandiginda ornegin 8 incelenecek rapor var diyorsa ona gore
 * tiklandiginda filtrelenmis seklinde 8 kayit gelecek").
 *
 * Her uyari: kisinin gercek sayisi, metni ve tam o kayitlari gosteren liste
 * adresi (sekme / suzgec URL'de). Sayilar DashboardAlertQueries'te listenin
 * kendi sorgusuyla hesaplanir. Kisinin acamadigi liste ve sifir sayi gelmez.
 * Paneller: hepsinde incelenecek rapor, bekleyen onay, kritik is; Is
 * gelistirme ve Yonetici panelinde teklif ve gorusme uyarilari da.
 */
final class DashboardAlerts
{
    public function __construct(
        private readonly DashboardAlertQueries $counts,
        private readonly ExecutiveDirectory $executives,
    ) {}

    /** @return list<array{key: string, tone: string, icon: string, count: int, text: string, title: string, url: string}> */
    public function forPanel(string $panel, Personnel $viewer): array
    {
        $personnelId = (int) $viewer->getKey();
        $acquisition = in_array($panel, [DepartmentDashboards::ACQUISITION, DepartmentDashboards::EXECUTIVE], true);
        $items = [];

        if (ReportResource::canAccess()) {
            $items[] = $this->item('reports_review', 'blue', 'document', $this->counts->reviewReports($personnelId), ReportResource::getUrl('index', ['tab' => 'review']));
        }

        if (ApprovalRequestResource::canAccess()) {
            $items[] = $this->item('approvals_inbox', 'amber', 'checkCircle', $this->counts->approvalInbox($personnelId), ApprovalRequestResource::getUrl('index', ['tab' => 'inbox']));
        }

        if (WorkItemResource::canAccess()) {
            $filters = ['status' => ['values' => ['open']]]
                + (WorkItemQueries::extendedUi()
                    ? ['priority' => ['values' => ['critical']]]
                    : ['critical' => ['isActive' => true]]);
            $items[] = $this->item('critical_work', 'red', 'alert', $this->counts->criticalWork($viewer), WorkItemResource::getUrl('index', ['filters' => $filters]));
        }

        if ($acquisition && ProposalResource::canAccess() && SchemaReadiness::hasBatch('B29')) {
            foreach ([
                ProposalQueries::ALERT_STALE_SUBMITTED => 'red',
                ProposalQueries::ALERT_STALE_TO_SUBMIT => 'amber',
                ProposalQueries::ALERT_NO_AMOUNT => 'blue',
            ] as $alert => $tone) {
                $items[] = $this->item($alert, $tone, 'clipboard', $this->counts->proposals($alert), ProposalResource::getUrl('index', ['filters' => ['alert' => ['value' => $alert]]]), [
                    'to_submit' => ProposalQueries::STALE_TO_SUBMIT_DAYS,
                    'submitted' => ProposalQueries::STALE_SUBMITTED_DAYS,
                ]);
            }
        }

        if ($acquisition && MeetingPlanResource::canAccess()) {
            // Ust yonetim butun gecmis gorusmeleri, digerleri kendi katildiklarini gorur.
            $all = $this->executives->isExecutive($viewer);
            $parameters = $all ? ['tab' => 'overdue'] : ['tab' => 'overdue', 'filters' => ['personnel' => ['value' => $personnelId]]];
            $items[] = $this->item('past_meetings', 'amber', 'calendar', $this->counts->overdueMeetings($all ? null : $personnelId), MeetingPlanResource::getUrl('list', $parameters));
        }

        return array_values(array_filter($items, fn (array $item): bool => $item['count'] > 0));
    }

    /**
     * @param  array<string, int>  $replace
     * @return array{key: string, tone: string, icon: string, count: int, text: string, title: string, url: string}
     */
    private function item(string $key, string $tone, string $icon, int $count, string $url, array $replace = []): array
    {
        return [
            'key' => $key,
            'tone' => $tone,
            'icon' => $icon,
            'count' => $count,
            'text' => (string) __('new_dashboard.alerts.'.$key, $replace),
            'title' => (string) __('new_dashboard.alerts.titles.'.$key),
            'url' => $url,
        ];
    }
}
