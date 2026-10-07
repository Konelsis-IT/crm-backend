<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Acquisition\BusinessSourceKind;
use App\Enums\Party\MeetingChannel;
use App\Models\Acquisition\BusinessCase;
use App\Models\Party\ContactRelationship;
use App\Models\Party\Party;
use App\Services\Acquisition\BusinessCaseService;
use App\Services\Audit\ActorContext;
use App\Services\Party\ContactRelationshipService;
use App\Services\Party\PartyMeetingNoteService;
use App\Services\Party\PartyRoleService;
use App\Services\Party\PartyService;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\TransactionRunner;
use Database\Seeders\Support\ProtectedSeeder;
use Database\Seeders\Support\SeedGuard;
use Illuminate\Support\Str;

/**
 * Potansiyel isler listesi, 07.10.2026 (D-170, 7 Ekim 2026 kullanici
 * talimati: "Potansiyel isleri de gonderdim ... Tur'unu direk potansiyel is
 * olarak ekleyelim"; "Hepsi yeni is olsun").
 *
 * Kaynak: data/potential_jobs_2026_10_07.php. Her satir yeni bir potansiyel
 * istir; tur elle "Potansiyel is" (B47, sicakliktan bagimsiz). Proje adi
 * sutunu bos oldugu icin baslik "Firma – Tur Guc". Not sutunu ise bagli
 * gorusme notu olur (listede tarih yok, liste tarihi yazilir); yetkili
 * kisiler tarafta yoksa acilir.
 *
 * Firma eslestirmesi kullanicinin sohbette verdigi kararlardir (PARTIES):
 * FERNAS Sirketler Grubu, Limak, AKFEN, ISKUR, YILDIRIM HOLDING, Entek ...;
 * TUPRAG Madencilik, OZBAL Grup ve ERN Holding ("excel'de hatali olusturulmus,
 * is ERN HOLDING'in; Yildizlar ile birlestirmeyelim") yeni taraftir. OZE
 * Grup satiri ("2 adet 120 MW DGES Eskisehir") kullanici karariyla OZE 2 ve
 * OZE 3 icin ikiye bolunur.
 *
 * FirmaTakipUpdate20261007Seeder'dan (birlestirmeler) sonra calisir. Her satir
 * seed arsivine duser (bir kez); var olan kayit degistirilmez. preview()
 * hicbir sey yazmaz. Kurulumda DEPLOY_SEEDERS ile calisir.
 */
final class PotentialJobs20261007Seeder extends ProtectedSeeder
{
    public const LIST_DATE = '07.10.2026';

    private const DATA_FILE = 'potential_jobs_2026_10_07.php';

    /**
     * Listedeki firma => sistemdeki taraf adlari (ilk bulunan). Bos liste:
     * NEW_PARTIES'teki adla yeni taraf.
     *
     * @var array<string, list<string>>
     */
    public const PARTIES = [
        'İREM İNŞAAT (MAKRES - KC ELEKTRİK)' => ['İREM İNŞAAT'],
        'TÜPRAG MADENCİLİK' => [],
        'ÖZBAL GRUP' => [],
        'FERNAS' => ['FERNAS ŞİRKETLER GRUBU'],
        'CEMAK ENERJİ ÜRETİM A.Ş.' => ['CEMAK ENERJİ ÜRETİM A.Ş.'],
        'SYCS İNŞAAT ÇİMENTO SAN.VE TİC.A.Ş.' => ['SYCS İNŞAAT ÇİMENTO SANAYİ VE TİCARET ANONİM ŞİRKETİ', 'SYCS İNŞAAT ÇİMENTO SAN.VE TİC.A.Ş.'],
        'MATLI GRUP' => ['MATLI ŞİRKETLER GRUBU MATLI YEM'],
        'EKVATOR ENERJİ -KUTUP ENERJİ' => ['EKVATOR ENERJİ'],
        'HİVE ENERJİ ÜRETİM A.Ş.' => ['HİVE ELEKTRİK ÜRETİM SANAYİ VE TİCARET ANONİM ŞİRKETİ'],
        'ZEDUR ENERJİ - DİYAR BATARYA' => ['DİYAR BATARYA SİSTEMLERİ VE YENİLENEBİLİR ENERJİ YATIRIMLARI ANONİM ŞİRKETİ'],
        'RT ENERJİ' => ['RT ENERJİ'],
        'LİMAK HOLDİNG' => ['Limak Holding', 'LİMAK YENİLENEBİLİR ENERJİ'],
        'MERGE ENERJİ' => ['MERGE ENERJİ'],
        'AKFEN HOLDİNG' => ['AKFEN HOLDİNG', 'AKFEN ELEKTRİK ENERJİSİ TOPTAN SATIŞ A.Ş.'],
        'ISKUR HOLDİNG' => ['İSKUR HOLDİNG', 'ISKUR HOLDİNG'],
        'YILDIZLAR İNŞAAT - ERN HOLDİNG' => [],
        'YILDIRIM GRUP' => ['YILDIRIM HOLDİNG'],
        'EFOR HOLDİNG' => ['EFOR ENERJİ'],
        'ENTEK ENERJİ A.Ş.' => ['ENTEK ELEKTRİK ÜRETİMİ A.Ş. (KOÇ Holding)', 'Entek Elektrik Üretim Anonim Şirketi'],
    ];

