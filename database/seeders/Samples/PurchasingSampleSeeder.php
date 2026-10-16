<?php

declare(strict_types=1);

namespace Database\Seeders\Samples;

use App\Enums\Approval\ApprovalDecisionKind;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\CompanyPurchase;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Project\Project;
use App\Services\Approval\ApprovalRequestService;
use App\Services\Audit\ActorContext;
use App\Services\Platform\SchemaReadiness;
use App\Services\Procurement\CompanyPurchaseService;
use App\Services\Procurement\PurchaseNeedService;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Procurement\PurchaseQuotationService;
use App\Services\Procurement\PurchaseRequestService;
use App\Services\Support\TransactionRunner;
use App\Support\DisplayTime;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Satin alma ornek verisi (D-199, kullanici 13 Ekim 2026: "ornek veriler
 * olustur ama bunlar canliya gitmeyecek veriler olacak ... canlida calisacak
 * seedleri belli bir mantiga uygun tasarlamistik").
 *
 * YALNIZ YEREL: `php artisan konelsis:sample-data` ile kurulur; DatabaseSeeder,
 * START_CHAIN ve DEPLOY_SEEDERS bu sinifi hic cagirmaz (deploy.sh calistirmaz),
 * canli ortamda calismayi reddeder. D-105'in "kurgusal seed yok" kurali canli
 * zincir icin gecerlidir; ornek seed'ler yalniz database/seeders/Samples
 * altinda durur.
 *
 * Senaryo uygulamanin kendi servisleriyle (kodlar, onay siniri, durumlar,
 * sirket ici alim kaydi) kurulur; gercek kisiler kendi gorevleriyle yer alir,
 * tedarikciler serbest "Ornek ..." adlaridir (firma karti acilmaz). Her kayitta
 * "ORNEK VERI" notu vardir; `konelsis:sample-data --archive` hepsini arsive
 * alir (silme yok). Tek islemde kurulur: bir adim hata verirse hicbiri yazilmaz.
 *
 * 1. Bilgisayar ve yazici (Sirket ici, Bilgi islem): iki tedarikci teklifi,
 *    secilen teklif siparis, 4.000 TL ustu -> Yonetim kurulu baskani onayi bekliyor.
 * 2. Kirtasiye (onay gerekmez): siparis verildi, teslim alindi, tamamlandi ->
 *    Sirket ici alimlar (3 ayda bir yenileme).
 * 3. Claude aboneligi (USD, aylik): YKB onayladi, tamamlandi -> yaklasan yenileme.
 * 4. Sunucu (yillik, 2.950 TL): Satin alma muduru kendi yetkisiyle onayladi,
 *    gecen yil alindi -> iki ay icinde yenileme.
 * 5. Saha kablo ve konnektor (proje, acil): uc tedarikci (biri EUR), biri
 *    reddedildi, en uygunu secildi, YKB onayladi, siparis yolda.
 * 6. Saha trafo (proje): tedarikciden fiyat bekleniyor (taslak teklif).
 * 7. Toplanti odasi ekrani: listede olmayan urunle acik talep (islenmedi).
 */
final class PurchasingSampleSeeder extends Seeder
{
    public const MARKER = 'ÖRNEK VERİ';

    private const NOTE = 'ÖRNEK VERİ – yalnız yerel deneme verisi (php artisan konelsis:sample-data); canlıya gitmez.';

    private const PURCHASING_MANAGER = 'engin.cetin@konelsis.com';

    private const PURCHASING_OFFICER = 'kadir.topuz@konelsis.com';

    private const CHAIRMAN = 'huseyin.gunes@konelsis.com';

    private const IT = 'ozgur.ozen@konelsis.com';

    private const HR = 'merve.ucar@konelsis.com';

    private const SOFTWARE = 'omer.ermis@konelsis.com';

    private const FIELD = 'ertugrul.sahin@konelsis.com';

    public function run(): void
    {
        self::assertLocal();

        if (! SchemaReadiness::hasBatch('B53')) {
            $this->command?->warn('B53 uygulanmamis; satin alma ornek verisi kurulamaz.');

            return;
        }

        if (self::exists()) {
            $this->command?->info('Satin alma ornek verisi zaten var (once --archive ile arsive alin).');

            return;
        }

        try {
            app(TransactionRunner::class)->run(fn () => $this->scenario(), 1);
        } finally {
            app(ActorContext::class)->reset();
        }

        $this->command?->info('Satin alma ornek verisi kuruldu: 7 talep, 9 satin alma teklifi, 5 siparis, sirket ici alimlar.');
    }

    public static function exists(): bool
    {
        return PurchaseRequest::query()->where('note', 'like', self::MARKER.'%')->exists();
    }

    /**
     * Ornek kayitlari arsive alir (silme yok): acik siparis once iptal edilir
     * (onay talebi kapanir), sonra talep, kalem, teklif, siparis ve sirket ici
     * alimlar arsive gider.
     *
     * @return array<string, int>
     */
    public function archive(): array
    {
        self::assertLocal();
        $counts = ['siparis' => 0, 'teklif' => 0, 'talep' => 0, 'kalem' => 0, 'sirket_ici_alim' => 0];

        try {
            app(TransactionRunner::class)->run(function () use (&$counts): void {
                $this->as(self::PURCHASING_MANAGER, function () use (&$counts): void {
                    $orders = app(PurchaseOrderService::class);

                    foreach (PurchaseOrder::query()->where('note', 'like', self::MARKER.'%')->whereNull('archived_at')->get() as $order) {
                        if (! $order->statusEnum()->isClosed()) {
                            $orders->cancel($order, 'Örnek veri arşive alındı');
                        }

                        $orders->archive($order->refresh());
                        $counts['siparis']++;
                    }

                    foreach (PurchaseQuotation::query()->where('note', 'like', self::MARKER.'%')->whereNull('archived_at')->get() as $quotation) {
                        app(PurchaseQuotationService::class)->archive($quotation);
                        $counts['teklif']++;
                    }

                    $requests = PurchaseRequest::query()->where('note', 'like', self::MARKER.'%')->whereNull('archived_at')->get();

                    foreach (CompanyPurchase::query()->whereIn('purchase_request_id', $requests->modelKeys())->whereNull('archived_at')->get() as $purchase) {
                        app(CompanyPurchaseService::class)->archive($purchase);
                        $counts['sirket_ici_alim']++;
                    }

                    foreach ($requests as $request) {
                        foreach ($request->needs()->whereNull('archived_at')->get() as $need) {
                            app(PurchaseNeedService::class)->archive($need);
                            $counts['kalem']++;
                        }

                        app(PurchaseRequestService::class)->archive($request);
                        $counts['talep']++;
                    }
                });
            }, 1);
        } finally {
            app(ActorContext::class)->reset();
        }

        return $counts;
    }

    private function scenario(): void
    {
        $today = Carbon::now(DisplayTime::zone())->startOfDay();
        $day = static fn (int $offset): string => $today->copy()->addDays($offset)->toDateString();
        $zile = $this->project('Yozgat Zile DGES');
        $fatih = $this->project('Fatih DGES');

        // 1. Bilgisayar ve yazici: iki teklif, secilen siparis YKB onayinda.
        $computers = $this->request(self::IT, [
            'usage_area' => 'office',
            'needed_by' => $day(14),
            'justification' => 'Ekim ayında başlayan iki yeni mühendis için çalışma bilgisayarı ve bölüm yazıcısı.',
            'existing_check' => 'Depoda boşta bilgisayar yok; mevcut yazıcı arızalı ve onarımı ekonomik değil.',
        ], [
            ['catalog_item_id' => $this->item('Bilgisayar'), 'quantity' => 2, 'brand_model' => 'Dizüstü, 14"', 'specification' => 'i7 işlemci, 32 GB bellek, 1 TB SSD'],
            ['catalog_item_id' => $this->item('Yazıcı'), 'quantity' => 1, 'brand_model' => 'Renkli lazer', 'specification' => 'A4, ağ bağlantılı, çift taraflı baskı'],
        ]);
        $this->quotation($computers, 'Örnek Bilişim A.Ş.', [
            'Bilgisayar' => ['price' => 46500, 'discount' => 5],
            'Yazıcı' => ['price' => 12900],
        ], ['quote_date' => $day(-6), 'valid_until' => $day(24), 'payment_terms' => '30 gün vadeli', 'delivery_terms' => 'Genel merkeze teslim', 'lead_time_days' => 5]);
        $computerOffer = $this->quotation($computers, 'Örnek Teknoloji Ltd. Şti.', [
            'Bilgisayar' => ['price' => 44900],
            'Yazıcı' => ['price' => 13400],
        ], ['quote_date' => $day(-5), 'valid_until' => $day(25), 'payment_terms' => 'Peşin', 'delivery_terms' => 'Genel merkeze teslim', 'lead_time_days' => 3, 'general_discount' => 1500]);
        $this->select($computerOffer);
        $computerOrder = $this->order($computerOffer, [
            'delivery_address' => 'Konelsis genel merkez – Bilgi işlem',
            'delivery_contact' => 'Özgür Özen',
            'delivery_until' => $day(10),
        ]);
        $this->as(self::PURCHASING_OFFICER, fn () => app(PurchaseOrderService::class)->requestApproval($computerOrder, 'Yeni mühendisler için; en uygun teklif seçildi.'));

        // 2. Kirtasiye (onay gerekmez): tamamlandi -> Sirket ici alimlar.
        $stationery = $this->request(self::HR, [
            'usage_area' => 'office',
            'needed_by' => $day(-1),
            'justification' => 'Üç aylık ofis kırtasiye ihtiyacı.',
        ], [
            ['catalog_item_id' => $this->item('Kalem'), 'quantity' => 100],
            ['catalog_item_id' => $this->item('Defter'), 'quantity' => 40],
        ]);
        $stationeryOffer = $this->quotation($stationery, 'Örnek Kırtasiye Ltd.', [
            'Kalem' => ['price' => 18],
            'Defter' => ['price' => 85],
        ], ['quote_date' => $day(-8), 'valid_until' => $day(7), 'lead_time_days' => 2]);
        $this->select($stationeryOffer);
        $stationeryOrder = $this->order($stationeryOffer, ['delivery_address' => 'Konelsis genel merkez', 'delivery_contact' => 'Merve Uçar']);
        $this->complete($stationeryOrder, $day(-2), shipped: false);

        // 3. Claude aboneligi (USD, aylik): YKB onayi, tamamlandi.
        $claude = $this->request(self::SOFTWARE, [
            'usage_area' => 'office',
            'needed_by' => $day(-20),
            'justification' => 'Yazılım ve yapay zekâ ekibinin beş kullanıcılık Claude aboneliği.',
            'existing_check' => 'Mevcut lisans yok.',
        ], [
            ['catalog_item_id' => $this->item('Claude aboneliği'), 'quantity' => 5],
        ]);
        $claudeOffer = $this->quotation($claude, 'Örnek Yazılım Bayi A.Ş.', [
            'Claude aboneliği' => ['price' => 30],
        ], ['quote_date' => $day(-28), 'valid_until' => $day(-14), 'currency_code' => 'USD', 'try_rate' => 41.85]);
        $this->select($claudeOffer);
        $claudeOrder = $this->order($claudeOffer, ['delivery_address' => 'Çevrimiçi lisans', 'delivery_contact' => 'Ömer Faruk Ermiş']);
        $this->approve($claudeOrder);
        $this->complete($claudeOrder, $day(-25), shipped: false);

        // 4. Sunucu (yillik, 4.000 TL alti): Satin alma muduru kendi yetkisiyle onaylar.
        $server = $this->request(self::IT, [
            'usage_area' => 'office',
            'needed_by' => $day(-305),
            'justification' => 'CRM ve doküman sunucusu için bulut sunucu kiralaması.',
        ], [
            ['catalog_item_id' => $this->item('Sunucu'), 'quantity' => 1, 'specification' => '4 çekirdek, 16 GB bellek, 200 GB disk'],
        ]);
        $serverOffer = $this->quotation($server, 'Örnek Bulut Hizmetleri A.Ş.', [
            'Sunucu' => ['price' => 2950],
        ], ['quote_date' => $day(-310), 'valid_until' => $day(-290)]);
        $this->select($serverOffer);
        $serverOrder = $this->order($serverOffer, ['delivery_address' => 'Bulut hizmeti']);
        $this->as(self::PURCHASING_MANAGER, fn () => app(PurchaseOrderService::class)->requestApproval($serverOrder));
        $this->complete($serverOrder, $day(-300), shipped: false);

        // 5. Saha kablo ve konnektor: uc teklif (biri EUR), biri reddedildi, secilen siparis yolda.
        $cables = $this->request(self::FIELD, [
            'usage_area' => 'field',
            'project_id' => $zile?->getKey(),
            'needed_by' => $day(20),
            'is_urgent' => true,
            'justification' => 'DC ve AC kablo çekimi için saha ihtiyacı; montaj programı 3. hafta başlıyor.',
        ], [
            ['catalog_item_id' => $this->item('(1x150/25) mm² NA2XSY'), 'quantity' => 1500],
            ['catalog_item_id' => $this->item('1x6 mm² Pv1-F Solar Kablo'), 'quantity' => 4000],
            ['catalog_item_id' => $this->item('Konnektör'), 'quantity' => 800, 'brand_model' => 'MC4 uyumlu'],
        ]);
        $this->quotation($cables, 'Örnek Kablo Sanayi A.Ş.', [
            '(1x150/25) mm² NA2XSY' => ['price' => 410],
            '1x6 mm² Pv1-F Solar Kablo' => ['price' => 21],
            'Konnektör' => ['price' => 95],
        ], ['quote_date' => $day(-4), 'valid_until' => $day(26), 'lead_time_days' => 10, 'payment_terms' => '%30 peşin, %70 teslimde']);
        $cableOffer = $this->quotation($cables, 'Örnek Elektrik Malzemeleri Ltd.', [
            '(1x150/25) mm² NA2XSY' => ['price' => 395, 'discount' => 3],
            '1x6 mm² Pv1-F Solar Kablo' => ['price' => 23.5],
            'Konnektör' => ['price' => 88],
        ], ['quote_date' => $day(-3), 'valid_until' => $day(27), 'lead_time_days' => 7, 'payment_terms' => '45 gün vadeli', 'delivery_terms' => 'Şantiyeye teslim']);
        $foreign = $this->quotation($cables, 'Örnek Global Cable GmbH', [
            '(1x150/25) mm² NA2XSY' => ['price' => 7.9],
            '1x6 mm² Pv1-F Solar Kablo' => ['price' => 0.45],
            'Konnektör' => ['price' => 1.8, 'option' => true],
        ], ['quote_date' => $day(-2), 'valid_until' => $day(28), 'currency_code' => 'EUR', 'try_rate' => 48.2, 'lead_time_days' => 56, 'delivery_terms' => 'EXW Hamburg']);
        $this->as(self::PURCHASING_OFFICER, fn () => app(PurchaseQuotationService::class)->reject($foreign, 'Teslim süresi 8 hafta; montaj programını tutmuyor.'));
        $this->select($cableOffer);
        $cableOrder = $this->order($cableOffer, [
            'delivery_address' => 'Yozgat Zile DGES şantiyesi, malzeme sahası',
            'delivery_contact' => 'Ertuğrul Şahin',
            'delivery_from' => $day(5),
            'delivery_until' => $day(12),
        ]);
        $this->approve($cableOrder);
        $this->as(self::PURCHASING_OFFICER, function () use ($cableOrder, $day): void {
            $orders = app(PurchaseOrderService::class);
            $orders->placeOrder($cableOrder->refresh(), ['expected_delivery_on' => $day(9)]);
            $orders->markShipped($cableOrder->refresh(), ['carrier' => 'Örnek Lojistik', 'tracking_no' => 'ORN-2026-1001', 'expected_delivery_on' => $day(9)]);
        });

        // 6. Saha trafo: tedarikciden fiyat bekleniyor (taslak teklif).
        $transformers = $this->request(self::FIELD, [
            'usage_area' => 'field',
            'project_id' => $fatih?->getKey(),
            'needed_by' => $day(35),
            'justification' => 'İç ihtiyaç ve ölçü trafoları; köşk montajı için.',
        ], [
            ['catalog_item_id' => $this->item('800/400 V 10kVA İç İhtiyaç Trafosu + İç İhtiyaç Panosu'), 'quantity' => 2],
            ['catalog_item_id' => $this->item('800/400/400V 30+30VA Gerilim Trafosu'), 'quantity' => 6],
        ]);
        $this->as(self::PURCHASING_OFFICER, fn () => app(PurchaseQuotationService::class)->requestFromNeeds(
            $transformers->needs()->get(),
            ['supplier_name' => 'Örnek Trafo A.Ş.', 'quote_date' => $day(0), 'valid_until' => $day(30), 'note' => self::NOTE],
        ));

        // 7. Listede olmayan urunle acik talep.
        $this->request(self::HR, [
            'usage_area' => 'office',
            'needed_by' => $day(30),
            'justification' => 'Toplantı odasındaki projeksiyon yerine ekran; uzaktan toplantılar için.',
            'existing_check' => 'Mevcut projeksiyonun lambası bitti, yedek parça bulunmuyor.',
        ], [
            ['requested_item_name' => '65 inç toplantı odası ekranı', 'quantity' => 1, 'brand_model' => '4K, duvara montaj aparatlı', 'unit' => 'Adet'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $lines
     */
    private function request(string $requester, array $header, array $lines): PurchaseRequest
    {
        /** @var PurchaseRequest $request */
        $request = $this->as($requester, fn () => app(PurchaseRequestService::class)->create([...$header, 'note' => self::NOTE, 'lines' => $lines]));

        return $request->refresh();
    }

    /**
     * Talebin kalemlerinden teklif; fiyatlar katalog kalemi adina gore.
     *
     * @param  array<string, array{price: float|int, discount?: float|int, option?: bool}>  $prices
     * @param  array<string, mixed>  $header
     */
    private function quotation(PurchaseRequest $request, string $supplier, array $prices, array $header = []): PurchaseQuotation
    {
        $lines = [];

        foreach ($request->needs()->with('catalogItem')->get() as $need) {
            /** @var PurchaseNeed $need */
            $price = $prices[(string) $need->catalogItem?->getAttribute('name')] ?? null;

            if ($price === null) {
                continue;
            }

            $lines[] = [
                'purchase_need_id' => $need->getKey(),
                'catalog_item_id' => $need->getAttribute('catalog_item_id'),
                'description' => $need->getAttribute('title'),
                'brand_model' => $need->getAttribute('brand_model'),
                'quantity' => (float) $need->getAttribute('quantity'),
                'unit' => $need->getAttribute('unit'),
                'unit_price' => $price['price'],
                'discount_rate' => $price['discount'] ?? 0,
                'is_option' => $price['option'] ?? false,
            ];
        }

        /** @var PurchaseQuotation $quotation */
        $quotation = $this->as(self::PURCHASING_OFFICER, fn () => app(PurchaseQuotationService::class)->create([
            ...$header,
            'supplier_name' => $supplier,
            'note' => self::NOTE,
            'lines' => $lines,
        ]));

        return $quotation->refresh();
    }

    private function select(PurchaseQuotation $quotation): void
    {
        $this->as(self::PURCHASING_MANAGER, fn () => app(PurchaseQuotationService::class)->select($quotation->refresh()));
    }

    /**
     * @param  array<string, mixed>  $logistics
     */
    private function order(PurchaseQuotation $quotation, array $logistics): PurchaseOrder
    {
        /** @var PurchaseOrder $order */
        $order = $this->as(self::PURCHASING_OFFICER, fn () => app(PurchaseOrderService::class)->createFromQuotation($quotation->refresh(), [...$logistics, 'note' => self::NOTE]));

        return $order->refresh();
    }

    /** Onaya gonderir (Satin alma sorumlusu) ve Yonetim kurulu baskani onaylar. */
    private function approve(PurchaseOrder $order): void
    {
        $this->as(self::PURCHASING_OFFICER, fn () => app(PurchaseOrderService::class)->requestApproval($order->refresh(), 'Örnek onay talebi.'));
        $requestId = $order->refresh()->getAttribute('approval_request_id');

        if ($requestId === null) {
            return;
        }

        $this->as(self::CHAIRMAN, fn () => app(ApprovalRequestService::class)->decide((int) $requestId, ApprovalDecisionKind::Approved, 'Örnek onay.'));
    }

    /** Siparis verilir, (istenirse yola cikar), teslim alinir ve tamamlanir. */
    private function complete(PurchaseOrder $order, string $purchasedOn, bool $shipped): void
    {
        $this->as(self::PURCHASING_OFFICER, function () use ($order, $purchasedOn, $shipped): void {
            $orders = app(PurchaseOrderService::class);
            $order->refresh();

            if (in_array($order->statusEnum(), [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Approved], true)) {
                $orders->placeOrder($order);
            }

            if ($shipped) {
                $orders->markShipped($order->refresh(), ['carrier' => 'Örnek Kargo']);
            }

            $orders->markDelivered($order->refresh());
            $orders->complete($order->refresh(), $purchasedOn);
        });
    }

    /**
     * Islemi verilen kisi adina calistirir.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function as(string $email, Closure $callback): mixed
    {
        $id = Personnel::query()->withoutGlobalScopes()->where('email', $email)->value('id');

        if ($id === null) {
            throw new RuntimeException('Örnek veri için kişi bulunamadı: '.$email);
        }

        $actor = app(ActorContext::class);
        $actor->actAsPersonnel((int) $id);

        return $callback();
    }

    private function item(string $name): int
    {
        $id = CatalogItem::query()->where('name', $name)->value('id');

        if ($id === null) {
            throw new RuntimeException('Örnek veri için Malzeme ve Hizmetler kalemi bulunamadı: '.$name);
        }

        return (int) $id;
    }

    private function project(string $name): ?Project
    {
        return Project::query()->where('short_name', $name)->first() ?? Project::query()->where('name', 'like', $name.'%')->first();
    }

    private static function assertLocal(): void
    {
        if (app()->isProduction() || ! app()->environment('local')) {
            throw new RuntimeException('Örnek veri yalnız yerel geliştirme ortamında kurulur (APP_ENV=local).');
        }
    }
}
