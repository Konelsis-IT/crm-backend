<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;

/**
 * Adimlarda "Kaydet" tasiyan sihirbaz (D-112; 22 Eylul 2026 kullanici karari
 * ve HTML ozellestirme onayi). Filament sihirbazinin alt satiri yalniz Geri /
 * Ileri / Olustur dugmelerini cizer ve ek dugme icin ayar sunmaz; bu sinif alt
 * satira, "Ileri"nin hemen soluna, o anki adima ait yesil "Kaydet" ekler.
 * Dugme Filament eylemidir (adim kimligi => sayfanin Livewire yontemi); adimi
 * degisince Alpine `step` ile yalniz o adimin dugmesi gorunur. Yerlesim:
 * resources/css/filament/konelsis.css (.kc-wizard-save).
 */
class SaveableWizard extends Wizard
{
    /** @var array<string, string> adim kimligi => sayfa yontemi (or. create, save, saveCaseOnly) */
    protected array $stepSaveMethods = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerActions([
            fn (SaveableWizard $component): array => $component->getStepSaveActions(),
        ]);
    }

    /**
     * @param  array<string, string>  $methods
     */
    public function saveOnSteps(array $methods): static
    {
        $this->stepSaveMethods = $methods;

        return $this;
    }

    /**
     * @return list<Action>
     */
    public function getStepSaveActions(): array
    {
        $actions = [];

        foreach ($this->stepSaveMethods as $stepId => $method) {
            $actions[] = Action::make(self::saveActionName($stepId))
                ->label(__('app.actions.save'))
                ->icon(Heroicon::OutlinedCheck)
                ->color('success')
                ->action($method)
                ->button();
        }

        return $actions;
    }

    public function toEmbeddedHtml(): string
    {
        $html = parent::toEmbeddedHtml();

        if ($this->stepSaveMethods === []) {
            return $html;
        }

        $buttons = '';

        foreach ($this->getChildSchema()->getComponents() as $step) {
            if (! $step instanceof Step || ! array_key_exists((string) $step->getId(), $this->stepSaveMethods)) {
                continue;
            }

            $action = $this->getAction(self::saveActionName((string) $step->getId()));

            if ($action === null || ! $action->isVisible()) {
                continue;
            }

            $buttons .= '<div x-cloak x-show="step === '.Js::from($step->getKey()).'" class="kc-wizard-save">'.$action->toHtml().'</div>';
        }

        // Alt satirda "Ileri" dugmesinin kutusundan hemen once.
        $next = strpos($html, 'x-on:click="requestNextStep()"');
        $boxStart = $next === false ? false : strrpos(substr($html, 0, $next), '<div');

        if ($boxStart === false) {
            return $html;
        }

        return substr($html, 0, $boxStart).$buttons.substr($html, $boxStart);
    }

    private static function saveActionName(string $stepId): string
    {
        return 'save_'.$stepId.'_step';
    }
}