    /** Yeni acilacak taraflar: listedeki firma => taraf adi (uzun ad). */
    public const NEW_PARTIES = [
        'TÜPRAG MADENCİLİK' => 'TÜPRAG MADENCİLİK',
        'ÖZBAL GRUP' => 'ÖZBAL GRUP',
        'YILDIZLAR İNŞAAT - ERN HOLDİNG' => 'ERN HOLDİNG',
    ];

    /**
     * Ikiye bolunen satir: listedeki firma => [anahtar ek, taraf adlari, baslik
     * firmasi, tur, guc].
     *
     * @var array<string, list<array{0: string, 1: list<string>, 2: string, 3: string, 4: string}>>
     */
    public const SPLITS = [
        'OZE GRUP' => [
            ['oze2', ['OZE 2 ENERJİ A.Ş.', 'OZE 2 ENERJİ ANONİM ŞİRKETİ'], 'OZE 2 ENERJİ A.Ş.', 'DGES', '120 MW'],
            ['oze3', ['OZE 3 ENERJİ A.Ş.', 'OZE 3 ENERJİ ANONİM ŞİRKETİ'], 'OZE 3 ENERJİ A.Ş.', 'DGES', '120 MW'],
        ],
    ];

    private bool $dry = false;

    /** @var array<string, int> */
    private array $totals = ['cases' => 0, 'parties' => 0, 'contacts' => 0, 'notes' => 0, 'skipped' => 0];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B28')) {
            $this->command?->warn('B28 uygulanmamis; potansiyel isler 07.10 atlandi.');

