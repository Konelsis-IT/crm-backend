<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersion;
use App\Services\Audit\ActorContext;
use App\Services\Platform\SchemaReadiness;
use Database\Seeders\Support\KksRevisions;
use Database\Seeders\Support\SeedGuard;

/**
 * KKS 07.10.2026 listesindeki revizyonlar icin yeni teklif surumleri (D-198,
 * kullanici 13 Ekim 2026: "KKS surumu icin evet yap").
 *
 * KksListUpdate20261007Seeder ilk calistiginda teklif tarihi ileri giden
 * Verilen teklifleri yalniz ozete yazmisti (yerelde 7 teklif). Bu seed ayni
 * listeyi yeniden okur ve revizyon sayilan satirlarda (Support\KksRevisions)
 * yeni surum acar: onceki surum kendi durumunu (Verilen) korur, yeni surum
 * listedeki tarihle Verilen olur. Liste seed'i zaten yeni kuralla calistiysa
 * (guncel surum listedeki tarihle gonderilmis ya da ozetinde ayni satir var)
 * hicbir sey yapmaz.
 *
 * B55 (surumun kendi teklif durumu) uygulanmadan calismaz; satirlar sonraki
 * kurulumda yeniden denenir. Kurulumda DEPLOY_SEEDERS ile, liste seed'inden
 * sonra calisir.
 */
final class KksRevisionVersions20261013Seeder extends KksListUpdate20261007Seeder
{
    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B55')) {
            $this->command?->warn('B55 uygulanmamis; KKS 07.10 revizyon surumleri sonraki kuruluma kaldi.');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $opened = 0;

        foreach ($this->plan()['rows'] as $row) {
            $previous = $this->previousRow($row);

            if (! KksRevisions::isRevision($row, $previous)) {
                continue;
            }

            $this->row('kks-1007-revision:'.$this->rowKey($row), function () use ($row, $previous, &$opened): Proposal|bool|null {
                $proposal = $this->existingProposal($row, $previous);

                if (! $proposal instanceof Proposal) {
                    return null;
                }

                $version = SeedGuard::allowingUpdates(fn (): ?ProposalVersion => app(KksRevisions::class)->open(
                    $proposal,
                    $row,
                    KksRevisions::summaryLine($row, $previous, static::LIST_DATE),
                ));

                if ($version instanceof ProposalVersion) {
                    $opened++;
                }

                // Kapali teklif ya da zaten acilmis revizyon da islenmis sayilir.
                return $proposal;
            });
        }

        $this->command?->info(sprintf('KKS 07.10 revizyonlari: %d teklifte yeni surum acildi.', $opened));
    }
}
