<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Support\SaveableWizard;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Schemas\Components\Component;

/**
 * Filament HasWizard'in aynisi; sihirbaz SaveableWizard'dir ve alt satirda
 * adimlara ait "Kaydet" dugmeleri bulunur (22 Eylul 2026 kullanici karari:
 * surekli ileri gitme zorunlulugu yok). Sayfa getStepSaveMethods() ile hangi
 * adimda hangi yontemin calisacagini soyler; getStepDraftMethods() (B43,
 * D-155) "Taslak olarak kaydet" dugmelerini verir.
 */
trait HasSaveableWizard
{
    use HasWizard;

    public function getWizardComponent(): Component
    {
        return SaveableWizard::make($this->getSteps())
            ->startOnStep($this->getStartStep())
            ->cancelAction($this->getCancelFormAction())
            ->submitAction($this->getSubmitFormAction())
            ->alpineSubmitHandler("\$wire.{$this->getSubmitFormLivewireMethodName()}()")
            ->skippable($this->hasSkippableSteps())
            ->contained(false)
            ->saveOnSteps($this->getStepSaveMethods())
            ->draftOnSteps($this->getStepDraftMethods())
            ->guardNextOnSteps($this->getStepNextGuards());
    }

    /**
     * Adim kimligi => "Ileri"den once calisan sayfa yontemi (D-157); yontem
     * sihirbaz anahtarini alir, true donerse gecis durur (or. ozet penceresi).
     *
     * @return array<string, string>
     */
    protected function getStepNextGuards(): array
    {
        return [];
    }

    /**
     * Adim kimligi => "Kaydet"in calistiracagi sayfa yontemi.
     *
     * @return array<string, string>
     */
    abstract protected function getStepSaveMethods(): array;

    /**
     * Adim kimligi => "Taslak olarak kaydet"in calistiracagi sayfa yontemi.
     *
     * @return array<string, string>
     */
    protected function getStepDraftMethods(): array
    {
        return [];
    }
}
