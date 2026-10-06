<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Acquisition\BusinessSourceKind;
use App\Models\Acquisition\BusinessCase;
use App\Models\Party\Party;
use App\Models\Personnel\Personnel;
use App\Services\Acquisition\BusinessCaseService;
use App\Services\Audit\ActorContext;
use App\Services\Platform\SchemaReadiness;
use Database\Seeders\Support\ProtectedSeeder;
use Illuminate\Support\Str;

/**
 * Firma takip listesindeki projeler = is dosyalari (D-129, 28 Eylul 2026
 * kullanici karari: "Firma takip listesinden daha once aldigimiz proje kismi
 * aslinda bir is dosyasidir. Proje grubundaki proje ile ilgisi yoktur.").
 *
 * Kaynak: database/seeders/data/real_parties.php icindeki her firmanin
 * `projects` listesi (Firma_Takip_Listesi_21_09_2026.xlsx D/E/F/G sutunlari ve
 * 21 Eylul kararina gore eski satirdan gelen M notu; RealPartySeeder'in
 * aktarmadigi kisim). Her satir, firmasi musteri (primary_party) olmak
 * uzere bir is dosyasi olur:
 *
 * - Baslik = PROJE ADI. Adi olmayan satir (kullanici karari: hepsi acilsin)
 *   "Firma - 101 MW proje", guc de yoksa "Firma - proje (adi belirtilmemis)".
 * - Ayni firmada ayni ad birden cok kez geciyorsa: satirlar aynen ayniysa tek
 *   is dosyasi; farkliysa basliga guc (guc de ayniysa satir no) eklenir.
 * - Proje tipi: GES / RES / HES (bilesen katalogu kodu). Kapsamlar: GES EDT
 *   -> GES + BES, RES EDT -> RES + BES (EDT = elektrik depolama tesisi),
 *   GES -> GES, RES -> RES, HES -> HES. GES kapsaminda guc (MW) sayi olarak,
 *   her kapsamda listedeki guc metni not olarak durur.
 * - Aciklama: listeden aktarildigi (tarih, satir), proje durumu (LISANS,
 *   ONLISANS, IDK, CM, 5.1.h...), proje turu, proje gucu, CED / lisans notu.
 * - Sorumlu: Ersin Ozdemir (kullanici karari, MeetingNotePersonnelSeeder ile
 *   ayni kisi); kaynak "elle", ulke TR, para birimi kurum varsayilani.
 *
 * Yazmalar BusinessCaseService ile gider (S-2): potansiyel is kodu (B40
 * sonrasi POTIS-YYYY-NNNN, oncesi TKLF-n), firsat kaydi,
 * kapsamlar ve Personel Hareketleri servisle olusur. Tekrar calistirilabilir:
 * ayni firmada ayni baslikli is dosyasi varsa atlanir; var olan is dosyasina
 * dokunulmaz. Numara sayaci geri alinmadigi icin deneme amacli (geri alinan)
 * calistirma yapilmaz; `plan()` hicbir sey yazmadan ne olusacagini doner.
 *
 * D-165: korumali seeder. Her is dosyasi satiri "case:firma|baslik" anahtariyla
 * bir kez islenir ve seed arsivine duser; var olan is dosyasi benimsenir,
 * hicbir kayit guncellenmez. Firmasi bulunamayan satir arsive dusmez.
 */
class FirmaTakipBusinessCaseSeeder extends ProtectedSeeder
{
    public const LIST_DATE = '21.09.2026';

    public const OWNER_EMAIL = MeetingNotePersonnelSeeder::DEFAULT_PERSONNEL_EMAIL;

    /** Listedeki proje turu => [proje tipi kodu, kapsamlar]. */
    private const TYPES = [
        'GES EDT' => ['GES', ['ges', 'bes']],
        'GES' => ['GES', ['ges']],
        'RES EDT' => ['RES', ['res', 'bes']],
        'RES' => ['RES', ['res']],
        'HES' => ['HES', ['hes']],
    ];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B16')) {
            $this->command?->warn('B16 (taraf ve is alim) uygulanmamis; firma takip is dosyalari atlandi.');

