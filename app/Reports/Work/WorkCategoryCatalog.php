<?php

declare(strict_types=1);

namespace App\Reports\Work;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Is panosu kategori setleri (B36, D-115): kategori arayuzden degil kodda
 * tanimlanir ve departmana (org_units.code) gore degisir. Sekiz bos sutun
 * yerine kartta tek bir etiket. Taninmayan departman genel seti kullanir.
 *
 * Yeni kategori: koda eklemek ve lang/{tr,en}/work_item.php `categories`
 * altina ad yazmak yeter.
 */
final class WorkCategoryCatalog
{
    /** Tanimsiz departmanlar icin genel set. */
    public const GENERAL = ['general', 'meeting', 'correspondence', 'training'];

    /**
     * Departman kodu => kategori kodlari (sira ekrandaki siradir).
     *
     * @var array<string, list<string>>
     */
    private const SETS = [
        'IS_GELISTIRME' => ['advertising', 'new_proposal', 'business_development', 'training', 'existing_work', 'rnd', 'tender_tracking'],
        'TEKLIF' => ['new_proposal', 'revised_proposal', 'costing', 'tender_tracking', 'existing_work'],
        'PROJE' => ['drawing', 'calculation', 'approval_process', 'field_support'],
        'SATIN_ALMA' => ['quote_collection', 'order', 'shipment'],
        'SAHA' => ['progress_payment', 'contract', 'site_work', 'inspection', 'commissioning'],
        'ELEKTRIK' => ['drawing', 'calculation', 'material', 'field_support'],
        'INSAAT' => ['drawing', 'calculation', 'progress_payment', 'field_support'],
        'YAZILIM' => ['support', 'development', 'maintenance'],
        'BILGI_ISLEM' => ['support', 'backup', 'hardware', 'network'],
        'MUHASEBE' => ['invoice', 'receivable', 'payment', 'reconciliation'],
        'INSAN_KAYNAKLARI' => ['hr', 'isg', 'quality', 'recruitment', 'training'],
        // 10 Ekim 2026, kullanici: Idari mudur icin Proje onay, On kabul, Gecici kabul.
        'YONETIM' => ['coordination', 'hr', 'isg', 'quality', 'receivable', 'meeting', 'project_approval', 'preliminary_acceptance', 'provisional_acceptance'],
        'LOJISTIK' => ['shipment', 'vehicle', 'warehouse'],
        'ATOLYE' => ['production', 'warehouse', 'maintenance'],
    ];

    /**
     * Gorevin kendi seti (D-217): departman setinin yerine gecer. Kisi adi
     * yok, gorev (pozisyon) kodu (ExecutiveDirectory). 15 Ekim 2026 kullanici:
     * Yonetim kurulu baskaninin calisma alani icin bu kategoriler, bu sirayla.
     *
     * @var array<string, list<string>>
     */
    private const POSITION_SETS = [
        // D-245 (23 Ekim 2026 kullanici, Huseyin Gunes'in listesi): YAZILIM, IT, DEVREYE_ALMA,
        // MEDYA, SERVIS, MUHASEBE, FINANS, IK, ISG, KALITE, SATIN_ALMA, PROJE (Taahhut),
        // IS_GELISTIRME, TEKLIF, YONETIM, HUKUK, INSAAT, ATOLYE, SAHA, BORSA, YATIRIMLAR, ROMANYA.
        'YKB' => [
            'software', 'it', 'commissioning', 'media', 'service', 'accounting', 'finance', 'hr', 'isg', 'quality',
            'purchasing', 'projects', 'business_development', 'proposal', 'management', 'legal', 'construction',
            'workshop', 'field', 'stock_market', 'investments', 'romania',
        ],
    ];

    /**
     * Listeden cikan eski kodlar (D-245): birlesik kategoriler B62 ile ilk
     * karsiligina tasinir (it_media -> it, hr_isg -> hr, software_commissioning ->
     * software, new_investments / existing_investments -> investments); Kiralar ve
     * Sirket ici duzen listede yok. Tasima uygulanana kadar kayitli deger gecerli
     * sayilir (is duzenlenince kategori silinmez) ama secim listelerinde cikmaz.
     *
     * @var list<string>
     */
    private const RETIRED = ['it_media', 'hr_isg', 'software_commissioning', 'new_investments', 'existing_investments', 'rents', 'internal_order'];

