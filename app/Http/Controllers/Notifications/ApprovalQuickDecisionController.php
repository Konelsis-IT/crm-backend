<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Enums\Approval\ApprovalDecisionKind;
use App\Exceptions\AbstractException;
use App\Filament\Resources\ApprovalRequests\ApprovalRequestResource;
use App\Filament\Support\DomainNotifications;
use App\Http\Controllers\Controller;
use App\Models\Approval\ApprovalRequest;
use App\Services\Approval\ApprovalRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bildirimden tek tiklamayla onay (D-82, "bildirimden onaylama"). Baglanti
 * imzali ve 7 gun gecerlidir; yalniz bildirimi alan personel kullanabilir.
 * Ret/iade gerekce istedigi icin talep sayfasina yonlendirilir (?karar=...).
 */
final class ApprovalQuickDecisionController extends Controller
{
    public function __invoke(Request $request, ApprovalRequest $approval, ApprovalRequestService $service): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403, __('approval_request.messages.link_invalid'));
        abort_unless((int) $request->query('p') === (int) $request->user()?->getAuthIdentifier(), 403, __('approval_request.messages.link_not_yours'));

        try {
            $service->decide($approval, ApprovalDecisionKind::Approved);
            DomainNotifications::success(__('approval_request.messages.decided'));
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);
        }

        return redirect()->to(ApprovalRequestResource::getUrl('view', ['record' => $approval]));
    }
}