            return;
        }

        $ownerId = Personnel::query()->where('email', self::OWNER_EMAIL)->value('id');

        if ($ownerId === null) {
            $this->command?->warn(self::OWNER_EMAIL.' bulunamadi; firma takip is dosyalari atlandi (once RealPersonnelSeeder).');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $plan = $this->plan((int) $ownerId);
        $service = app(BusinessCaseService::class);
        $created = 0;
        $adopted = 0;

        foreach ($plan['cases'] as $case) {
            // D-165: satir bir kez islenir; var olan is dosyasi benimsenir, guncellenmez.
            $this->row($case['key'], function () use ($case, $service, &$created, &$adopted): ?BusinessCase {
                /** @var BusinessCase|null $existing */
                $existing = BusinessCase::query()
                    ->where('primary_party_id', $case['data']['primary_party_id'])
                    ->where('title', $case['title'])
                    ->first();

                if ($existing !== null) {
                    $adopted++;

                    return $existing;
                }

                // D-165: canlida zaten var olan firmanin altina seed potansiyel is acmaz
                // (silinen is geri gelmez). Ilk kurulumda firma ayni calismada acilir.
                $party = Party::query()->find($case['data']['primary_party_id']);

                if ($party === null || ! $this->ownedBySeed($party)) {
                    return null;
                }

                /** @var BusinessCase $record */
                $record = $service->create($case['data']);
                $created++;

                return $record;
            });
        }

        $this->command?->info(sprintf(
            'Firma takip is dosyalari: %d olusturuldu, %d zaten vardi, %d satir tekrar oldugu icin birlestirildi.',
            $created,
            $adopted,
            $plan['merged'],
        ));

        if ($plan['missing_parties'] !== []) {
            $this->command?->warn('Sistemde bulunamayan firmalar (atlandi): '.implode(', ', $plan['missing_parties']));
        }
    }

    /**
     * Yazmadan: olusacak is dosyalari, eslesmeyen firmalar, birlestirilen satirlar.
     *
     * @return array{cases: list<array{key: string, party: string, row: int|null, title: string, exists: bool, data: array<string, mixed>}>, missing_parties: list<string>, merged: int}
     */
    public function plan(int $ownerId): array
    {
        /** @var list<array<string, mixed>> $firms */
        $firms = require __DIR__.'/data/real_parties.php';
        $cases = [];
        $missing = [];
        $merged = 0;

        foreach ($firms as $firm) {
            $projects = array_values((array) ($firm['projects'] ?? []));

            if ($projects === []) {
                continue;
            }

            $party = Party::query()->where('normalized_name', $this->normalize((string) $firm['name']))->first();

            if ($party === null) {
                $missing[] = (string) $firm['name'];

                continue;
            }

            foreach ($this->titled($party, $projects, $merged) as [$title, $project]) {
                $cases[] = [
                    // D-165 seed arsivi anahtari: listedeki firma adi + is dosyasi basligi.
                    'key' => 'case:'.$this->normalize((string) $firm['name']).'|'.$this->normalize($title),
                    'party' => (string) $party->display_name,
                    'row' => isset($project['excel_row']) ? (int) $project['excel_row'] : null,
                    'title' => $title,
                    'exists' => BusinessCase::query()->where('primary_party_id', $party->getKey())->where('title', $title)->exists(),
                    'data' => $this->payload($party, $title, $project, $ownerId),
                ];
            }
        }

        return ['cases' => $cases, 'missing_parties' => $missing, 'merged' => $merged];
    }

    /**
     * Basliklar: adsiz satira uretilmis baslik, ayni adli satirlara ayirt edici ek.
     *
     * @param  list<array<string, mixed>>  $projects
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    private function titled(Party $party, array $projects, int &$merged): array
    {
        $groups = [];

        foreach ($projects as $project) {
            $title = $this->baseTitle($party, $project);
            $groups[$this->normalize($title)][] = [$title, $project];
        }

        $result = [];

        foreach ($groups as $group) {
            // Aynen ayni satirlar tek is dosyasidir.
            $unique = [];

            foreach ($group as [$title, $project]) {
                $signature = json_encode(array_diff_key($project, ['excel_row' => true]));
                $unique[$signature] ??= [$title, $project];
            }

            $merged += count($group) - count($unique);
            $unique = array_values($unique);

            if (count($unique) === 1) {
                $result[] = $unique[0];

                continue;
            }

            $powers = array_map(fn (array $item): string => (string) ($item[1]['power'] ?? ''), $unique);
            $distinctPowers = count(array_unique($powers)) === count($powers) && ! in_array('', $powers, true);

            foreach ($unique as [$title, $project]) {
                $suffix = $distinctPowers ? (string) $project['power'] : 'satır '.($project['excel_row'] ?? '?');
                $result[] = [Str::limit($title, 240, '').' ('.$suffix.')', $project];
            }
        }

        return $result;
    }

    /** @param  array<string, mixed>  $project */
    private function baseTitle(Party $party, array $project): string
    {
        $name = trim((string) ($project['name'] ?? ''));

        if ($name !== '') {
            return Str::limit($name, 255, '');
        }

        $firm = Str::limit((string) $party->display_name, 180, '');
        $power = trim((string) ($project['power'] ?? ''));

        return $power !== '' ? $firm.' – '.$power.' proje' : $firm.' – proje (adı belirtilmemiş)';
    }

    /**
     * BusinessCaseService::create verisi.
     *
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    private function payload(Party $party, string $title, array $project, int $ownerId): array
    {
        $type = mb_strtoupper(trim((string) ($project['type'] ?? '')), 'UTF-8');
        [$typeCode, $scopes] = self::TYPES[$type] ?? [null, []];
        $power = trim((string) ($project['power'] ?? ''));
        $note = $power !== '' ? Str::limit('Listedeki proje gücü: '.$power, 255, '') : null;

        $rows = [];

        foreach ($scopes as $scope) {
            $rows[$scope] = ['note' => $scope === 'bes' ? 'Elektrik depolama tesisi (EDT)'.($power !== '' ? ' – '.Str::limit($power, 200, '') : '') : $note];

            if ($scope === 'ges') {
                $rows[$scope]['capacity_mw'] = $this->megawatts($power);
            }
        }

        return [
            'title' => $title,
            'primary_party_id' => (int) $party->getKey(),
            'short_description' => $this->description($project),
            'country_code' => 'TR',
            'currency_code' => (string) config('konelsis.organization.default_currency', 'TRY'),
            'project_type_code' => $typeCode,
            'source_kind' => BusinessSourceKind::Manual->value,
            'owner_employee_id' => $ownerId,
            'scope_types' => $scopes,
            'scopes' => $rows,
        ];
    }

    /** @param  array<string, mixed>  $project */
    private function description(array $project): string
    {
        $lines = ['Firma takip listesinden aktarıldı ('.self::LIST_DATE.(isset($project['excel_row']) ? ', satır '.$project['excel_row'] : '').').'];

        foreach ([
            'status' => 'Proje durumu',
            'type' => 'Proje türü',
            'power' => 'Proje gücü',
            'regulatory_note' => 'ÇED / lisans notu',
        ] as $key => $label) {
            $value = trim((string) ($project[$key] ?? ''));

            if ($value !== '') {
                $lines[] = $label.': '.$value;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * "30MW/30MWH" -> 30, "20 Mwe 28,08 MWm/..." -> 20, "5,5 mw" -> 5.5.
     * MWh (enerji) ve MWm (mekanik) sayilmaz; bulunamazsa null.
     */
    private function megawatts(string $power): ?string
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*mw(?:e|p)?(?![a-zçğıöşü])/iu', $power, $match) !== 1) {
            return null;
        }

        return str_replace(',', '.', $match[1]);
    }

    /** RealPartySeeder ile ayni kural: kucuk harf, tek bosluk. */
    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->squish()->value();
    }
}
