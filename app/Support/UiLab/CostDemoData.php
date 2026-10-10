<?php

declare(strict_types=1);

namespace App\Support\UiLab;

/**
 * UI Deneme > Maliyet kalemleri (D-187, 9 Ekim 2026) deneme verisi.
 *
 * Kaynak: Kartal RES Hibrit GES 38,8 MWp maliyet Excel'i, bir kez
 * ayristirilip data/kartal-ges-maliyet.php dosyasina yazildi (hucre
 * degerleri; formuller degil). Veritabanina yazilmaz, seed verisi degildir;
 * yalniz UI Deneme'deki React prototipi okur. Gercek ekran yapilinca bu
 * klasor kaldirilabilir.
 *
 * Sekme ayrimi (kullanici istegi): Birim_Fiyat_GES_KESIF -> Urun/Hizmet;
 * "Idari Kadro ve Genel Giderler" sayfasinin A.1 Yonetim Personel Maliyeti
 * -> Idari Kadro; A.2 ... A.10 ve ikinci "A.8 Ticari Giderler" -> Genel Giderler.
 *
 * Katalog eslestirmesi (kullanicinin en buyuk uyarisi: Excel yuklenince
 * kalemler var olan katalogla eslestirilir, her seferinde yeni kayit
 * acilmaz): sistemde henuz katalog yok; burada Excel'in kendisinden
 * belirlenimci bir DENEME eslestirmesi uretilir. Ayni adli kalemler ayni
 * katalog kalemine baglanir; adi baska bir kaleme cok benzeyenler "Benzer"
 * (onay bekleyen eslesme), tek gecen bir kismi "Yeni" sayilir.
 * Departmanlar OrganizationStructureSeeder / RealOrganizationSeeder'daki
 * birim adlaridir; onay durumlari React tarafinda belirlenimci deneme verisidir.
 */
final class CostDemoData
{
    /** Onay veren birimler (birim adlari seed'deki org_units adlari; unvanlar pozisyon adlari). */
    public const DEPARTMENTS = [
        'teklif' => ['name' => 'Teklif', 'short' => 'TK', 'title' => 'Teklif grup müdürü'],
        'satin_alma' => ['name' => 'Satın Alma', 'short' => 'SA', 'title' => 'Satın alma müdürü'],
        'insaat' => ['name' => 'İnşaat', 'short' => 'İN', 'title' => 'İnşaat müdürü'],
        'elektrik' => ['name' => 'Elektrik', 'short' => 'EL', 'title' => 'Elektrik grup müdürü'],
        'proje' => ['name' => 'Proje', 'short' => 'PR', 'title' => 'Proje sorumlusu'],
        'yazilim' => ['name' => 'Yazılım', 'short' => 'YZ', 'title' => 'Otomasyon yazılım müdürü'],
        'lojistik' => ['name' => 'Lojistik', 'short' => 'LJ', 'title' => 'Lojistik birimi'],
        'muhasebe' => ['name' => 'Muhasebe', 'short' => 'MH', 'title' => 'Finans müdürü'],
        'insan_kaynaklari' => ['name' => 'İnsan Kaynakları', 'short' => 'İK', 'title' => 'İnsan kaynakları sorumlusu'],
        'yonetim' => ['name' => 'Yönetim', 'short' => 'YN', 'title' => 'İdari müdür'],
    ];

    /** Urun/Hizmet: her kalemde Teklif + Satin Alma + kategorinin teknik birimi. */
    private const DISCIPLINE = [
        'A' => 'insaat', 'B' => 'proje', 'C' => 'elektrik', 'D' => 'elektrik', 'E' => 'yazilim',
        'F' => 'elektrik', 'G' => 'proje', 'H' => 'proje', 'I' => 'proje', 'J' => 'lojistik',
    ];

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, mixed>
     */
    public static function raw(): array
    {
        if (self::$cache === null) {
            /** @var array<string, mixed> $data */
            $data = require __DIR__.'/data/kartal-ges-maliyet.php';
            self::$cache = $data;
        }

        return self::$cache;
    }

