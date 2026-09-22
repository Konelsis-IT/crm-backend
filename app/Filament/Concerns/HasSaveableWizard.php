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
 * adimda hangi yontemin calisacagini soyler.
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
            ->saveOnSteps($this->getStepSaveMethods());
    }

    /**
     * Adim kimligi => "Kaydet"in calistiracagi sayfa yontemi.
     *
     * @return array<string, string>
     */
    abstract protected function getStepSaveMethods(): array;
}
