<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ReleaseNotes;
use Illuminate\Console\Command;

/**
 * Surum notlarini konsola yazar (D-166, 6 Ekim 2026 kullanici talimati:
 * "calismanin ardindan neler cozuldu bana console'da aciklarsin"). Kurulum
 * betigi (deploy.sh) sonunda yayinlanan surumun notlarini gosterir; yalniz
 * okur, hicbir sey degistirmez.
 */
final class ShowReleaseNotesCommand extends Command
{
    protected $signature = 'konelsis:notes
        {surum? : Gosterilecek surum (orn. 2.5); bos birakilirsa canlida yayinlanan son surum}';

    protected $description = 'Surum notlarini (yenilikler, iyilestirmeler, duzeltmeler) konsola yazar.';

    public function handle(): int
    {
        $version = trim((string) $this->argument('surum'));
        $release = null;

        foreach ($version === '' ? ReleaseNotes::published() : ReleaseNotes::all() as $item) {
            if ($version === '' || $item['version'] === $version) {
                $release = $item;

                break;
            }
        }

        if ($release === null) {
            $this->warn($version === '' ? 'Gosterilecek surum notu yok.' : sprintf('%s surumunun notu yok.', $version));

            return self::SUCCESS;
        }

        $this->info(sprintf('Surum %s (%s)', $release['version'], $release['date']));

        foreach ($release['groups'] as $key => $items) {
            if ($items === []) {
                continue;
            }

            $this->newLine();
            $this->line('<comment>'.__('release.groups.'.$key).'</comment>');

            foreach ($items as $text) {
                $this->line('  - '.$text);
            }
        }

        return self::SUCCESS;
    }
}
