<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Support\ActionColors;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

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
}
