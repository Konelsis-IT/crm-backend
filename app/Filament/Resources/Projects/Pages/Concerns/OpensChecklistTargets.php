<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages\Concerns;

/**
 * Kontrol listesindeki "+" dugmesinin sayfa tarafi. Gomulu tablolar tembel
 * yuklenir (Filament: gorunur alana girince); henuz yuklenmemis bir tabloya
 * olay gondermek bosa gider. Bu yuzden hedef tablo once "hemen yukle"
 * listesine alinir (anahtari degisir, sayfa yeniden cizilirken tabloyla
 * birlikte kurulur), ardindan tabloya olusturma olayi gonderilir.
 */
trait OpensChecklistTargets
{
    /** @var list<string> */
    public array $eagerRelations = [];

    public function checklistCreate(string $target): void
    {
        if (! in_array($target, $this->eagerRelations, true)) {
            $this->eagerRelations[] = $target;
        }

        $this->dispatch('konelsis-checklist-create', target: $target);
    }
}