    /**
     * Birden fazla departmanda calisanin seti (D-218): gorev seti varsa o, yoksa
     * departman setlerinin birlesimi (kendi departmani once, tekrarsiz).
     *
     * @param  list<string>  $unitCodes
     * @return list<string>
     */
    public function codesForUnits(array $unitCodes, ?string $positionCode = null): array
    {
        if ($positionCode !== null && isset(self::POSITION_SETS[$positionCode])) {
            return self::POSITION_SETS[$positionCode];
        }

        $codes = [];

        foreach ($unitCodes as $unitCode) {
            $key = strtoupper(trim($unitCode));

            if (isset(self::SETS[$key])) {
                $codes = [...$codes, ...self::SETS[$key]];
            }
        }

        return $codes === [] ? self::GENERAL : array_values(array_unique($codes));
    }

    /**
     * Kisinin React setinin anahtari: gorev seti "POS:YKB", birden fazla bilinen
     * departman "UNITS:IS_GELISTIRME+TEKLIF"; tek departmanda null (departman seti).
     *
     * @param  list<string>  $unitCodes
     */
    public function personKey(array $unitCodes, ?string $positionCode): ?string
    {
        $position = $this->positionKey($positionCode);

        if ($position !== null) {
            return $position;
        }

        $known = array_values(array_unique(array_filter(array_map(
            fn (string $code): ?string => isset(self::SETS[strtoupper(trim($code))]) ? strtoupper(trim($code)) : null,
            $unitCodes,
        ))));

        return count($known) > 1 ? 'UNITS:'.implode('+', $known) : null;
    }

    /**
     * personKey() anahtarinin seti (React `category_sets` ek anahtarlari icin).
     *
     * @return list<string>
     */
    public function codesForKey(string $key): array
    {
        if (str_starts_with($key, 'UNITS:')) {
            return $this->codesForUnits(explode('+', substr($key, 6)));
        }

        return $this->sets()[$key] ?? self::GENERAL;
    }

    /** Gorev setinin `sets()` anahtari ("POS:YKB"); gorevin seti yoksa null. */
    public function positionKey(?string $positionCode): ?string
    {
        return $positionCode !== null && isset(self::POSITION_SETS[$positionCode]) ? 'POS:'.$positionCode : null;
    }

    /**
     * @return list<string>
     */
    public function codesFor(?string $unitCode, ?string $positionCode = null): array
    {
        // Gorevin kendi seti once (D-217), yoksa departman seti.
        if ($positionCode !== null && isset(self::POSITION_SETS[$positionCode])) {
            return self::POSITION_SETS[$positionCode];
        }

        $key = strtoupper(trim((string) $unitCode));

        return self::SETS[$key] ?? self::GENERAL;
    }

    public function has(?string $unitCode, string $category, ?string $positionCode = null): bool
    {
        return in_array($category, $this->codesFor($unitCode, $positionCode), true);
    }

    /**
     * Butun kodlar (tekrarsiz).
     *
     * @return list<string>
     */
    public function allCodes(): array
    {
        $codes = self::GENERAL;

        foreach ([...array_values(self::SETS), ...array_values(self::POSITION_SETS)] as $set) {
            $codes = [...$codes, ...$set];
        }

        return array_values(array_unique($codes));
    }

    public function isKnown(string $category): bool
    {
        return in_array($category, $this->allCodes(), true) || in_array($category, self::RETIRED, true);
    }

    public function label(?string $category): ?string
    {
        if ($category === null || $category === '') {
            return null;
        }

        $key = 'work_item.categories.'.$category;

        return Lang::has($key) ? (string) __($key) : Str::headline($category);
    }

    /**
     * @return array<string, string>
     */
    public function optionsFor(?string $unitCode, ?string $positionCode = null): array
    {
        $options = [];

        foreach ($this->codesFor($unitCode, $positionCode) as $code) {
            $options[$code] = (string) $this->label($code);
        }

        return $options;
    }

    /**
     * Butun kodlar => ad (filtreler ve etiketler icin).
     *
     * @return array<string, string>
     */
    public function allOptions(): array
    {
        $options = [];

        foreach ($this->allCodes() as $code) {
            $options[$code] = (string) $this->label($code);
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /**
     * Departman kodu => set (React arayuzu icin; genel set `*` anahtarinda,
     * gorev setleri "POS:<kod>" anahtarinda, D-217).
     *
     * @return array<string, list<string>>
     */
    public function sets(): array
    {
        $positions = [];

        foreach (self::POSITION_SETS as $code => $set) {
            $positions['POS:'.$code] = $set;
        }

        return ['*' => self::GENERAL, ...self::SETS, ...$positions];
    }
}
