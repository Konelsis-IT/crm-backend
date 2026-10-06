<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Party\ActivityArea;
use App\Services\Party\ActivityAreaService;
use App\Services\Platform\SchemaReadiness;
use Database\Seeders\Support\ProtectedSeeder;
use Illuminate\Database\Eloquent\Model;

/**
 * Faaliyet alanlarinin ilk listesi (B33, D-107; 21 Eylul 2026 kullanici
 * karari). Liste Ayarlar > Faaliyet alanlari ekranindan genisletilir.
 *
 * Idempotent: kod ile bulunur; var olan alanin adi, sirasi ve durumu
 * DEGISTIRILMEZ (kullanicinin duzeltmeleri korunur), yalniz eksik alan
 * eklenir. Kalici uretim verisidir; uretimde de calisir.
 *
 * D-165: her ana ve alt alan ayri satirdir; islenen satir seed arsivine
 * duser ve bir daha islenmez (canlida silinen alan geri gelmez).
 */
class ActivityAreaSeeder extends ProtectedSeeder
{
    /**
     * Ana alan kodu => [ad TR, ad EN, alt alanlar: kod => [ad TR, ad EN]].
     *
     * @var array<string, array{0: string, 1: string, 2: array<string, array{0: string, 1: string}>}>
     */
    public const AREAS = [
        'EQUIPMENT' => ['Ekipman tedariki', 'Equipment supply', [
            'EQUIPMENT_TURBINE' => ['Türbin', 'Turbine'],
            'EQUIPMENT_GENERATOR' => ['Jeneratör', 'Generator'],
            'EQUIPMENT_INVERTER' => ['İnverter', 'Inverter'],
            'EQUIPMENT_PV_PANEL' => ['Güneş paneli', 'PV panel'],
            'EQUIPMENT_BATTERY' => ['Batarya / depolama sistemi', 'Battery / storage system'],
            'EQUIPMENT_TRANSFORMER' => ['Trafo', 'Transformer'],
            'EQUIPMENT_MOUNTING' => ['Konstrüksiyon (montaj yapısı)', 'Mounting structure'],
        ]],
        'ENGINEERING' => ['Mühendislik ve proje', 'Engineering and design', [
            'ENGINEERING_DESIGN' => ['Proje / tasarım', 'Design'],
            'ENGINEERING_AUTOMATION' => ['Otomasyon / SCADA', 'Automation / SCADA'],
            'ENGINEERING_CONSULTING' => ['Danışmanlık', 'Consulting'],
        ]],
        'ENVIRONMENT' => ['Çevre ve izin', 'Environment and permits', [
            'ENVIRONMENT_EIA' => ['ÇED danışmanlığı', 'EIA consulting'],
        ]],
        'INVESTMENT' => ['Yatırım ve üretim', 'Investment and generation', [
            'INVESTMENT_INVESTOR' => ['Yatırımcı', 'Investor'],
            'INVESTMENT_EPC' => ['EPC yüklenici', 'EPC contractor'],
            'INVESTMENT_GROUP' => ['Büyük grup / holding', 'Large group / holding'],
        ]],
    ];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B33')) {
            $this->command?->warn('Faaliyet alani tablolari (B33) yok; ActivityAreaSeeder atlandi.');

            return;
        }

        $service = app(ActivityAreaService::class);
        $created = 0;
        $order = 0;

        foreach (self::AREAS as $code => [$tr, $en, $children]) {
            $order += 10;
            $parentOrder = $order;

            $this->row('activity-area:'.$code, static function () use ($service, $code, $tr, $en, $parentOrder, &$created): Model {
                $existing = ActivityArea::query()->where('code', $code)->first();

                if ($existing !== null) {
                    return $existing;
                }

                $parent = $service->create(['code' => $code, 'name_tr' => $tr, 'name_en' => $en, 'sort_order' => $parentOrder, 'status' => 'active']);
                $created++;

                return $parent;
            });

            $childOrder = 0;

            foreach ($children as $childCode => [$childTr, $childEn]) {
                $childOrder += 10;
                $sortOrder = $childOrder;

                // D-165: alt alan ayri satirdir; ust alan arsivde olabilir, koddan okunur.
                $this->row('activity-area:'.$childCode, static function () use ($service, $code, $childCode, $childTr, $childEn, $sortOrder, &$created): ?Model {
                    $existing = ActivityArea::query()->where('code', $childCode)->first();

                    if ($existing !== null) {
                        return $existing;
                    }

                    $parentId = ActivityArea::query()->where('code', $code)->value('id');

                    if ($parentId === null) {
                        return null;
                    }

                    $child = $service->create([
                        'parent_id' => $parentId,
                        'code' => $childCode,
                        'name_tr' => $childTr,
                        'name_en' => $childEn,
                        'sort_order' => $sortOrder,
                        'status' => 'active',
                    ]);
                    $created++;

                    return $child;
                });
            }
        }

        $this->command?->info(sprintf('Faaliyet alanlari: %d yeni alan eklendi.', $created));
    }
}
