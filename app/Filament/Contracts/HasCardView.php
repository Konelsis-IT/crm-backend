<?php

declare(strict_types=1);

namespace App\Filament\Contracts;

/**
 * Kart gorunumu sunan sayfa (D-79). Arama, sayfa ve sayfa boyu durumu sayfanin
 * Livewire ozelliklerinde yasar; CardList'in sayfalama dugmeleri bu sozlesme
 * uzerinden sayfaya komut verir.
 */
interface HasCardView
{
    public function goToCardPage(int $page): void;
}
