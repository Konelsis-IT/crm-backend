<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

/**
 * Teklif duzenleme ve "Yeni teklif surumu" ekranindaki dosya ciplerinin "x"i
 * (D-186, 9 Ekim 2026 kullanici talimati: "var olan belgeleri de isterse carpi
 * butonu ile kaldirip ..."). Cipteki kucuk dugme `wire:click` ile bu metodu
 * cagirir (CompactUpload::chips): revizyon kimligi formdaki gizli
 * `..._removed` dizisine eklenir ya da (Geri al) cikarilir. Hicbir sey hemen
 * silinmez; isaret kaydetmede uygulanir (AcquisitionIntakeService,
 * ProposalVersionScopeService) ve belge Dokumanlar'da kalir.
 *
 * Yalniz teklif formunun bilinen "kaldirilacaklar" yollari kabul edilir.
 */
trait RemovesProposalFiles
{
    public function toggleProposalFile(string $path, int $revisionId): void
    {
        if ($revisionId <= 0 || preg_match('/^(?:scopes\.[a-z_]+\.(?:removed_scope_file|removed_cost_files)|[a-z_]+_removed)$/', $path) !== 1) {
            return;
        }

        $current = array_values(array_map('intval', (array) data_get($this->data, $path, [])));

        $next = in_array($revisionId, $current, true)
            ? array_values(array_diff($current, [$revisionId]))
            : [...$current, $revisionId];

        data_set($this->data, $path, $next);
    }
}
