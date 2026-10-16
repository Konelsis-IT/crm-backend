<?php

declare(strict_types=1);

namespace App\Livewire\Procurement;

use App\Enums\Procurement\QuotationAiStatus;
use App\Models\Procurement\PurchaseQuotation;
use App\Query\Procurement\QuotationAiQueries;
use App\Services\Procurement\PurchaseQuotationAiService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Livewire;
use Throwable;

/**
 * Konelsis AI okuma ilerlemesi (D-192). Teklif sayfasinda (detay ve duzenleme)
 * okuma suruyorken gorunur ve birkac saniyede bir sonucu yoklar
 * (PurchaseQuotationAiService::poll). Sonuc yazilinca bildirim gonderir ve
 * sayfayi yeniden yukler; boylece form AI'nin doldurdugu degerleri gosterir ve
 * eski form durumu kaydedilip AI degerlerini ezemez. CRM'de kuyruk iscisi
 * gerekmez. Kullanici onayi: "Javascriptte kullanabilirsin. Onayliyorum."
 *
 * Sayfaya PurchaseQuotationAiActions::progress() ile tek satirla eklenir.
 */
final class QuotationAiProgress extends Component
{
    public int $quotationId = 0;

    public string $returnUrl = '';

    public function mount(mixed $record = null): void
    {
        $this->quotationId = $record instanceof PurchaseQuotation ? (int) $record->getKey() : 0;
        $this->returnUrl = Livewire::isLivewireRequest() ? (string) Livewire::originalUrl() : (string) request()->fullUrl();
    }

    public function check(PurchaseQuotationAiService $service): void
    {
        $quotation = $this->quotation($service);

        if ($quotation === null) {
            return;
        }

        try {
            $outcome = $service->poll($quotation);
        } catch (Throwable) {
            return;
        }

        if ($outcome['status'] === QuotationAiStatus::Pending || $outcome['status'] === null) {
            return;
        }

        $notification = Notification::make();

        if ($outcome['status'] === QuotationAiStatus::Completed) {
            $noQuotation = $outcome['quotations'] === 0;
            $body = trim(implode(' ', array_filter([
                $outcome['message'],
                $outcome['new_items'] > 0 ? __('purchase_quotation_ai.notifications.new_items', ['count' => $outcome['new_items']]) : null,
            ])));

            $notification
                ->title(__($noQuotation ? 'purchase_quotation_ai.notifications.no_quotation_title' : 'purchase_quotation_ai.notifications.completed_title'))
                ->body($body)
                ->{$noQuotation ? 'warning' : 'success'}();

            if ($outcome['new_items'] > 0) {
                $notification->persistent();
            }
        } else {
            $notification
                ->title(__('purchase_quotation_ai.notifications.failed_title'))
                ->body($outcome['message'])
                ->danger()
                ->persistent();
        }

        $notification->send();

        if ($this->returnUrl !== '') {
            $this->redirect($this->returnUrl);
        }
    }

    public function render(PurchaseQuotationAiService $service, QuotationAiQueries $queries): View
    {
        $quotation = $this->quotation($service);
        $reading = $quotation !== null && $quotation->aiStatusEnum() === QuotationAiStatus::Pending;

        return view('livewire.procurement.quotation-ai-progress', [
            'reading' => $reading,
            'documents' => $reading ? $queries->pendingDocuments($quotation)->count() : 0,
        ]);
    }

    private function quotation(PurchaseQuotationAiService $service): ?PurchaseQuotation
    {
        if ($this->quotationId <= 0 || ! $service->available()) {
            return null;
        }

        try {
            $quotation = $service->show($this->quotationId);
        } catch (Throwable) {
            return null;
        }

        return $quotation instanceof PurchaseQuotation && Gate::allows('view', $quotation) ? $quotation : null;
    }
}