    /**
     * React prototipinin okudugu veri: kalemler + deneme katalog eslestirmesi + kur tablosu.
     *
     * @return array<string, mixed>
     */
    public static function forPrototype(): array
    {
        $data = self::raw();
        $rates = $data['icmal']['rates'];

        $products = $data['products'];
        $productLines = [];
        foreach ($products as $ci => $category) {
            $products[$ci]['departments'] = ['teklif', 'satin_alma', self::DISCIPLINE[$category['no']] ?? 'proje'];
            foreach ($category['groups'] as $gi => $group) {
                foreach ($group['items'] as $ii => $item) {
                    $productLines[] = [&$products[$ci]['groups'][$gi]['items'][$ii], $category['title'] ?? $category['name'], $group['summary_name'] ?? $group['name']];
                }
            }
        }
        self::match($productLines, 'products');

        $staff = $data['staff'];
        $staff['departments'] = ['insan_kaynaklari', 'proje', 'muhasebe'];
        $staffLines = [];
        foreach ($staff['items'] as $ii => $item) {
            $staffLines[] = [&$staff['items'][$ii], $staff['name'], $staff['name']];
        }
        self::match($staffLines, 'staff');

        $expenses = $data['expenses'];
        $expenseLines = [];
        foreach ($expenses as $gi => $group) {
            $expenses[$gi]['departments'] = ['proje', 'satin_alma', 'muhasebe'];
            foreach ($group['items'] as $ii => $item) {
                $expenseLines[] = [&$expenses[$gi]['items'][$ii], $group['name'], $group['name']];
            }
        }
        self::match($expenseLines, 'expenses');

        return [
            'source' => $data['source'],
            'rates' => [
                'date' => $data['icmal']['rates_date'],
                'offer' => 'USD',
                'TRY' => 1.0,
                'USD' => (float) $rates['USD'],
                'EUR' => (float) $rates['EURO'],
                'parity' => (float) $rates['Parite (EURO/USD)'],
                'petrol' => (float) $rates['Benzin'],
                'diesel' => (float) $rates['Dizel'],
            ],
            'departments' => self::DEPARTMENTS,
            'products' => $products,
            'staff' => $staff,
            'expenses' => $expenses,
            'overhead' => $data['overhead'],
            'icmal' => $data['icmal'],
        ];
    }

    /**
     * Deneme katalog eslestirmesi (belirlenimci). Her satira `match` yazar:
     * status matched | new | similar, katalog adi ve yolu, ayni kaleme bagli satir sayisi,
     * benzer adaylar.
     *
     * @param  list<array{0: array<string, mixed>, 1: string, 2: string}>  $lines
     */
    private static function match(array $lines, string $scope): void
    {
        $entries = [];
        $order = [];

        foreach ($lines as $line) {
            $key = self::normalize((string) $line[0]['name']);

            if ($key === '') {
                continue;
            }

            if (! isset($entries[$key])) {
                $entries[$key] = ['name' => (string) $line[0]['name'], 'path' => $line[1] === $line[2] ? $line[1] : $line[1].' › '.$line[2], 'uses' => 0];
                $order[] = $key;
            }

            $entries[$key]['uses']++;
        }

        // Benzer adlar: kendinden once kataloga girmis bir ada cok benzeyen ad.
        $similar = [];
        foreach ($order as $j => $key) {
            $candidates = [];
            for ($i = 0; $i < $j; $i++) {
                $other = $order[$i];
                similar_text($key, $other, $percent);
                $short = min(strlen($key), strlen($other));
                $contains = $short >= 6 && (str_contains($key, $other) || str_contains($other, $key)) && $short / max(strlen($key), strlen($other)) >= 0.55;

                if ($percent >= 90 || $contains) {
                    $candidates[] = ['name' => $entries[$other]['name'], 'path' => $entries[$other]['path'], 'score' => (int) round($contains ? max($percent, 80) : $percent)];
                }
            }

            if ($candidates !== []) {
                usort($candidates, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
                $similar[$key] = array_slice($candidates, 0, 3);
            }
        }

        $seen = [];
        foreach ($lines as $line) {
            $row = &$line[0];
            $key = self::normalize((string) $row['name']);
            $entry = $entries[$key] ?? ['name' => (string) $row['name'], 'path' => $line[1], 'uses' => 1];
            $first = ! isset($seen[$key]);
            $seen[$key] = true;

            if ($first && isset($similar[$key])) {
                $status = 'similar';
            } elseif ($entry['uses'] === 1 && crc32($scope.'|'.$key) % 9 === 0) {
                $status = 'new';
            } else {
                $status = 'matched';
            }

            $row['match'] = [
                'status' => $status,
                'catalog' => $entry['name'],
                'path' => $entry['path'],
                'uses' => $entry['uses'],
                'suggestions' => $status === 'similar' ? $similar[$key] : [],
            ];
            unset($row);
        }
    }

    /** Eslestirme anahtari: kucuk harf (Turkce), harf/rakam disi atilir, aksanlar sadelesir. */
    public static function normalize(string $name): string
    {
        $name = str_replace(['İ', 'I'], ['i', 'ı'], $name);
        $name = mb_strtolower($name, 'UTF-8');
        $name = strtr($name, ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'â' => 'a', 'î' => 'i', 'û' => 'u']);

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $name);
    }
}
