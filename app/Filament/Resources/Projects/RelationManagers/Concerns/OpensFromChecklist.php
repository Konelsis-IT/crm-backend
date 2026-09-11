<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers\Concerns;

use Livewire\Attributes\On;

/**
 * "Bu adimda beklenenler" kontrol listesindeki "+" dugmesi bu tabloyu
 * hedefleyen bir tarayici olayi gonderir (Filament Action::dispatch);
 * tablo kendi "Olustur" eylemini acar. Hedef anahtari tablo sayfaya
 * gomulurken verilir (ProjectWizard::relationManager, checklistTarget).
 */
trait OpensFromChecklist
{
    public ?string $checklistTarget = null;

    #[On('konelsis-checklist-create')]
    public function createFromChecklist(string $target): void
    {
        if ($target !== $this->checklistTarget) {
            return;
        }

        $this->mountAction('create', [], ['table' => true]);
    }
}
