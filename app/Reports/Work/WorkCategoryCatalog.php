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
        'YONETIM' => ['coordination', 'hr', 'isg', 'quality', 'receivable', 'meeting'],
        'LOJISTIK' => ['shipment', 'vehicle', 'warehouse'],
        'ATOLYE' => ['production', 'warehouse', 'maintenance'],
    ];

    /**
     * @return list<string>
     */
    public function codesFor(?string $unitCode): array
    {
        $key = strtoupper(trim((string) $unitCode));

        return self::SETS[$key] ?? self::GENERAL;
    }

    public function has(?string $unitCode, string $category): bool
    {
        return in_array($category, $this->codesFor($unitCode), true);
    }

    /**
     * Butun kodlar (tekrarsiz).
     *
     * @return list<string>
     */
    public function allCodes(): array
    {
        $codes = self::GENERAL;

        foreach (self::SETS as $set) {
            $codes = [...$codes, ...$set];
        }

        return array_values(array_unique($codes));
    }

    public function isKnown(string $category): bool
    {
        return in_array($category, $this->allCodes(), true);
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
    public function optionsFor(?string $unitCode): array
    {
        $options = [];

        foreach ($this->codesFor($unitCode) as $code) {
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
     * Departman kodu => set (React arayuzu icin; genel set `*` anahtarinda).
     *
     * @return array<string, list<string>>
     */
    public function sets(): array
    {
        return ['*' => self::GENERAL, ...self::SETS];
    }
}
