<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Project\CriticalityProfile;
use App\Enums\Project\ProjectStatus;
use App\Enums\Shared\ActiveStatus;
use App\Models\Party\Party;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Reference\Country;
use App\Services\Audit\ActorContext;
use App\Services\Party\PartyRoleService;
use App\Services\Party\PartyService;
use App\Services\Platform\SchemaReadiness;
use App\Services\Project\ProjectScopeService;
use App\Services\Project\ProjectService;
use App\Services\Support\TransactionRunner;
use App\Support\Projects\ProjectNames;
use Database\Seeders\Support\ProtectedSeeder;
use Database\Seeders\Support\SeedGuard;
use Illuminate\Support\Str;

/**
 * Devam eden projeler listesi, 08.10.2026 (D-174, 8 Ekim 2026 kullanici
 * talimati). Kullanici 13 projeyi "Isveren - (yurt disiysa ulke) - Proje adi
 * (tipler)" bicimiyle verdi; Excel sutun basliklari (MNG F3, KAYSERI, METGUN,
 * MIR ...) kayit acmaz.
 *
 * - Verilen proje adi KISA ADDIR ("o kisa isimleri boyle dolduracagiz").
 *   Lisans adi (projects.name) bilinmedigi icin simdilik kisa adla ayni
 *   yazilir; aciklamada "Lisans adi elle duzeltilecek" notu vardir.
 * - Hepsi devam eden proje: durum Aktif (ProjectStatus::Active).
 * - Tipler: BESS = bes, GES = ges, EIH = enh_eih (proje kapsam satiri, B48).
 * - Taraflar adla bulunur (asla id ile degil), birlestirilmis taraf hedefe
 *   izlenir (FirmaTakipUpdate20261007Seeder::findParty). Bulunamayanlar
 *   kullanicinin verdigi adla yeni taraf olur: belediyeler mevcut duzende
 *   "Resmi kurum" (authority), digerleri Isveren (employer).
 * - Kod (PRJ-YYYY-NNNN) normal ayiriciyla, ProjectService::createDirect
 *   icinde verilir.
 * - Liberya (LR) ulke listesinde yoksa eklenir (yalniz ekleme).
 *
 * D-175 (8 Ekim 2026 kullanici duzeltmesi: "Proje yoneticisini Ersin Ozdemir
 * yapmissin ama yanlis ... Ersin Bey sadece is gelistirme alaninda"):
 * - Her satirin proje muduru kullanicinin verdigi kisidir (MANAGERS, e-posta
 *   ya da adla bulunur; Ersin Ozdemir hicbir projeye yazilmaz). createDirect
 *   projenin is kaydinin sahibini de proje muduru yapar.
 * - Ad duzeltmeleri: "Giren Ada DGES" -> "Giresun Ada DGES", "Mercan Kimse GES"
 *   -> "Mercan Kimya GES" (RENAMES). Satir anahtari eski adla kalir ('key'),
 *   boylece eski satiri isleyen ortam projeyi ikinci kez acmaz; var olan proje
 *   eski ya da yeni adla benimsenir ('aliases').
 * - Yeni satir: "MIR YAPI - Romanya - Salcia Tudor Wind" (proje muduru Eda Nur
 *   Yilmaz). Kullanici tip vermedi; addaki "Wind" nedeniyle RES yazilir.
 * - Eski veriyle kurulmus ortamlarin duzeltmesi ProjectManagers20261008Seeder'dadir.
 *
 * Her proje bir satirdir (anahtar kisa ad); satir kendi transaction'inda
 * yazilir, hata verirse geri alinir ve sonraki kurulumda yeniden denenir.
 * Var olan proje (ayni lisans ya da kisa ad) degistirilmez, benimsenir.
 * B48 uygulanmadan calismaz (kisa ad kolonu ve proje tipleri gerekir).
 * preview() hicbir sey yazmaz ve kod ayirmaz. Kurulumda DEPLOY_SEEDERS ile calisir.
 */
final class Projects20261008Seeder extends ProtectedSeeder
{
    public const LIST_DATE = '08.10.2026';

    /**
     * Proje mudurleri (D-175): e-posta => kullanicinin yazdigi ad
     * (RealPersonnelSeeder). E-posta degismisse adla bulunur.
     *
     * @var array<string, string>
     */
    public const MANAGERS = [
        'edanur.yilmaz@konelsis.com' => 'Eda Nur Yılmaz',
        'melih.kol@konelsis.com' => 'Melih Kol',
        'murat.toksoy@konelsis.com' => 'Murat Toksoy',
    ];

