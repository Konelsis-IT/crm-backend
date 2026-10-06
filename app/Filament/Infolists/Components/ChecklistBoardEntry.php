<?php

declare(strict_types=1);

namespace App\Filament\Infolists\Components;

use App\Filament\Forms\Components\ChecklistBoard;
use App\Models\Acquisition\BusinessCase;
use Filament\Infolists\Components\Entry;

/**
 * Potansiyel is detayindaki salt okunur kontrol listesi tahtasi (D-158, 5 Ekim
 * 2026 kullanici talimati: "Teklif oncesi kontrol listesi arayuzu birebir olarak
 * salt okunur sekilde potansiyel is detayinda gorulsun. Su an potansiyel is
 * detayi farkli gorunuyor ... farkli bir arayuz kafa karistiriyor").
 *
 * Duzenleme ekranindaki React tahtasinin aynisidir (ayni Blade gorunumu ve
 * checklist-board.js); hucreler tiklanmaz, yukleme dugmesi yoktur, belgeler
 * baglanti olarak acilir. Cevaplar kayittan okunur (ChecklistBoard::readOnlyConfig).
 */
class ChecklistBoardEntry extends Entry
{
    protected string $view = 'filament.forms.checklist-board';

    /** Detay sayfasinin kartlari elle kurulur; kayit semadan gelmez, burada verilir. */
    protected ?BusinessCase $case = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hiddenLabel();
    }

    public function case(BusinessCase $case): static
    {
        $this->case = $case;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBoardConfig(): array
    {
        $record = $this->case ?? $this->getRecord();

        return $record instanceof BusinessCase ? ChecklistBoard::readOnlyConfig($record) : ['templates' => [], 'labels' => [], 'readOnly' => true];
    }
}
