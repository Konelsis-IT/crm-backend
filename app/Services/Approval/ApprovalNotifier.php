<?php

declare(strict_types=1);

namespace App\Services\Approval;

use App\Filament\Resources\ApprovalRequests\ApprovalRequestResource;
use App\Models\Approval\ApprovalRequest;
use App\Models\Approval\ApprovalRequestStep;
use App\Models\Personnel\Personnel;
use App\Services\Authorization\RoleResolver;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Onay motorunun bildirimleri: yalniz Filament bildirim zili (D-49). Bir
 * bildirim gonderilemezse is akisi bozulmaz.
 *
 * Escalation zinciri (D-35): onaycinin dogrudan amiri -> birim yoneticisi ->
 * ust yonetim (system_admin). Sure asiminda zincir ve talep sahibi bilgilenir.
 */
final class ApprovalNotifier
{
    public function stepActivated(ApprovalRequestStep $row): void
    {
        $approver = $row->approver;
        $request = $row->request;

        if ($approver === null || $request === null) {
            return;
        }

        $this->send(
            $approver,
            __('approval_request.notifications.step_activated.title'),
            __('approval_request.notifications.step_activated.body', [
                'subject' => (string) $request->subject_label,
                'step' => $row->step?->localizedName() ?? '-',
                'due' => $row->due_at?->timezone(config('app.timezone', 'UTC'))->format('d.m.Y H:i') ?? '-',
            ]),
            $request,
            Heroicon::OutlinedClipboardDocumentCheck,
            'warning',
            $this->decisionActions($request, $approver),
        );
    }

    public function requestDecided(ApprovalRequest $request, bool $approved): void
    {
        $requester = $request->requester;

        if ($requester === null) {
            return;
        }

        $this->send(
            $requester,
            __($approved ? 'approval_request.notifications.approved.title' : 'approval_request.notifications.rejected.title'),
            __($approved ? 'approval_request.notifications.approved.body' : 'approval_request.notifications.rejected.body', [
                'subject' => (string) $request->subject_label,
            ]),
            $request,
            $approved ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle,
            $approved ? 'success' : 'danger',
        );
    }

    public function requestClosed(ApprovalRequest $request, string $reasonKey): void
    {
        $recipients = $request->activeSteps()->with('approver')->get()
            ->map(fn (ApprovalRequestStep $row) => $row->approver)
            ->filter()
            ->unique(fn (Personnel $personnel) => $personnel->getKey());

        foreach ($recipients as $personnel) {
            $this->send(
                $personnel,
                __('approval_request.notifications.'.$reasonKey.'.title'),
                __('approval_request.notifications.'.$reasonKey.'.body', ['subject' => (string) $request->subject_label]),
                $request,
                Heroicon::OutlinedNoSymbol,
                'gray',
            );
        }
    }

    /**
     * Sure asimi: talep sahibi ve her gecikmis onaycinin escalation zinciri.
     *
     * @param  Collection<int, ApprovalRequestStep>  $overdueRows
     */
    public function requestExpired(ApprovalRequest $request, Collection $overdueRows): void
    {
        $recipients = collect();

        if ($request->requester !== null) {
            $recipients->push($request->requester);
        }

        foreach ($overdueRows as $row) {
            $approver = $row->approver;

            if ($approver === null) {
                continue;
            }

            $recipients->push($approver);

            foreach ($this->escalationChain($approver) as $personnel) {
                $recipients->push($personnel);
            }
        }

        foreach ($recipients->unique(fn (Personnel $personnel) => $personnel->getKey()) as $personnel) {
            $this->send(
                $personnel,
                __('approval_request.notifications.expired.title'),
                __('approval_request.notifications.expired.body', ['subject' => (string) $request->subject_label]),
                $request,
                Heroicon::OutlinedExclamationTriangle,
                'warning',
            );
        }
    }

    /**
     * @return list<Personnel>
     */
    private function escalationChain(Personnel $approver): array
    {
        $chain = [];

        $manager = $approver->currentManager();

        if ($manager !== null) {
            $chain[] = $manager;
        }

        $unitManager = $approver->orgUnit?->manager;

        if ($unitManager !== null) {
            $chain[] = $unitManager;
        }

        foreach (Personnel::query()->role(RoleResolver::MANAGER)->get() as $executive) {
            $chain[] = $executive;
        }

        return array_values(array_filter($chain, fn (Personnel $personnel): bool => $personnel->isActive() && (int) $personnel->getKey() !== (int) $approver->getKey()));
    }

    /**
     * Bildirimden onaylama (D-82): "Onayla" imzali baglantiyla tek tiklamada
     * karar verir; "Reddet" gerekce istedigi icin talep sayfasini ret
     * penceresi acik gelir (?karar=reject).
     *
     * @return list<Action>
     */
    private function decisionActions(ApprovalRequest $request, Personnel $approver): array
    {
        try {
            $approveUrl = URL::temporarySignedRoute(
                'filament.admin.notifications.approval-approve',
                Carbon::now()->addDays(7),
                ['approval' => $request->getKey(), 'p' => $approver->getKey()],
            );
            $rejectUrl = ApprovalRequestResource::getUrl('view', ['record' => $request, 'karar' => 'reject']);
        } catch (Throwable) {
            return [];
        }

        return [
            Action::make('approve')
                ->label(__('approval_request.actions.approve'))
                ->button()
                ->color('success')
                ->url($approveUrl)
                ->markAsRead(),
            Action::make('reject')
                ->label(__('approval_request.actions.reject'))
                ->button()
                ->color('danger')
                ->url($rejectUrl)
                ->markAsRead(),
        ];
    }

    /**
     * @param  list<Action>  $extraActions
     */
    private function send(Personnel $personnel, string $title, string $body, ApprovalRequest $request, Heroicon $icon, string $color, array $extraActions = []): void
    {
        if (! FeatureFlags::enabled('notifications.database') || ! SchemaReadiness::hasBatch('B00')) {
            return;
        }

        try {
            $notification = Notification::make()
                ->title($title)
                ->body($body)
                ->icon($icon)
                ->iconColor($color);

            $url = $this->url($request);
            $actions = $extraActions;

            if ($url !== null) {
                $actions[] = Action::make('open')
                    ->label(__('approval_request.actions.open'))
                    ->link()
                    ->url($url);
            }

            if ($actions !== []) {
                $notification->actions($actions);
            }

            // Kuyruksuz aninda teslim (bkz. PanelNotifier).
            $personnel->notifyNow($notification->toDatabase());
        } catch (Throwable) {
            // Bildirim is akisini durdurmaz.
        }
    }

    private function url(ApprovalRequest $request): ?string
    {
        try {
            return ApprovalRequestResource::getUrl('view', ['record' => $request]);
        } catch (Throwable) {
            return null;
        }
    }
}