    /**
     * Kullanicinin ad duzeltmeleri (D-175): eski kisa ad => yeni kisa ad.
     *
     * @var array<string, string>
     */
    public const RENAMES = [
        'Giren Ada DGES' => 'Giresun Ada DGES',
        'Mercan Kimse GES' => 'Mercan Kimya GES',
    ];

    /**
     * Kisa ad => [listedeki satir, taraf adlari (ilk bulunan), yeni taraf
     * [ad, rol, ulke] ya da null, proje ulkesi, tipler, proje tipi kodu,
     * proje muduru e-postasi; istege bagli: satir anahtari (eski ad), eski
     * adlar, aciklama notu].
     *
     * @var array<string, array{line: string, parties: list<string>, new: array{0: string, 1: string, 2: string}|null, country: string, types: list<string>, type_code: string|null, manager: string, key?: string, aliases?: list<string>, note?: string}>
     */
    public const PROJECTS = [
        'Mng Phase 3' => [
            'line' => 'Mng - Liberya - Mng Phase 3',
            'parties' => ['MNG'],
            'new' => null,
            'country' => 'LR',
            'types' => [],
            'type_code' => null,
            'manager' => 'melih.kol@konelsis.com',
        ],
        'Sepsi Solar' => [
            'line' => 'Konelsis - Romanya - Sepsi Solar',
            'parties' => ['KONELSİS'],
            // Kullanici karari: "Konelsis (Türkiye) tarafı".
            'new' => ['KONELSİS', 'employer', 'TR'],
            'country' => 'RO',
            'types' => [],
            'type_code' => null,
            'manager' => 'edanur.yilmaz@konelsis.com',
        ],
        'Yozgat Zile DGES' => [
            'line' => 'Mor Yatırım - Yozgat Zile DGES (BESS + GES)',
            'parties' => ['MOR YATIRIM ENERJİ SAN.TİC.A.Ş.'],
            'new' => null,
            'country' => 'TR',
            'types' => ['ges', 'bes'],
            'type_code' => 'GES',
            'manager' => 'edanur.yilmaz@konelsis.com',
        ],
        'Giresun Ada DGES' => [
            // D-175: kullanici duzeltmesi ("Giren" yazim hatasi); anahtar eski adla kalir.
            'line' => 'Mor Yatırım - Giresun Ada DGES (BESS + GES)',
            'parties' => ['MOR YATIRIM ENERJİ SAN.TİC.A.Ş.'],
            'new' => null,
            'country' => 'TR',
            'types' => ['ges', 'bes'],
            'type_code' => 'GES',
            'manager' => 'edanur.yilmaz@konelsis.com',
            'key' => 'Giren Ada DGES',
            'aliases' => ['Giren Ada DGES'],
        ],
        'Fatih DGES' => [
            'line' => 'Ankatech (Aksa) - Fatih DGES (BESS + GES)',
            'parties' => ['ANKATECH ENERJİ MÜHENDİSLİK MÜŞAVİRLİK ANONİM ŞİRKETİ'],
            'new' => null,
            'country' => 'TR',
            'types' => ['ges', 'bes'],
            'type_code' => 'GES',
            'manager' => 'murat.toksoy@konelsis.com',
        ],
        'Gölbaşı w-1' => [
            'line' => 'Gölbaşı Belediyesi - Gölbaşı w-1',
            'parties' => ['GÖLBAŞI BELEDİYESİ'],
            'new' => ['GÖLBAŞI BELEDİYESİ', 'authority', 'TR'],
            'country' => 'TR',
            'types' => [],
            'type_code' => null,
            'manager' => 'edanur.yilmaz@konelsis.com',
        ],
        'Salihli w-1' => [
            'line' => 'Salihli Belediyesi - Salihli w-1',
            'parties' => ['SALİHLİ BELEDİYESİ'],
            'new' => ['SALİHLİ BELEDİYESİ', 'authority', 'TR'],
            'country' => 'TR',
            'types' => [],
            'type_code' => null,
            'manager' => 'edanur.yilmaz@konelsis.com',
        ],
        'Alaşehir w-1' => [
            'line' => 'Alaşehir Belediyesi - Alaşehir w-1',
            'parties' => ['ALAŞEHİR BELEDİYESİ'],
            'new' => ['ALAŞEHİR BELEDİYESİ', 'authority', 'TR'],
            'country' => 'TR',
            'types' => [],
            'type_code' => null,
            'manager' => 'edanur.yilmaz@konelsis.com',
        ],
        'Yozgat w-1' => [
            'line' => 'Yozgat Belediyesi - Yozgat w-1',
            'parties' => ['YOZGAT BELEDİYESİ'],
            'new' => ['YOZGAT BELEDİYESİ', 'authority', 'TR'],
            'country' => 'TR',
            'types' => [],
            'type_code' => null,
            'manager' => 'edanur.yilmaz@konelsis.com',
        ],
        'Mercan Kimya GES' => [
            // D-175: kullanici duzeltmesi ("Kimse" yazim hatasi); anahtar eski adla kalir.
            'line' => 'MIR YAPI (Romanya) - Mercan Kimya GES (GES)',
            'parties' => ['MIR YAPI'],
            'new' => ['MIR YAPI', 'employer', 'RO'],
            // Bicimdeki ulke yurt disi projeyi soyler: proje de Romanya'da.
            'country' => 'RO',
            'types' => ['ges'],
            'type_code' => 'GES',
            'manager' => 'edanur.yilmaz@konelsis.com',
            'key' => 'Mercan Kimse GES',
            'aliases' => ['Mercan Kimse GES'],
        ],
        'Elektraverde 1' => [
            'line' => 'Electra One S.R.L. - Elektraverde 1',
            'parties' => ['Electra One S.R.L.'],
            'new' => ['Electra One S.R.L.', 'employer', 'TR'],
            'country' => 'TR',
            'types' => [],
            'type_code' => null,
            'manager' => 'edanur.yilmaz@konelsis.com',
        ],
        'Elektraverde 2' => [
            // Kullanicinin yazdigi gibi ("Elecktra Two S.R.L"); olasi yazim hatasi elle duzeltilir.
            'line' => 'Elecktra Two S.R.L - Elektraverde 2',
            'parties' => ['Elecktra Two S.R.L'],
            'new' => ['Elecktra Two S.R.L', 'employer', 'TR'],
            'country' => 'TR',
            'types' => [],
            'type_code' => null,
            'manager' => 'edanur.yilmaz@konelsis.com',
        ],
        'Alıç DGES EİH' => [
            'line' => 'Aksa Yen.E.Ü.A.Ş. - Alıç DGES EİH (BESS + GES + EİH)',
            // D-170 sonrasi uzun ad; oncesinde eski ad.
            'parties' => ['AKSA YENİLENEBİLİR ENERJİ ÜRETİM A.Ş.', 'Aksa Yenilenebilir Enerji Üretim Anonim Şirketi'],
            'new' => null,
            'country' => 'TR',
            'types' => ['ges', 'bes', 'enh_eih'],
            'type_code' => 'GES',
            'manager' => 'murat.toksoy@konelsis.com',
        ],
        'Salcia Tudor Wind' => [
            // D-175: kullanicinin ekledigi yeni proje; isveren D-174'te acilan MIR YAPI (RO).
            'line' => 'MIR YAPI - Romanya - Salcia Tudor Wind',
            'parties' => ['MIR YAPI'],
            'new' => ['MIR YAPI', 'employer', 'RO'],
            'country' => 'RO',
            // Kullanici tip vermedi; "Wind" = ruzgar -> RES.
            'types' => ['res'],
            'type_code' => 'RES',
            'manager' => 'edanur.yilmaz@konelsis.com',
            'note' => 'Proje tipi listede verilmedi; adındaki "Wind" nedeniyle RES seçildi, gerekirse elle düzeltilecek.',
        ],
    ];

