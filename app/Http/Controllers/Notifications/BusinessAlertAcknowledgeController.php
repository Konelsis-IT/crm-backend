<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Exceptions\AbstractException;
use App\Filament\Pages\Dashboard;
use App\Filament\Support\DomainNotifications;
use App\Http\Controllers\Controller;
use App\Models\Notification\BusinessAlert;
use App\Services\Notification\BusinessAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Bildirimdeki "Gordum" dugmesi (D-82): imzali baglanti, yalniz alici.
 */
final class BusinessAlertAcknowledgeController extends Controller
{
    public function __invoke(Request $request, BusinessAlert $alert, BusinessAlertService $service): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403, __('business_alert.messages.link_invalid'));
        abort_unless((int) $request->query('p') === (int) $request->user()?->getAuthIdentifier(), 403, __('business_alert.messages.link_not_yours'));
        Gate::authorize('acknowledge', $alert);

        try {
            $service->acknowledge($alert);
            DomainNotifications::success(__('business_alert.messages.acknowledged'));
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);
        }

        return redirect()->to(filled($alert->url) ? (string) $alert->url : Dashboard::getUrl());
    }
}
