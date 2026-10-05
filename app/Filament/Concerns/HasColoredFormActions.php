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
 */
trait HasColoredFormActions
{
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
