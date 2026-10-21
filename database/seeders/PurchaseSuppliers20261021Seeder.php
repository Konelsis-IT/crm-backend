<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Party\PartyKind;
use App\Enums\Party\PartyStatus;
use App\Models\Party\Party;
use App\Models\Procurement\PurchaseServiceArea;
use App\Models\Procurement\PurchaseSupplier;
use App\Models\Scopes\HidePurchaseOnlyPartiesScope;
use App\Services\Audit\ActorContext;
use App\Services\Party\PartyService;
use App\Services\Platform\SchemaReadiness;
use App\Services\Procurement\PurchaseServiceAreaService;
use App\Services\Procurement\PurchaseSupplierService;
use App\Support\TurkishFold;
use Database\Seeders\Support\ProtectedSeeder;
use Database\Seeders\Support\SeedGuard;
use Throwable;

/**
 * Satin alma tedarikci listesi, 21.10.2026 (D-232; kullanici: "Satın alma için
 * ayrı bir tedarikçi listesi oluşturalım ... Hangi alanda hizmet verdikleri de
 * belli durumda bu şekilde sisteme ekleyelim").
 *
 * - Hizmet alanlari: Excel'in grup basliklari (34), Excel sirasiyla.
 * - Firmalar: database/seeders/data/purchase_suppliers_2026_10_21.php (250
 *   firma, alanlariyla; kisa ad / uzun ad; parantez icindeki sehir / ilgili
 *   kisi / aciklama nota yazilir). Taraflar'da olmayan firma yalniz
 *   tedarikcidir; Taraflar'a eklenmez.
 * - Taraflar'da da olan 14 firma (D-232 ek; kullanici: "Birleştir. Hepsi aynı
 *   firma belli oluyor ... kısa isim uzun isim şeklinde veriyi yaz" ve
 *   "ekstra eklicez hizmet alanını var olan faaliyet hizmet alanlarını
 *   kaldırmak yok var olan yapıyı bozmak yok"): Taraflar'daki kayit kalir;
 *   tedarikci uyeligi, hizmet alanlari ve not ona eklenir, firma iki listede de
 *   gorunur. Faaliyet alanlari, roller, kisiler, notlar degismez. Kisa ve uzun
 *   ad yalniz Taraflar'daki ad hala aktarimdaki gibiyse (kisa ad bos) yazilir.
 *   Taraflar'da bulunamazsa ya da birden fazla kayit tutarsa firma yalniz
 *   tedarikci olarak eklenir (uyari yazilir).
 *
 * Satir anahtari ilk aktarimdaki firma adinin (key) katlanmis halidir. Yalniz ekler (D-165): tedarikci
 * listesinde ayni adli firma varsa o kayit benimsenir, degistirilmez; tek
 * guncelleme kullanicinin istedigi kisa / uzun ad yazimidir
 * (SeedGuard::allowingUpdates). B58 yoksa hicbir sey yapilmaz; satirlar sonraki
 * kuruluma kalir.
 */
final class PurchaseSuppliers20261021Seeder extends ProtectedSeeder
{
    public const DATA_FILE = 'seeders/data/purchase_suppliers_2026_10_21.php';

    /** @var array<string, Party>|null Katlanmis ad => tedarikci firma. */
    private ?array $suppliers = null;

    /** @var array<string, array<int, Party>>|null Katlanmis ad => Taraflar'daki kuruluslar. */
    private ?array $parties = null;

    /** @var array{created: int, joined: int, renamed: int, adopted: int, failed: int} */
    private array $totals = ['created' => 0, 'joined' => 0, 'renamed' => 0, 'adopted' => 0, 'failed' => 0];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B58')) {
            $this->command?->warn('B58 uygulanmamis; satin alma tedarikci listesi sonraki kuruluma kaldi.');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $data = self::data();
        $areas = [];

        foreach ($data['areas'] as $index => $name) {
            $area = $this->row('area:'.TurkishFold::fold($name), static fn (): PurchaseServiceArea => app(PurchaseServiceAreaService::class)->ensure($name, ($index + 1) * 10));
            $areas[TurkishFold::fold($name)] = $area instanceof PurchaseServiceArea
                ? (int) $area->getKey()
                : self::areaId($name);
        }