    /** Ulke listesinde olmayabilecek ulkeler: kod => [iso3, Turkce ad, Ingilizce ad, saat dilimi]. */
    private const COUNTRIES = [
        'LR' => ['LBR', 'Liberya', 'Liberia', 'Africa/Monrovia'],
    ];

    private bool $dry = false;

    /** @var array<string, int> */
    private array $totals = ['projects' => 0, 'adopted' => 0, 'parties' => 0, 'countries' => 0, 'skipped' => 0];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B48') || ! SchemaReadiness::hasBatch('B17')) {
            $this->command?->warn('B48 (proje kisa adi ve proje tipleri) uygulanmamis; devam eden projeler 08.10 atlandi.');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        foreach (self::COUNTRIES as $code => $country) {
            $this->row('country:'.$code, function () use ($code, $country): ?Country {
                try {
                    return app(TransactionRunner::class)->run(fn (): Country => $this->country($code, $country), 1);
                } catch (\Throwable $exception) {
                    $this->command?->warn(sprintf('Ulke %s eklenemedi (sonra yeniden denenecek): %s', $code, $exception->getMessage()));

                    return null;
                }
            });
        }

        foreach (self::PROJECTS as $short => $row) {
            $this->row(self::rowKey($short), function () use ($short, $row): ?Project {
                try {
                    return SeedGuard::allowingUpdates(fn (): ?Project => app(TransactionRunner::class)->run(
                        fn (): ?Project => $this->process($short, $row)['project'],
                        1,
                    ));
                } catch (\Throwable $exception) {
                    $this->totals['skipped']++;
                    $this->command?->warn(sprintf('Devam eden projeler 08.10 satir "%s" atlandi (sonra yeniden denenecek): %s', $short, $exception->getMessage()));

                    return null;
                }
            });
        }

