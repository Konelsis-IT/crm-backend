<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\ReleaseNotes;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/**
 * Surum notlari penceresinin icerigi (D-91): her surum ayri bir acilir
 * bolumdur; en yeni surum acik, gecmis surumler kapali gelir ve ayni
 * pencereden acilabilir. Yayin tarihi gelmemis surum hic gosterilmez.
 *
 * Her grup (yeni ozellik / iyilestirme / duzeltme / calisma notu) kendi
 * simgesi ve rengiyle ayri bir alt bolumdur; boylece uzun listede ne
 * okudugunuz bir bakista ayirt edilir.
 */
final class ReleaseNotesSchema
{
    /**
     * Grup anahtari => [simge, renk].
     *
     * @return array<string, array{icon: Heroicon, color: string}>
     */
    private static function groupStyles(): array
    {
        return [
            ReleaseNotes::FEATURES => ['icon' => Heroicon::OutlinedSparkles, 'color' => 'success'],
            ReleaseNotes::IMPROVEMENTS => ['icon' => Heroicon::OutlinedArrowTrendingUp, 'color' => 'info'],
            ReleaseNotes::FIXES => ['icon' => Heroicon::OutlinedWrenchScrewdriver, 'color' => 'warning'],
            ReleaseNotes::NOTES => ['icon' => Heroicon::OutlinedInformationCircle, 'color' => 'gray'],
        ];
    }

    /**
     * @return array<int, Component>
     */
    public static function components(): array
    {
        $styles = self::groupStyles();
        $sections = [];

        foreach (ReleaseNotes::published() as $index => $release) {
            $slug = str_replace('.', '_', $release['version']);
            $groups = [];

            foreach ($release['groups'] as $key => $lines) {
                if ($lines === []) {
                    continue;
                }

                $style = $styles[$key] ?? ['icon' => Heroicon::OutlinedInformationCircle, 'color' => 'gray'];

                $groups[] = Section::make(__('release.groups.'.$key))
                    ->icon($style['icon'])
                    ->iconColor($style['color'])
                    ->compact()
                    ->secondary()
                    ->columns(1)
                    ->components([
                        TextEntry::make(sprintf('release_%s_%s', $slug, $key))
                            ->hiddenLabel()
                            ->label(__('release.groups.'.$key))
                            ->state($lines)
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->columnSpanFull(),
                    ]);
            }

            $sections[] = Section::make(__('release.version', [
                'version' => $release['version'],
                'date' => $release['date'],
            ]))
                ->icon($index === 0 ? Heroicon::OutlinedRocketLaunch : Heroicon::OutlinedClock)
                ->iconColor($index === 0 ? 'primary' : 'gray')
                ->description($index === 0 ? __('release.latest') : null)
                ->collapsible()
                ->collapsed($index > 0)
                ->columns(1)
                ->components($groups);
        }

        return $sections;
    }
}