        foreach ($data['suppliers'] as $firm) {
            $this->row('supplier:'.TurkishFold::fold($firm['key']), function () use ($firm, $areas): ?Party {
                $areaIds = array_values(array_filter(array_map(
                    static fn (string $area): ?int => $areas[TurkishFold::fold($area)] ?? null,
                    $firm['areas'],
                )));

                try {
                    return $this->write($firm, $areaIds);
                } catch (Throwable $exception) {
                    $this->totals['failed']++;
                    $this->command?->warn(sprintf('Tedarikci "%s" yazilamadi (sonra yeniden denenecek): %s', $firm['long'], $exception->getMessage()));

                    return null;
                }
            });
        }

        $this->command?->info(sprintf(
            'Tedarikciler 21.10: %d hizmet alani; %d yeni tedarikci, %d Taraflar firmasina eklendi (%d kisa / uzun ad yazildi), %d var olan benimsendi, %d satir atlandi.',
            count($areas), $this->totals['created'], $this->totals['joined'], $this->totals['renamed'], $this->totals['adopted'], $this->totals['failed'],
        ));
    }

    /**
     * Yazmadan: alan ve firma sayisi, alan basina firma, Taraflar'a eklenecekler
     * (bulunan kayit ve ad degisikligi), tedarikci listesinde zaten bulunanlar.
     *
     * @return array{areas: int, suppliers: int, per_area: array<string, int>, joins: list<array<string, string>>, existing: list<string>}
     */
    public function preview(): array
    {
        $data = self::data();
        $perArea = array_fill_keys($data['areas'], 0);
        $joins = [];
        $existing = [];

        foreach ($data['suppliers'] as $firm) {
            foreach ($firm['areas'] as $area) {
                $perArea[$area] = ($perArea[$area] ?? 0) + 1;
            }

            if ($firm['party'] !== null) {
                $matches = $this->taraflar($firm['party']['match']);
                $party = count($matches) === 1 ? reset($matches) : null;
                $joins[] = [
                    'excel' => $firm['long'],
                    'taraflar' => $party instanceof Party ? '#'.$party->getKey().' '.$party->display_name : sprintf('bulunamadi (%d kayit)', count($matches)),
                    'names' => $party instanceof Party && self::renameable($party, $firm['party'])
                        ? sprintf('uzun: %s, kisa: %s', $firm['party']['long'], $firm['party']['short'] ?? '-')
                        : 'degismez',
                ];
            }

            if (SchemaReadiness::hasBatch('B58') && $this->existing($firm) instanceof Party) {
                $existing[] = $firm['long'];
            }
        }

        return [
            'areas' => count($data['areas']),
            'suppliers' => count($data['suppliers']),
            'per_area' => $perArea,
            'joins' => $joins,
            'existing' => $existing,
        ];
    }

    /**
     * @param  array{key: string, long: string, short: string|null, areas: list<string>, note: string|null, party: array{match: string, long: string, short: string|null}|null}  $firm
     * @param  list<int>  $areaIds
     */
    private function write(array $firm, array $areaIds): Party
    {
        if ($firm['party'] !== null) {
            $matches = $this->taraflar($firm['party']['match']);

            if (count($matches) === 1) {
                return $this->join(reset($matches), $firm, $areaIds);
            }

            $this->command?->warn(sprintf('"%s" Taraflar\'da %d kayitla eslesti; yalniz tedarikci olarak eklendi, Taraflar kaydiyla elle eslestirilmeli.', $firm['party']['match'], count($matches)));
        }

        $existing = $this->existing($firm);

        if ($existing instanceof Party) {
            $this->totals['adopted']++;

            return $existing;
        }

        $party = app(PurchaseSupplierService::class)->createSupplier([
            'display_name' => $firm['short'] ?? $firm['long'],
            'country_code' => 'TR',
            'organization_profile' => ['legal_name' => $firm['long'], 'trade_name' => $firm['short']],
        ], [
            'note' => $firm['note'],
            'service_area_ids' => $areaIds,
        ]);
        $this->suppliers[TurkishFold::fold((string) $party->display_name)] = $party;
        $this->totals['created']++;

        return $party;
    }

    /**
     * Taraflar'daki firmaya tedarikci uyeligi, hizmet alanlari ve not eklenir;
     * firmanin kendi kayitlari degismez. Uyelik zaten varsa dokunulmaz.
     *
     * @param  array{key: string, long: string, short: string|null, areas: list<string>, note: string|null, party: array{match: string, long: string, short: string|null}}  $firm
     * @param  list<int>  $areaIds
     */
    private function join(Party $party, array $firm, array $areaIds): Party
    {
        if (PurchaseSupplier::query()->where('party_id', $party->getKey())->exists()) {
            $this->totals['adopted']++;

            return $party;
        }

        if (self::renameable($party, $firm['party'])) {
            SeedGuard::allowingUpdates(static fn (): mixed => app(PartyService::class)->update($party, [
                'organization_profile' => ['legal_name' => $firm['party']['long'], 'trade_name' => $firm['party']['short']],
            ]));
            $this->totals['renamed']++;
        }

        app(PurchaseSupplierService::class)->attachExisting($party, [
            'note' => $firm['note'],
            'service_area_ids' => $areaIds,
        ]);
        $this->totals['joined']++;

        return $party;
    }

    /**
     * Ad yalniz Taraflar'daki kayit hala aktarimdaki adla duruyorsa (uzun ad
     * eslesen ad, kisa ad bos) ve yeni adlar farkliysa yazilir.
     *
     * @param  array{match: string, long: string, short: string|null}  $names
     */
    public static function renameable(Party $party, array $names): bool
    {
        $profile = $party->organizationProfile;

        if ($profile === null || filled($profile->trade_name) || TurkishFold::fold((string) $profile->legal_name) !== TurkishFold::fold($names['match'])) {
            return false;
        }

        return $profile->legal_name !== $names['long'] || $names['short'] !== null;
    }

    /**
     * Taraflar'da gorunen (birlestirilmemis) kuruluslar; gorunen ad, uzun ya da
     * kisa ad katlanmis haliyle eslesir.
     *
     * @return array<int, Party>
     */
    private function taraflar(string $name): array
    {
        if ($this->parties === null) {
            $this->parties = [];

            $rows = Party::query()
                ->where('party_kind', PartyKind::Organization->value)
                ->where('status', '!=', PartyStatus::Merged->value)
                ->with('organizationProfile')
                ->get();

            foreach ($rows as $party) {
                $names = [$party->display_name, $party->organizationProfile?->legal_name, $party->organizationProfile?->trade_name];

                foreach (array_filter($names, 'filled') as $value) {
                    $this->parties[TurkishFold::fold((string) $value)][(int) $party->getKey()] = $party;
                }
            }
        }

        return $this->parties[TurkishFold::fold($name)] ?? [];
    }

    /**
     * Tedarikci listesinde ayni adli (katlanmis gorunen ad) firma.
     *
     * @param  array{long: string, short: string|null}  $firm
     */
    private function existing(array $firm): ?Party
    {
        if ($this->suppliers === null) {
            $this->suppliers = [];

            $parties = Party::query()
                ->withoutGlobalScope(HidePurchaseOnlyPartiesScope::class)
                ->whereHas('purchaseSupplier')
                ->get(['id', 'display_name']);

            foreach ($parties as $party) {
                $this->suppliers[TurkishFold::fold((string) $party->getAttribute('display_name'))] ??= $party;
            }
        }

        return $this->suppliers[TurkishFold::fold($firm['short'] ?? $firm['long'])] ?? null;
    }

    /**
     * @return array{areas: list<string>, suppliers: list<array{key: string, long: string, short: string|null, areas: list<string>, note: string|null, party: array{match: string, long: string, short: string|null}|null}>}
     */
    public static function data(): array
    {
        /** @var array{areas: list<string>, suppliers: list<array{key: string, long: string, short: string|null, areas: list<string>, note: string|null, party: array{match: string, long: string, short: string|null}|null}>} $data */
        $data = require database_path(self::DATA_FILE);

        return $data;
    }

    /** Alan satiri arsivdeyse (onceki kurulum) alan adindan bulunur. */
    private static function areaId(string $name): ?int
    {
        $key = TurkishFold::fold($name);

        foreach (PurchaseServiceArea::query()->get(['id', 'name']) as $area) {
            if (TurkishFold::fold((string) $area->getAttribute('name')) === $key) {
                return (int) $area->getKey();
            }
        }

        return null;
    }
}