            return;
        }

        $ownerId = FirmaTakipUpdate20261007Seeder::ownerId();

        if ($ownerId === null) {
            $this->command?->warn(FirmaTakipBusinessCaseSeeder::OWNER_EMAIL.' bulunamadi; potansiyel isler 07.10 atlandi.');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        foreach ($this->jobs() as $job) {
            $this->row('pj:'.$job['key'], function () use ($job, $ownerId): ?BusinessCase {
                try {
                    return SeedGuard::allowingUpdates(fn (): ?BusinessCase => app(TransactionRunner::class)->run(
                        fn (): ?BusinessCase => $this->process($job, $ownerId)['case'],
                        1,
                    ));
                } catch (\Throwable $exception) {
                    $this->totals['skipped']++;
                    $this->command?->warn(sprintf('Potansiyel isler 07.10 satir %s atlandi (sonra yeniden denenecek): %s', $job['key'], $exception->getMessage()));

                    return null;
                }
            });
        }

        $this->command?->info(sprintf(
            'Potansiyel isler 07.10: %d yeni potansiyel is, %d yeni taraf, %d yeni kisi, %d gorusme notu; %d satir atlandi.',
            $this->totals['cases'], $this->totals['parties'], $this->totals['contacts'], $this->totals['notes'], $this->totals['skipped'],
        ));
    }

    /**
     * Yazmadan: her satirin tarafi, basligi, kisileri ve notu.
     *
     * @return list<array<string, mixed>>
     */
    public function preview(): array
    {
        $this->dry = true;
        $out = [];

        foreach ($this->jobs() as $job) {
            $result = $this->process($job, 0);
            unset($result['case']);
            $out[] = ['row' => $job['excel_row'], 'firm' => $job['firm'], ...$result];
        }

        $this->dry = false;

        return $out;
    }

    /**
     * Satirlar; ikiye bolunen satir iki is olur.
     *
     * @return list<array<string, mixed>>
     */
    private function jobs(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = require __DIR__.'/data/'.self::DATA_FILE;
        $jobs = [];

        foreach ($rows as $row) {
            $firm = (string) $row['firm'];

            if (isset(self::SPLITS[$firm])) {
                foreach (self::SPLITS[$firm] as [$suffix, $names, $label, $type, $power]) {
                    $jobs[] = [...$row, 'key' => $row['excel_row'].':'.$suffix, 'names' => $names, 'label' => $label, 'type' => $type, 'power' => $power, 'split' => true];
                }

                continue;
            }

            // Yeni tarafin basligi kendi adini alir ("ERN HOLDING yazsin").
            $jobs[] = [...$row, 'key' => (string) $row['excel_row'], 'names' => self::PARTIES[$firm] ?? [$firm], 'label' => self::NEW_PARTIES[$firm] ?? $firm, 'split' => false];
        }

        return $jobs;
    }

    /**
     * @param  array<string, mixed>  $job
     * @return array{case: BusinessCase|null, party: string, title: string, contacts: list<string>, note: string|null}
     */
    private function process(array $job, int $ownerId): array
    {
        $resolver = new FirmaTakipUpdate20261007Seeder;
        $newName = self::NEW_PARTIES[$job['firm']] ?? null;
        $party = $resolver->findParty($newName !== null ? [$newName] : $job['names']);
        $title = $this->title($job);
        $contacts = $this->contactNames((string) $job['contacts']);
        $note = trim((string) $job['notes']) !== '' ? trim((string) $job['notes']) : null;
        $result = ['case' => null, 'party' => '', 'title' => $title, 'contacts' => [], 'note' => $note];

        if ($party === null && $newName === null) {
            throw new \RuntimeException('Taraf bulunamadi: '.$job['firm']);
        }

        if ($party === null) {
            $result['party'] = 'YENİ: '.$newName;

            if ($this->dry) {
                return [...$result, 'contacts' => $contacts];
            }

            $party = $this->createParty((string) $newName);
        }

        $result['party'] = '#'.$party->getKey().' '.$party->display_name;
        $existing = [];

        foreach ($party->contacts()->get() as $contact) {
            $existing[FirmaTakipUpdate20261007Seeder::personKey($contact->displayName())] = true;
        }

        $newContacts = array_values(array_filter($contacts, static fn (string $name): bool => ! isset($existing[FirmaTakipUpdate20261007Seeder::personKey($name)])));
        $result['contacts'] = $newContacts;

        if ($this->dry) {
            return $result;
        }

        foreach ($newContacts as $index => $name) {
            app(ContactRelationshipService::class)->create([
                'organization_party_id' => $party->getKey(),
                'contact_name' => $name,
                'relationship_role' => 'other',
                'is_primary' => $existing === [] && $index === 0,
            ]);
            $this->totals['contacts']++;
        }

        /** @var BusinessCase $case */
        $case = app(BusinessCaseService::class)->create($this->payload($party, $title, $job, $ownerId));
        $this->totals['cases']++;

        // D-171: ayni metin haftalik ziyaret planinda / raporda tarihli olarak varsa not
        // burada tarihsiz yazilmaz; WeeklyVisitPlan20261007Seeder tarihli notu bu ise baglar.
        if ($note !== null && ! WeeklyVisitPlan20261007Seeder::covers($note)) {
            app(PartyMeetingNoteService::class)->create([
                'party_id' => $party->getKey(),
                'business_case_id' => $case->getKey(),
                'personnel_id' => $ownerId,
                'noted_on' => '2026-10-07',
                'channel' => $this->channel($note)->value,
                'subject' => Str::limit($title, 200, ''),
                'note' => $note.' (Listede tarih yok; liste tarihi '.self::LIST_DATE.' yazıldı.)',
            ]);
            $this->totals['notes']++;
        }

        return [...$result, 'case' => $case];
    }

    /**
     * Bu listeden acilan (ya da acilacak) isler: taraf adlari, yeni taraf adi,
     * baslik ve not (WeeklyVisitPlan20261007Seeder ayni metinli notu bu ise baglar).
     *
     * @return list<array{names: list<string>, new: string|null, title: string, note: string|null}>
     */
    public function plannedCases(): array
    {
        $out = [];

        foreach ($this->jobs() as $job) {
            $new = self::NEW_PARTIES[$job['firm']] ?? null;
            $note = trim((string) $job['notes']);
            $out[] = ['names' => $new !== null ? [$new] : $job['names'], 'new' => $new, 'title' => $this->title($job), 'note' => $note !== '' ? $note : null];
        }

        return $out;
    }

    private function createParty(string $name): Party
    {
        /** @var Party $party */
        $party = app(PartyService::class)->create([
            'party_kind' => 'organization',
            'display_name' => $name,
            'country_code' => 'TR',
            'status' => 'prospect',
            'organization_profile' => ['legal_name' => $name],
        ]);

        app(PartyRoleService::class)->create([
            'party_id' => $party->getKey(),
            'role_code' => 'employer',
            'status' => 'active',
        ]);

        $this->totals['parties']++;

        return $party;
    }

    /**
     * "Firma – Tur Guc"; tur ve guc yoksa "Firma – potansiyel iş".
     *
     * @param  array<string, mixed>  $job
     */
    private function title(array $job): string
    {
        $detail = trim(implode(' ', array_filter([trim((string) $job['type']), trim((string) $job['power'])])));

        return Str::limit((string) $job['label'], 180, '').' – '.($detail !== '' ? $detail : 'potansiyel iş');
    }

    /**
     * @param  array<string, mixed>  $job
     * @return array<string, mixed>
     */
    private function payload(Party $party, string $title, array $job, int $ownerId): array
    {
        $type = mb_strtoupper(trim((string) $job['type']), 'UTF-8');
        [$typeCode, $scopes] = FirmaTakipUpdate20261007Seeder::TYPES[$type] ?? [null, []];
        $power = trim((string) $job['power']);
        $lines = ['Potansiyel işler listesinden aktarıldı ('.self::LIST_DATE.', satır '.$job['excel_row'].').'];

        if ($job['firm'] !== $party->display_name) {
            $lines[] = 'Listedeki firma: '.$job['firm'];
        }

        foreach (['city' => 'Şehir', 'contacts' => 'Yetkili', 'type' => 'Tür', 'status' => 'Durum', 'power' => 'Güç'] as $field => $label) {
            if (($value = trim((string) $job[$field])) !== '') {
                $lines[] = $label.': '.$value;
            }
        }

        if ($job['split']) {
            $lines[] = 'Listede tek satır ("2 adet 120 MW DGES Eskişehir"); kullanıcı kararıyla OZE 2 ve OZE 3 için ikiye bölündü.';
        }

        $rows = FirmaTakipUpdate20261007Seeder::scopeRows($scopes, $power);

        // "180 MW ve 150 MW": tek kapasite yazilmaz, guc notta kalir.
        if (isset($rows['ges']) && preg_match_all('/\d+(?:[.,]\d+)?\s*mw/iu', $power) > 1) {
            $rows['ges']['capacity_mw'] = null;
        }

        return [
            'title' => $title,
            'primary_party_id' => (int) $party->getKey(),
            'short_description' => implode("\n", $lines),
            'country_code' => 'TR',
            'currency_code' => (string) config('konelsis.organization.default_currency', 'TRY'),
            'project_type_code' => $typeCode,
            'source_kind' => BusinessSourceKind::Manual->value,
            'owner_employee_id' => $ownerId,
            'scope_types' => $scopes,
            'scopes' => $rows,
            'development_kind' => 'potential_job',
        ];
    }

    /**
     * "ERTUGRUL KARTAL - HALIL ISLEK-SELMAN AYHAN", "ALPEREN KILIC VE ERSIN BEY",
     * "ALPER ESATOGLU - (HALIT BEY)" => ad listesi (Turkce bas harf buyuk).
     *
     * @return list<string>
     */
    private function contactNames(string $text): array
    {
        $names = [];

        foreach (preg_split('/\s+VE\s+|\s*[-–]\s*/u', $text) ?: [] as $part) {
            $part = trim(str_replace(['(', ')'], ' ', $part));

            // "ALPEREN KILIC VE ERSIN BEY": Ersin Bey bizim personelimiz (Ersin Ozdemir); kisi acilmaz.
            if ($part === '' || FirmaTakipUpdate20261007Seeder::personKey($part) === 'ersinbey') {
                continue;
            }

            $words = array_map(static function (string $word): string {
                $lower = mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $word), 'UTF-8');
                $first = mb_substr($lower, 0, 1);

                return mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $first), 'UTF-8').mb_substr($lower, 1);
            }, preg_split('/\s+/u', $part) ?: []);

            $names[] = implode(' ', $words);
        }

        return array_values(array_unique($names));
    }

    private function channel(string $text): MeetingChannel
    {
        $lower = mb_strtolower($text, 'UTF-8');

        return match (true) {
            str_contains($lower, 'whatsapp') => MeetingChannel::Message,
            str_contains($lower, 'mail') && ! str_contains($lower, 'görüşme') => MeetingChannel::Email,
            str_contains($lower, 'toplantı'), str_contains($lower, 'ziyaret'), str_contains($lower, 'görüşme') => MeetingChannel::Visit,
            default => MeetingChannel::Phone,
        };
    }
}
