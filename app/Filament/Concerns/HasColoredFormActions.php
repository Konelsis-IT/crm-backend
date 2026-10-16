<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Support\ActionColors;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;

/**
 * Olustur / Duzenle sayfasinin alt dugmeleri (D-148): "Olustur" / "Kaydet"
 * yesil, "Iptal" gul kirmizisi. Filament bu dugmeleri sayfanin icinde kurar
 * (Iptal'i acikca gri yapar); bu yuzden renk sayfada verilir. Her
 * CreateRecord / EditRecord sayfasi bu ozelligi kullanir (safe-verify denetler).
 *
 * D-178 (8 Ekim 2026 kullanici talimati: "Bir duzenleme / olusturma ekranindan
 * kaydet dedikten sonra detay sayfasina route edilmelidir. Ayni edit / create
 * sayfasinda kalmamalidir. Butun uygulama icin gecerlidir bu."): basarili
 * olusturma ve kaydetmeden sonra kaydin detay sayfasina, detay sayfasi yoksa
 * (ya da kisi goremiyorsa) listeye gidilir. Kendi getRedirectUrl()'ini yazan
 * sayfa (or. projeye donusunce proje sayfasi, taslakta sihirbaz) bunu ezer.
 * Taslak kaydi ("Taslak olarak kaydet") sihirbazda kalir: o sayfalar
 * save(shouldRedirect: false) cagirir.
 */
trait HasColoredFormActions
{
    /**
     * D-178: kaydettikten sonra detay sayfasi, yoksa liste. Donus tipi string:
     * CreateRecord (string) ve EditRecord (?string) ikisiyle de uyumlu; null
     * (ayni sayfada kalmak) bilerek yok.
     */
    protected function getRedirectUrl(): string
    {
        $resource = static::getResource();
        $record = $this->getRecord();
        $parameters = $this->getRedirectUrlParameters();

        if ($record !== null && $resource::hasPage('view') && $resource::canView($record)) {
            return $this->getResourceUrl('view', $parameters);
        }

        return $this->getResourceUrl(parameters: $parameters);
    }

    /**
     * Normal sayfa: [Olustur | Kaydet, Iptal].
     *
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return array_map(
            fn (Action | ActionGroup $action): Action | ActionGroup => $action instanceof Action && in_array($action->getName(), ['create', 'save'], true)
                ? ActionColors::save($action)
                : $action,
            parent::getFormActions(),
        );
    }

    /** Sihirbazli sayfa: son adimdaki "Olustur" / "Kaydet". */
    protected function getSubmitFormAction(): Action
    {
        return ActionColors::save(parent::getSubmitFormAction());
    }

    protected function getCancelFormAction(): Action
    {
        return ActionColors::cancel(parent::getCancelFormAction());
    }

    /**
     * Duzenle sayfasinin basligindaki "Iptal" (14 Ekim 2026 kullanici talimati:
     * "Tum duzenle ekranlarinda goruntule butonlari kaldirilmalidir. Yerine Iptal
     * butonu getirilmelidir. Yani mantik kaydet - iptal gibi olacak."). Kaydetmeden
     * cikar: gelinen sayfaya, yoksa kaydin detay sayfasina, o da yoksa listeye.
     * Taslak, Ileri gibi ozel dugmeler yerinde kalir.
     */
    protected function cancelHeaderAction(): Action
    {
        return ActionColors::cancel(Action::make('cancel_edit')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.cancel.label'))
            ->icon(Heroicon::OutlinedXMark)
            ->url(fn (): string => $this->cancelHeaderUrl()));
    }

    private function cancelHeaderUrl(): string
    {
        $previous = property_exists($this, 'previousUrl') ? $this->previousUrl : null;

        if (filled($previous) && $previous !== url()->current() && ! str_contains((string) $previous, '/livewire')) {
            return (string) $previous;
        }

        $resource = static::getResource();
        $record = $this->getRecord();

        if ($record !== null && $resource::hasPage('view') && $resource::canView($record)) {
            return $this->getResourceUrl('view');
        }

        return $this->getResourceUrl();
    }
}
