<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\DisplayTime;
use App\Support\Money;
use App\Support\UiLab\CostDemoData;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * UI Deneme > Maliyet kalemleri React prototipinin yapilandirmasi (D-187).
 * Sayfa bunu data-config olarak verir (filament.dashboards.app gorunumu,
 * D-173 ile ayni baglama kalibi); React (resources/js/dashboards/cost-lab.js)
 * yalniz okur. Veri App\Support\UiLab\CostDemoData: Excel'den bir kez
 * ayristirilmis kalemler; veritabani okunmaz ve yazilmaz.
 *
 * Etiketler lang/{tr,en}/cost_lab.php `ui`; simgeler Heroicon'dan sunucuda SVG.
 */
final class CostLabConfig
{
    /** Arayuz simgeleri (React adlariyla). */
    private const ICONS = [
        'search' => Heroicon::OutlinedMagnifyingGlass,
        'close' => Heroicon::OutlinedXMark,
        'check' => Heroicon::OutlinedCheckCircle,
        'reject' => Heroicon::OutlinedXCircle,
        'clock' => Heroicon::OutlinedClock,
        'note' => Heroicon::OutlinedChatBubbleBottomCenterText,
        'chevron' => Heroicon::OutlinedChevronRight,
        'expand' => Heroicon::OutlinedChevronDoubleDown,
        'collapse' => Heroicon::OutlinedChevronDoubleUp,
        'filter' => Heroicon::OutlinedFunnel,
        'catalog' => Heroicon::OutlinedBookOpen,
        'new' => Heroicon::OutlinedSparkles,
        'similar' => Heroicon::OutlinedQuestionMarkCircle,
        'link' => Heroicon::OutlinedLink,
        'upload' => Heroicon::OutlinedArrowUpTray,
        'excel' => Heroicon::OutlinedTableCells,
        'product' => Heroicon::OutlinedCube,
        'staff' => Heroicon::OutlinedUserGroup,
        'expense' => Heroicon::OutlinedReceiptPercent,
        'summary' => Heroicon::OutlinedPresentationChartBar,
        'rates' => Heroicon::OutlinedCurrencyDollar,
        'shield' => Heroicon::OutlinedShieldCheck,
        'info' => Heroicon::OutlinedInformationCircle,
        'warning' => Heroicon::OutlinedExclamationTriangle,
        'bolt' => Heroicon::OutlinedBolt,
        'save' => Heroicon::OutlinedCheck,
        'density' => Heroicon::OutlinedBars3,
        'chart' => Heroicon::OutlinedChartBar,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function make(): array
    {
        $labels = __('cost_lab.ui');

        return [
            'app' => 'cost-lab',
            'locale' => app()->getLocale(),
            // D-180: tutarlar ISO kodu yerine simgeyle (App\Support\Money ile ayni tablo).
            'currency_symbols' => Money::SYMBOLS,
            'labels' => is_array($labels) ? $labels : [],
            'generated_at' => Carbon::now(DisplayTime::zone())->format('d.m.Y H:i'),
            'icons' => array_map(static fn (Heroicon $icon): string => self::svg($icon), self::ICONS),
            'data' => CostDemoData::forPrototype(),
        ];
    }

    /** Heroicon'u satir ici SVG olarak verir (kaynak Filament; DashboardAppConfig ile ayni yol). */
    private static function svg(Heroicon $icon): string
    {
        try {
            return trim(svg($icon->getIconForSize(IconSize::Medium))->toHtml());
        } catch (Throwable) {
            return '';
        }
    }
}
