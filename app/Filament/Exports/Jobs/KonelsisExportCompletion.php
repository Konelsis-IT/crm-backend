<?php

declare(strict_types=1);

namespace App\Filament\Exports\Jobs;

use Filament\Actions\Exports\Jobs\ExportCompletion;

/**
 * Disa aktarma tamamlama isi (D-110). Kuyruksuz akista dosya dugmeye basinca
 * dogrudan iner (ExportActions); tum satirlar aktarildiysa ayrica "dosya hazir"
 * bildirimi gosterilmez. Aktarilamayan satir varsa Filament'in bildirimi
 * (indirme dugmesiyle) aynen cikar.
 */
class KonelsisExportCompletion extends ExportCompletion
{
    public function handle(): void
    {
        $isSynchronous = ($this->connection === 'sync') || (blank($this->connection) && (config('queue.default') === 'sync'));

        if ($isSynchronous && $this->export->getFailedRowsCount() === 0) {
            $this->export->touch('completed_at');

            return;
        }

        parent::handle();
    }
}
