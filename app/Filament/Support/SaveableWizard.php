<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
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
 *
 * B43 (D-155): istenen adimlarda "Kaydet"in soluna gri "Taslak olarak kaydet"
 * de gelir (draftOnSteps); ikisi ayni kutudadir.
 *
 * D-157: "Ileri"den once sayfanin bir yontemi calisabilir (guardNextOnSteps;
 * potansiyel is adiminda kontrol listesi ozeti). Yontem true donerse gecis
 * durur; pencere onayinda sayfa `next-wizard-step` olayini kendisi yayar.
 */
class SaveableWizard extends Wizard
{
    /** @var array<string, string> adim kimligi => sayfa yontemi (or. create, save, saveCaseOnly) */
    protected array $stepSaveMethods = [];

    /** @var array<string, string> adim kimligi => taslak kaydi yontemi */
    protected array $stepDraftMethods = [];

    /** @var array<string, string> adim kimligi => "Ileri"den once calisan sayfa yontemi (sihirbaz anahtarini alir) */
    protected array $stepNextGuards = [];

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
     * @param  array<string, string>  $methods
     */
    public function draftOnSteps(array $methods): static
    {
        $this->stepDraftMethods = $methods;

        return $this;
    }

    /**
     * @param  array<string, string>  $methods
     */
    public function guardNextOnSteps(array $methods): static
    {
        $this->stepNextGuards = $methods;

        return $this;
    }

    /**
     * "Ileri": adimin bekcisi varsa once o calisir (true: gecis pencereye birakildi).
     */
    #[ExposedLivewireMethod]
    public function nextStep(int $currentStepIndex): void
    {
        $steps = array_values($this->getChildSchema()->getComponents());
        $step = $steps[$currentStepIndex] ?? null;
        $method = $step instanceof Step ? ($this->stepNextGuards[(string) $step->getId()] ?? null) : null;

        if ($method !== null) {
            $livewire = $this->getLivewire();

            if (method_exists($livewire, $method) && $livewire->{$method}((string) $this->getKey()) === true) {
                return;
            }
        }

        parent::nextStep($currentStepIndex);
    }

    /**
     * @return list<Action>
     */
    public function getStepSaveActions(): array
    {
        $actions = [];

        foreach ($this->stepDraftMethods as $stepId => $method) {
            $actions[] = Action::make(self::draftActionName($stepId))
                ->label(__('app.actions.save_draft'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->action($method)
                ->button();
        }

        foreach ($this->stepSaveMethods as $stepId => $method) {
            $actions[] = Action::make(self::saveActionName($stepId))
                ->label(__('app.actions.save'))
                ->icon(Heroicon::OutlinedCheck)
                ->color(ActionColors::SAVE)
                ->action($method)
                ->button();
        }

        return $actions;
    }

    public function toEmbeddedHtml(): string
    {
        $html = parent::toEmbeddedHtml();

        if ($this->stepSaveMethods === [] && $this->stepDraftMethods === []) {
            return $html;
        }

        $buttons = '';

        foreach ($this->getChildSchema()->getComponents() as $step) {
            if (! $step instanceof Step) {
                continue;
            }

            $stepId = (string) $step->getId();
            $inner = '';

            foreach ([self::draftActionName($stepId) => $this->stepDraftMethods, self::saveActionName($stepId) => $this->stepSaveMethods] as $name => $methods) {
                if (! array_key_exists($stepId, $methods)) {
                    continue;
                }

                $action = $this->getAction($name);

                if ($action !== null && $action->isVisible()) {
                    $inner .= $action->toHtml();
                }
            }

            if ($inner !== '') {
                $buttons .= '<div x-cloak x-show="step === '.Js::from($step->getKey()).'" class="kc-wizard-save">'.$inner.'</div>';
            }
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

    private static function draftActionName(string $stepId): string
    {
        return 'draft_'.$stepId.'_step';
    }
}