        $this->command?->info(sprintf(
            'Devam eden projeler 08.10: %d yeni proje, %d var olan proje benimsendi, %d yeni taraf, %d ulke; %d satir atlandi.',
            $this->totals['projects'], $this->totals['adopted'], $this->totals['parties'], $this->totals['countries'], $this->totals['skipped'],
        ));
    }

    /**
     * Yazmadan ve kod ayirmadan: her satirin tarafi, tipleri, ulkesi, proje
     * muduru ve satirin seed arsivinde olup olmadigi.
     *
     * @return list<array<string, mixed>>
     */
    public function preview(): array
    {
        $this->dry = true;
        $out = [];

        foreach (self::PROJECTS as $short => $row) {
            try {
                $result = $this->process($short, $row);
                unset($result['project']);
            } catch (\Throwable $exception) {
                $result = ['error' => $exception->getMessage()];
            }

            $out[] = [
                'short_name' => $short,
                'row_key' => self::rowKey($short),
                'in_seed_archive' => $this->isArchived(self::rowKey($short)),
                'line' => $row['line'],
                ...$result,
            ];
        }

        $this->dry = false;

        return $out;
    }

    /** Satir anahtari: kisa ad, adi duzeltilen satirda eski ad (D-175). */
    public static function rowKey(string $short): string
    {
        return 'project:'.self::key(self::PROJECTS[$short]['key'] ?? $short);
    }

    /**
     * Proje mudurunun personel kimligi: e-postayla, bulunamazsa adla (D-175).
     */
    public static function managerId(string $email): ?int
    {
        $id = Personnel::query()->where('email', $email)->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        $name = self::MANAGERS[$email] ?? null;

        if ($name === null) {
            return null;
        }

        $key = FirmaTakipUpdate20261007Seeder::personKey($name);

        foreach (Personnel::query()->get(['id', 'full_name']) as $person) {
            if (FirmaTakipUpdate20261007Seeder::personKey((string) $person->full_name) === $key) {
                return (int) $person->getKey();
            }
        }

        return null;
    }

    /**
     * Ayni lisans adi ya da kisa adla var olan proje (yeniden acilmaz); adi
     * duzeltilen satirda eski adla da aranir (D-175).
     */
    public static function existingProject(string $short): ?Project
    {
        $keys = array_map(self::key(...), [$short, ...(self::PROJECTS[$short]['aliases'] ?? [])]);

        foreach (Project::query()->orderBy('id')->get([...ProjectNames::columns(), 'customer_party_id', 'project_manager_employee_id', 'business_case_id']) as $project) {
            if (in_array(self::key((string) $project->name), $keys, true) || in_array(self::key((string) $project->short_name), $keys, true)) {
                return $project;
            }
        }

        return null;
    }

    /** Karsilastirma anahtari: Turkce kucuk harf, harf ve rakam disi atilir. */
    public static function key(string $text): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], Str::squish($text)), 'UTF-8'));
    }

    /**
     * @param  array{line: string, parties: list<string>, new: array{0: string, 1: string, 2: string}|null, country: string, types: list<string>, type_code: string|null, manager: string, key?: string, aliases?: list<string>, note?: string}  $row
     * @return array{project: Project|null, party: string, existing_project: string|null, country: string, types: list<string>, type_code: string|null, manager: string}
     */
    private function process(string $short, array $row): array
    {
        $country = $row['country'];
        $countryText = $country.(Country::query()->whereKey($country)->exists() ? '' : ' (ülke listesine eklenecek)');
        $managerId = self::managerId($row['manager']);
        $result = [
            'project' => null,
            'party' => '',
            'existing_project' => null,
            'country' => $countryText,
            'types' => $row['types'],
            'type_code' => $row['type_code'],
            'manager' => $managerId === null ? 'BULUNAMADI: '.$row['manager'] : '#'.$managerId.' '.(self::MANAGERS[$row['manager']] ?? $row['manager']),
        ];

        $existing = self::existingProject($short);

        if ($existing !== null) {
            $result['existing_project'] = '#'.$existing->getKey().' '.$existing->name;
            $result['party'] = '#'.$existing->customer_party_id.' (var olan projenin işvereni)';

            if (! $this->dry) {
                $this->totals['adopted']++;
            }

            return [...$result, 'project' => $existing];
        }

        if ($managerId === null) {
            throw new \RuntimeException('Proje muduru bulunamadi: '.$row['manager']);
        }

        $party = (new FirmaTakipUpdate20261007Seeder)->findParty($row['parties']);

        if ($party === null && $row['new'] === null) {
            throw new \RuntimeException('Taraf bulunamadi: '.implode(' / ', $row['parties']));
        }

        if ($party === null) {
            [$name, $role, $partyCountry] = $row['new'];
            $result['party'] = 'YENİ: '.$name.' ('.$role.', '.$partyCountry.')';

            if ($this->dry) {
                return $result;
            }

            $party = $this->createParty($name, $role, $partyCountry);
        } else {
            $result['party'] = '#'.$party->getKey().' '.$party->display_name;
        }

        if ($this->dry) {
            return $result;
        }

        if (! Country::query()->whereKey($country)->exists() && isset(self::COUNTRIES[$country])) {
            $this->country($country, self::COUNTRIES[$country]);
        }

        // createDirect projenin is kaydinin sahibini de proje muduru yapar (D-175).
        $project = app(ProjectService::class)->createDirect([
            'name' => $short,
            'short_name' => $short,
            'customer_party_id' => (int) $party->getKey(),
            'project_manager_employee_id' => $managerId,
            'project_type_code' => $row['type_code'] ?? ProjectScopeService::componentCodeFor($row['types']),
            'criticality_profile' => CriticalityProfile::Standard->value,
            'status' => ProjectStatus::Active->value,
            'country_code' => $country,
            'site_country_code' => $country,
            'currency_code' => (string) config('konelsis.organization.default_currency', 'TRY'),
            'description' => $this->description($row),
            'scope_types' => $row['types'],
            'scopes' => [],
        ]);

        $this->totals['projects']++;

        return [...$result, 'project' => $project];
    }

    /**
     * @param  array{line: string, note?: string}  $row
     */
    private function description(array $row): string
    {
        return implode("\n", array_filter([
            'Devam eden projeler listesinden aktarıldı ('.self::LIST_DATE.').',
            'Listedeki satır: '.$row['line'],
            'Lisans adı bilinmediği için kısa adla aynı yazıldı; lisans adı elle düzeltilecek (08.10.2026 listesi).',
            $row['note'] ?? null,
        ]));
    }

    private function createParty(string $name, string $role, string $country): Party
    {
        /** @var Party $party */
        $party = app(PartyService::class)->create([
            'party_kind' => 'organization',
            'display_name' => $name,
            'country_code' => $country,
            // Devam eden projenin isvereni: aday degil, aktif taraf.
            'status' => 'active',
            'organization_profile' => ['legal_name' => $name],
        ]);

        app(PartyRoleService::class)->create([
            'party_id' => $party->getKey(),
            'role_code' => $role,
            'status' => 'active',
        ]);

        $this->totals['parties']++;

        return $party;
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: string}  $country
     */
    private function country(string $code, array $country): Country
    {
        /** @var Country|null $existing */
        $existing = Country::query()->whereKey($code)->first();

        if ($existing !== null) {
            return $existing;
        }

        [$iso3, $nameTr, $nameEn, $timezone] = $country;
        $this->totals['countries']++;

        /** @var Country */
        return Country::query()->create([
            'code' => $code,
            'iso3_code' => $iso3,
            'name_tr' => $nameTr,
            'name_en' => $nameEn,
            'default_timezone' => $timezone,
            'status' => ActiveStatus::Active,
        ]);
    }
}
