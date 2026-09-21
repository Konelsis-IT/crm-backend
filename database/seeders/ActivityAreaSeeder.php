<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Party\ActivityArea;
use App\Services\Party\ActivityAreaService;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Seeder;

/**
 * Faaliyet alanlarinin ilk listesi (B33, D-107; 21 Eylul 2026 kullanici
 * karari). Liste Ayarlar > Faaliyet alanlari ekranindan genisletilir.
 *
 * Idempotent: kod ile bulunur; var olan alanin adi, sirasi ve durumu
 * DEGISTIRILMEZ (kullanicinin duzeltmeleri korunur), yalniz eksik alan
 * eklenir. Kalici uretim verisidir; uretimde de calisir.
 */
class ActivityAreaSeeder extends Seeder
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
            $parent = ActivityArea::query()->where('code', $code)->first();

            if ($parent === null) {
                /** @var ActivityArea $parent */
                $parent = $service->create(['code' => $code, 'name_tr' => $tr, 'name_en' => $en, 'sort_order' => $order, 'status' => 'active']);
                $created++;
            }

            $childOrder = 0;

            foreach ($children as $childCode => [$childTr, $childEn]) {
                $childOrder += 10;

                if (ActivityArea::query()->where('code', $childCode)->exists()) {
                    continue;
                }

                $service->create([
                    'parent_id' => $parent->getKey(),
                    'code' => $childCode,
                    'name_tr' => $childTr,
                    'name_en' => $childEn,
                    'sort_order' => $childOrder,
                    'status' => 'active',
                ]);
                $created++;
            }
        }

        $this->command?->info(sprintf('Faaliyet alanlari: %d yeni alan eklendi.', $created));
    }
}
