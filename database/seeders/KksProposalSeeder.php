<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\BusinessSourceKind;
use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\ProposalVersionStatus;
use App\Enums\Acquisition\SubmissionChannel;
use App\Enums\Party\MeetingChannel;
use App\Enums\Party\PartyRoleCode;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Party\Party;
use App\Models\Party\PartyMeetingNote;
use App\Models\Personnel\Personnel;
use App\Services\Acquisition\AcquisitionIntakeService;
use App\Services\Acquisition\BusinessCaseService;
use App\Services\Acquisition\ProposalVersionService;
use App\Services\Audit\ActorContext;
use App\Services\Party\PartyMeetingNoteService;
use App\Services\Party\PartyRoleService;
use App\Services\Party\PartyService;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\TransactionRunner;
use App\Support\DisplayTime;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * KKS teklif takip listesi = teklifler (D-135, 28 Eylul 2026 kullanici karari:
 * "Simdi teklifleri iceri almak istiyorum ... kirmizi renk ile doldurulmus
 * teklifler var o teklifler kaybedilen tekliftir.").
 *
 * Kaynak: database/seeders/data/kks_proposals.php (KKS_Teklif_Takip_Listesi.xlsx,
 * 22.09.2026; iki sayfa). Firma takip aktarimiyla (D-129) ayni mantik:
 *
 * - Taraf: listedeki firma adi (kucuk harf, tek bosluk) ya da PARTY_ALIASES'taki
 *   sistemdeki adi. Sistemde yoksa taraf acilir (kurulus, Aday, Isveren rolu).
 * - Potansiyel is: ayni firmada ayni proje adi (TITLE_ALIASES ile eski
 *   aktarimdaki adi) varsa teklif ona baglanir; yoksa acilir. Adi " TM" / " EİH"
 *   ile biten ve ayni firmada eki olmayan adi da gecen satir, o adin potansiyel
 *   isine baglanir (or. HISAR RES TM -> HISAR RES). Ayni potansiyel ise birden
 *   fazla teklif baglanabilir (D-132).
 * - Teklif: her satir bir teklif (kendi TKLF numarasi). Baslik proje adi; ad
 *   kapsamla bitmiyorsa kapsam parantez icinde eklenir. Teklif durumu sayfadan
 *   gelir: VERILECEK TEKLIFLER -> Verilecek teklif, VERILEN TEKLIFLER -> Verilen
 *   teklif; kirmizi dolgulu satir -> Kacan firsat (kaybedilen teklif).
 * - Verilen ve kaybedilen tekliflerin surumu Taslak -> Incelemede -> Onaylandi
 *   -> Gonderildi yurur; gonderim tarihi listedeki teklif tarihidir. Potansiyel
 *   is buna gore "Musteriye gonderildi" olur. Bir potansiyel isin bu listedeki
 *   butun teklifleri kaybedilmisse potansiyel is "Kaybedildi" olur (sonuc da).
 * - Surum ozeti: listeden aktarildigi, kapsam, guc, depolama, proje durumu,
 *   teklif tarihi ve gorusme notlari.
 * - Sorumlu: Ersin Ozdemir (D-129 ile ayni kisi).
 *
 * Yazmalar servislerle gider (S-2). Tekrar calistirilabilir: ayni potansiyel iste
 * ayni baslikli teklif varsa satir atlanir. Numara sayaci (potansiyel is
 * sira numarasi) geri alinmadigi icin deneme amacli calistirma yapilmaz;
 * `plan()` hicbir sey yazmadan ne olusacagini doner.
 */
class KksProposalSeeder extends Seeder
{
    public const LIST_DATE = '22.09.2026';

    /** Listedeki firma adi => sistemdeki taraf adi (display_name). */
    public const PARTY_ALIASES = [
        'AKSA Yenilenebilir Enerji' => 'Aksa Yenilenebilir Enerji Üretim Anonim Şirketi',
        'AKSA' => 'Aksa Yenilenebilir Enerji Üretim Anonim Şirketi',
        'İSKUR' => 'ISKUR HOLDİNG',
        'İÇDAŞ ÇELİK' => 'İÇDAŞ ÇELİK ENERJİ TERSANE VE ULAŞIM A.Ş.',
        'LİMAK ENERJİ' => 'LİMAK YENİLENEBİLİR ENERJİ',
        'BEZMİ ALEM VAKIF ÜNİVERSİTESİ' => 'BEZMİALEM VAKIF ÜNİVERSİTESİ',
        'VAKKO Tekstil' => 'VAKKO TEKSTİL ve HAZIR GİYİM SANAYİ İŞLETMLERİ A.Ş.',
        'SIBA ELEKTRİK' => 'SİBA',
        'PNE WIND AG' => 'PNE ENERGY AG',
        'OVA ENERJİ' => 'OVA ENERJİ SAN.A.Ş.',
        'GÜGLER ENERJİ' => 'GUGLER SU TRÜBÜNLERİ',
        'ODY GREEN ENERJİ ANONIM ŞİRKETİ' => 'ODY GREEN ENERJİ',
    ];

    /** "Firma|Listedeki proje adi" => firma takip aktarimindaki potansiyel is basligi. */
    public const TITLE_ALIASES = [
        'EXEN SOLAR ENERJİ ÜRETİM ve DEPOLAMA ANONİM ŞİRKETİ|SİVRİCE EDT GES' => 'SİVRİCE DGES',
        'GÜLSAN HOLDİNG|DOĞANKAYA GES' => "DOĞANKAYA HES'E YARDIMCI KAYNAK",
        'BEZMİ ALEM VAKIF ÜNİVERSİTESİ|ESKİŞEHİR BEZMİALEM GES' => 'ESKİŞEHİR GES',
    ];

    /** Sistemde olmayan ve Turkiye disindaki firmalar: ulke kodu. */
    private const NEW_PARTY_COUNTRIES = [
        'OZBEKİSTAN' => 'UZ',
        'GÜNEY AFRİKA' => 'ZA',
        'SRL-CEF CARACAL' => 'RO',
        'ORIZONTCEF SOLAR ENERGY' => 'RO',
    ];

    /** Listedeki kapsam => [proje tipi (bilesen katalogu kodu), kapsamlar]. */
    private const SCOPES = [
        'GES' => ['GES', ['ges']],
        'DGES' => ['GES', ['ges', 'bes']],
        'RES' => ['RES', ['res']],
        'DRES' => ['RES', ['res', 'bes']],
        'HES' => ['HES', ['hes']],
        'DHES' => ['HES', ['hes', 'bes']],
        'TM' => ['SUBSTATION', ['tm']],
        'EİH' => ['ENH', ['enh_eih']],
        'EDT' => ['BESS', ['bes']],
    ];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B29')) {
            $this->command?->warn('B29 (teklif durumu) uygulanmamis; KKS teklifleri atlandi.');

            return;
        }

        $ownerId = Personnel::query()->where('email', FirmaTakipBusinessCaseSeeder::OWNER_EMAIL)->value('id');

        if ($ownerId === null) {
            $this->command?->warn(FirmaTakipBusinessCaseSeeder::OWNER_EMAIL.' bulunamadi; KKS teklifleri atlandi (once RealPersonnelSeeder).');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $owner = (int) $ownerId;
        $plan = $this->plan();
        $totals = ['parties' => 0, 'cases' => 0, 'proposals' => 0, 'skipped' => 0, 'lost_cases' => 0];
        $parties = [];
        $cases = [];

        foreach ($plan['rows'] as $row) {
            if ($row['proposal_exists']) {
                $totals['skipped']++;

                continue;
            }

            // Satir basina tek islem: taraf / potansiyel is / teklif yarim kalmaz.
            app(TransactionRunner::class)->run(function () use ($row, $owner, $plan, &$parties, &$cases, &$totals): void {
                $party = $parties[$row['party_key']] ??= $this->partyFor($row, $totals);
                $caseKey = $party->getKey().'|'.$this->normalize($row['case_title']);
                $case = $cases[$caseKey] ??= $this->caseFor($party, $row, $plan['case_rows'][$row['party_key'].'|'.$this->normalize($row['case_title'])] ?? [$row], $owner, $totals);

                $this->openProposal($case, $row);
                $totals['proposals']++;
            }, 1);
        }

        foreach ($cases as $case) {
            if ($this->closeIfAllLost($case)) {
                $totals['lost_cases']++;
            }
        }

        $this->command?->info(sprintf(
            'KKS teklifleri: %d teklif, %d yeni potansiyel is, %d yeni taraf; %d satir zaten vardi; %d potansiyel is Kaybedildi.',
            $totals['proposals'],
            $totals['cases'],
            $totals['parties'],
            $totals['skipped'],
            $totals['lost_cases'],
        ));

        if (! SchemaReadiness::hasBatch('B41')) {
            $this->command?->warn('B41 (gorusme notu - potansiyel is / teklif baglantisi) uygulanmamis; KKS gorusme notlari atlandi.');

            return;
        }

        [$notes, $corrected] = $this->seedMeetingNotes($owner);

        $this->command?->info(sprintf('KKS gorusme notlari: %d not olusturuldu; %d teklif ozetinden notlar cikarildi.', $notes, $corrected));
    }

    /**
     * Gorusme notlari (D-137): listedeki "Guncel gorusme notlari" (I) ve
     * "Gorusme notlari" (J) tarihli parcalara bolunur; her parca tarafin
     * gorusme notu olur, potansiyel ise ve satirin teklifine baglanir. Ayni
     * potansiyel isteki ayni metin (I ile J'de ya da ayni isin baska
     * satirlarinda tekrar eden) tek not olur ve butun ilgili tekliflere baglanir.
     * Tekrar calistirilabilir: ayni potansiyel is + tarih + metin varsa atlanir.
     * Daha once ozete yazilmis not satirlari ozetten cikarilir.
     *
     * @return array{0: int, 1: int}
     */
    private function seedMeetingNotes(int $ownerId): array
    {
        /** @var list<array<string, mixed>> $source */
        $source = require __DIR__.'/data/kks_proposals.php';
        $personnel = $this->personnelNames();
        $groups = [];
        $corrected = 0;

        foreach ($source as $row) {
            $proposal = $this->proposalForRow($row);

            if ($proposal === null) {
                continue;
            }

            if ($this->cleanSummary($proposal)) {
                $corrected++;
            }

            foreach ($this->meetingEntries($row) as $entry) {
                $key = $proposal->business_case_id.'|'.($entry['date'] ?? '-').'|'.$this->fingerprint($entry['text']);
                $groups[$key] ??= ['case_id' => (int) $proposal->business_case_id, 'entry' => $entry, 'proposal_ids' => []];
                $groups[$key]['proposal_ids'][] = (int) $proposal->getKey();
            }
        }

        $created = 0;
        $service = app(PartyMeetingNoteService::class);

        foreach ($groups as $group) {
            $entry = $group['entry'];
            [$personnelId, $text] = $this->splitPersonnel($entry['text'], $personnel);
            $note = $entry['date'] === null
                ? $text.' (Listede tarih yok; liste tarihi '.self::LIST_DATE.' yazıldı.)'
                : $text;
            $notedOn = Carbon::createFromFormat('d.m.Y', $entry['date'] ?? self::LIST_DATE)->toDateString();

            $exists = PartyMeetingNote::query()
                ->where('business_case_id', $group['case_id'])
                ->whereDate('noted_on', $notedOn)
                ->where('note', $note)
                ->exists();

            if ($exists) {
                continue;
            }

            $case = BusinessCase::query()->findOrFail($group['case_id']);

            $service->create([
                'party_id' => $case->primary_party_id,
                'business_case_id' => $case->getKey(),
                'proposal_ids' => array_values(array_unique($group['proposal_ids'])),
                'personnel_id' => $personnelId ?? $ownerId,
                'noted_on' => $notedOn,
                'channel' => $this->channel($text)->value,
                'subject' => Str::limit((string) $case->title, 200, ''),
                'note' => $note,
            ]);
            $created++;
        }

        return [$created, $corrected];
    }

    /**
     * Bir satirin not metinlerini tarihli gorusmelere boler. Her "(gg.aa.yyyy"
     * yeni bir gorusme baslatir; tarih parantezi yalniz kisiyi iceriyorsa not
     * parantezden sonra devam eder ("(14.08.2026 Gokhan Adey) degerlendirmede"
     * -> "Gokhan Adey – degerlendirmede"). Satir basindaki cıplak tarih de
     * gorusme tarihidir; "… yapilan" / "… tarihinde" ile devam ediyorsa metnin
     * parcasidir (gecmis teklif ya da ileri tarihli plan). Tarihsiz parcalar
     * tarihsiz not olur.
     *
     * @param  array<string, mixed>  $row
     * @return list<array{date: string|null, text: string}>
     */
    public function meetingEntries(array $row): array
    {
        $entries = [];
        $seen = [];

        foreach ([(string) $row['current_notes'], (string) $row['notes']] as $text) {
            foreach (preg_split('/\R/u', $text) ?: [] as $line) {
                foreach ($this->lineEntries(trim($line)) as $entry) {
                    $fingerprint = $this->fingerprint($entry['text']);

                    if ($fingerprint === '' || isset($seen[$fingerprint])) {
                        continue;
                    }

                    $seen[$fingerprint] = true;
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }

    /** @return list<array{date: string|null, text: string}> */
    private function lineEntries(string $line): array
    {
        if ($line === '') {
            return [];
        }

        $datePattern = '/\((\d{1,2})\.(\d{1,2})\.(\d{4})(?=[\s)])/u';
        $starts = [];

        if (preg_match_all($datePattern, $line, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                if (checkdate((int) $match[2][0], (int) $match[1][0], (int) $match[3][0])) {
                    $starts[] = [
                        'offset' => $match[0][1],
                        'length' => strlen($match[0][0]),
                        'date' => sprintf('%02d.%02d.%04d', (int) $match[1][0], (int) $match[2][0], (int) $match[3][0]),
                    ];
                }
            }
        }

        $entries = [];
        $head = $starts === [] ? $line : substr($line, 0, $starts[0]['offset']);

        if (trim($head) !== '') {
            $entries[] = $this->bareEntry(trim($head));
        }

        foreach ($starts as $index => $start) {
            $end = $starts[$index + 1]['offset'] ?? strlen($line);
            $body = substr($line, $start['offset'] + $start['length'], $end - $start['offset'] - $start['length']);
            $entries[] = ['date' => $start['date'], 'text' => $this->closeDateParen($body)];
        }

        return array_values(array_filter($entries, fn (array $entry): bool => $entry['text'] !== ''));
    }

    /**
     * Tarih parantezinin kapanisi: parca sonundaysa atilir, arada kaliyorsa
     * (kisi + not) " – " olur; hic kapanmiyorsa metin oldugu gibi kalir.
     */
    private function closeDateParen(string $body): string
    {
        $depth = 0;
        $length = strlen($body);

        for ($i = 0; $i < $length; $i++) {
            if ($body[$i] === '(') {
                $depth++;
            } elseif ($body[$i] === ')') {
                if ($depth === 0) {
                    $before = trim(substr($body, 0, $i));
                    $after = trim(substr($body, $i + 1));

                    return trim($before === '' ? $after : ($after === '' ? $before : $before.' – '.$after), " \t;,");
                }

                $depth--;
            }
        }

        return trim($body, " \t;,");
    }

    /** @return array{date: string|null, text: string} */
    private function bareEntry(string $text): array
    {
        // Tamami parantez icindeyse (kisi bilgisi gibi) dis parantez atilir.
        if (preg_match('/^\((.*)\)$/su', $text, $wrapped) === 1 && ! str_contains($wrapped[1], ')')) {
            $text = trim($wrapped[1]);
        }

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})\.?\s+(.*)$/su', $text, $match) === 1
            && checkdate((int) $match[2], (int) $match[1], (int) $match[3])
            && preg_match('/^(yapılan|tarihinde)\b/u', $match[4]) !== 1) {
            return ['date' => sprintf('%02d.%02d.%04d', (int) $match[1], (int) $match[2], (int) $match[3]), 'text' => trim($match[4])];
        }

        return ['date' => null, 'text' => $text];
    }

    /**
     * Metnin basindaki "Ad Soyad;" gorusen personeldir (or. "Ersin Ozdemir;",
     * "Yusuf Fil;"); bulunursa personel yazilir ve on ek metinden atilir.
     *
     * @param  array<string, int>  $personnel  kucuk harf ad => id
     * @return array{0: int|null, 1: string}
     */
    private function splitPersonnel(string $text, array $personnel): array
    {
        if (preg_match('/^([^;()]{3,40}?)(?:\s+ziyareti)?\s*;\s*(.*)$/su', $text, $match) === 1) {
            $id = $personnel[$this->normalize($match[1])] ?? null;

            if ($id !== null) {
                return [$id, trim($match[2])];
            }
        }

        return [null, $text];
    }

    /** @return array<string, int> */
    private function personnelNames(): array
    {
        $names = [];

        foreach (Personnel::query()->get(['id', 'full_name']) as $person) {
            $full = $this->normalize((string) $person->full_name);
            $parts = explode(' ', $full);
            $names[$full] = (int) $person->getKey();
            // Listede ikinci ad yazilmayabilir: "Yusuf Fil" = Yusuf Gokcan Fil.
            $names[$parts[0].' '.end($parts)] ??= (int) $person->getKey();
        }

        return $names;
    }

    private function channel(string $text): MeetingChannel
    {
        $lower = mb_strtolower($text, 'UTF-8');

        return match (true) {
            str_contains($lower, 'whatsapp') => MeetingChannel::Message,
            str_contains($lower, 'toplantı gerçekleştir'), str_contains($lower, 'ziyaretinde') => MeetingChannel::Visit,
            str_contains($lower, 'mail') => MeetingChannel::Email,
            default => MeetingChannel::Phone,
        };
    }

    /** Tekrar karsilastirmasi: harf ve rakam disi atilir, kucuk harf. */
    private function fingerprint(string $text): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($text, 'UTF-8'));
    }

    /** @param  array<string, mixed>  $row */
    private function proposalForRow(array $row): ?Proposal
    {
        $tag = '('.self::LIST_DATE.', '.$row['sheet'].', satır '.$row['excel_row'].').';

        /** @var Proposal|null $proposal */
        $proposal = Proposal::query()
            ->whereHas('currentVersion', fn ($version) => $version->where('summary', 'like', '%'.$tag.'%'))
            ->first();

        return $proposal;
    }

    /** Onceki aktarimda ozete yazilan not satirlarini cikarir. */
    private function cleanSummary(Proposal $proposal): bool
    {
        $version = $proposal->currentVersion;

        if ($version === null) {
            return false;
        }

        $lines = explode("\n", (string) $version->summary);

        // Not satirlari ve devam satirlari (cok satirli notlar) atilir.
        $clean = [];
        $skipping = false;

        foreach ($lines as $line) {
            if (str_starts_with($line, 'Güncel görüşme notları:') || str_starts_with($line, 'Görüşme notları:')) {
                $skipping = true;

                continue;
            }

            if ($skipping && ! preg_match('/^(Kapsam|Proje gücü|Depolama gücü|Proje durumu|Teklif tarihi|Teklif verme tarihi|Listede kırmızı işaretli):/u', $line)) {
                continue;
            }

            $skipping = false;
            $clean[] = $line;
        }

        if (count($clean) === count($lines)) {
            return false;
        }

        app(ProposalVersionService::class)->correctImportedSummary($version, implode("\n", $clean));

        return true;
    }

    /**
     * Yazmadan: her satirin tarafi, potansiyel isi ve teklif basligi.
     *
     * @return array{rows: list<array<string, mixed>>, case_rows: array<string, list<array<string, mixed>>>}
     */
    public function plan(): array
    {
        /** @var list<array<string, mixed>> $source */
        $source = require __DIR__.'/data/kks_proposals.php';
        $namesByFirm = [];

        foreach ($source as $item) {
            if (trim((string) $item['name']) !== '') {
                $namesByFirm[(string) $item['firm']][$this->normalize((string) $item['name'])] = true;
            }
        }

        $rows = [];
        $caseRows = [];

        foreach ($source as $item) {
            $firm = trim((string) $item['firm']);
            $party = $this->findParty($firm);
            $partyKey = $party !== null ? 'id:'.$party->getKey() : 'new:'.$this->normalize($firm);
            $caseTitle = $this->caseTitle($item, $namesByFirm[$firm] ?? [], $party);
            $proposalTitle = $this->proposalTitle($item);
            $case = $party !== null ? $this->findCase($party, $caseTitle) : null;

            $row = [
                ...$item,
                'party_key' => $partyKey,
                'party_name' => $party?->display_name,
                'case_title' => $case?->title ?? $caseTitle,
                'case_id' => $case?->getKey(),
                'proposal_title' => $proposalTitle,
                'proposal_exists' => $case !== null && Proposal::query()->where('business_case_id', $case->getKey())->where('title', $proposalTitle)->exists(),
            ];

            $rows[] = $row;
            $caseRows[$partyKey.'|'.$this->normalize($row['case_title'])][] = $row;
        }

        return ['rows' => $rows, 'case_rows' => $caseRows];
    }

    private function findParty(string $firm): ?Party
    {
        $name = self::PARTY_ALIASES[$firm] ?? $firm;

        return Party::query()->where('normalized_name', $this->normalize($name))->first();
    }

    /** @param  array<string, int>  $totals */
    private function partyFor(array $row, array &$totals): Party
    {
        if ($row['party_name'] !== null) {
            return Party::query()->where('normalized_name', $this->normalize((string) $row['party_name']))->firstOrFail();
        }

        $name = trim((string) $row['firm']);

        /** @var Party $party */
        $party = app(PartyService::class)->create([
            'party_kind' => 'organization',
            'display_name' => $name,
            'country_code' => self::NEW_PARTY_COUNTRIES[$name] ?? 'TR',
            'status' => 'prospect',
            'organization_profile' => ['legal_name' => $name],
        ]);

        app(PartyRoleService::class)->create([
            'party_id' => $party->getKey(),
            'role_code' => PartyRoleCode::Employer->value,
            'status' => 'active',
        ]);

        $totals['parties']++;

        return $party;
    }

    /**
     * Potansiyel is basligi: eski aktarimdaki ad, " TM" / " EİH" eksiz ad ya da
     * adsiz satir icin "Firma – guc kapsam".
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, true>  $firmNames
     */
    private function caseTitle(array $item, array $firmNames, ?Party $party): string
    {
        $firm = trim((string) $item['firm']);
        $name = trim((string) $item['name']);

        if ($name === '') {
            return $this->unnamedTitle($item);
        }

        if (isset(self::TITLE_ALIASES[$firm.'|'.$name])) {
            return self::TITLE_ALIASES[$firm.'|'.$name];
        }

        if (preg_match('/^(.*\S)\s+(TM|EİH)$/u', $name, $match) === 1) {
            $base = $match[1];

            if (isset($firmNames[$this->normalize($base)]) || ($party !== null && $this->findCase($party, $base) !== null)) {
                return $base;
            }
        }

        return Str::limit($name, 255, '');
    }

    /** @param  array<string, mixed>  $item */
    private function proposalTitle(array $item): string
    {
        $name = trim((string) $item['name']);
        $scope = (string) $item['scope'];

        if ($name === '') {
            return $this->unnamedTitle($item);
        }

        $endsWithScope = preg_match('/(^|\s)'.preg_quote($scope, '/').'$/u', mb_strtoupper($name, 'UTF-8')) === 1;

        return Str::limit($endsWithScope || $scope === '' ? $name : $name.' ('.$scope.')', 255, '');
    }

    /** @param  array<string, mixed>  $item */
    private function unnamedTitle(array $item): string
    {
        $power = trim((string) $item['power_mwp']);
        $power = $power === '' ? '' : (is_numeric($power) ? $power.' MWp ' : $power.' ');

        return Str::limit(trim((string) $item['firm']), 180, '').' – '.$power.$item['scope'].' teklifi';
    }

    private function findCase(Party $party, string $title): ?BusinessCase
    {
        $key = $this->normalize($title);

        return BusinessCase::query()
            ->where('primary_party_id', $party->getKey())
            ->get(['id', 'title', 'sequence_no'])
            ->first(fn (BusinessCase $case): bool => $this->normalize((string) $case->title) === $key);
    }

    /**
     * @param  list<array<string, mixed>>  $caseRows  ayni potansiyel ise dusen satirlar
     * @param  array<string, int>  $totals
     */
    private function caseFor(Party $party, array $row, array $caseRows, int $ownerId, array &$totals): BusinessCase
    {
        $existing = $this->findCase($party, (string) $row['case_title']);

        if ($existing !== null) {
            return $existing;
        }

        $typeCode = null;
        $scopes = [];

        foreach ($caseRows as $item) {
            [$code, $itemScopes] = self::SCOPES[(string) $item['scope']] ?? [null, []];
            $typeCode ??= $code;

            foreach ($itemScopes as $scope) {
                $scopes[$scope] ??= $this->scopeRow($scope, $item);
            }
        }

        $budgetary = collect($caseRows)->contains(fn (array $item): bool => str_contains(
            mb_strtolower($item['status'].' '.$item['current_notes'].' '.$item['notes'], 'UTF-8'),
            'bütçe',
        ));

        /** @var BusinessCase $case */
        $case = app(BusinessCaseService::class)->create([
            'title' => (string) $row['case_title'],
            'primary_party_id' => (int) $party->getKey(),
            'short_description' => 'KKS teklif takip listesinden aktarıldı ('.self::LIST_DATE.').',
            'country_code' => (string) ($party->country_code ?? 'TR'),
            'currency_code' => (string) config('konelsis.organization.default_currency', 'TRY'),
            'project_type_code' => $typeCode,
            'source_kind' => BusinessSourceKind::Manual->value,
            'owner_employee_id' => $ownerId,
            'scope_types' => array_keys($scopes),
            'scopes' => $scopes,
            ...($budgetary ? ['offer_type' => 'budgetary'] : []),
        ]);

        $totals['cases']++;

        return $case;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function scopeRow(string $scope, array $item): array
    {
        $power = trim((string) $item['power_mwp']);
        $storage = trim((string) $item['storage_mwh']);

        if ($scope === 'bes') {
            return ['note' => Str::limit('Depolama'.($storage !== '' ? ': '.$storage.(is_numeric($storage) ? ' MWh' : '') : ''), 255, '')];
        }

        $row = ['note' => $power !== '' ? Str::limit('Listedeki proje gücü: '.$power.(is_numeric($power) ? ' MWp' : ''), 255, '') : null];

        if ($scope === 'ges' && is_numeric($power)) {
            $row['capacity_mw'] = $power;
        }

        return $row;
    }

    /** @param  array<string, mixed>  $row */
    private function openProposal(BusinessCase $case, array $row): void
    {
        $status = OfferStatus::from((string) $row['offer_status']);

        $proposal = app(AcquisitionIntakeService::class)->addProposal((int) $case->getKey(), [
            'proposal_title' => (string) $row['proposal_title'],
            'summary' => $this->summary($row),
            'offer_status' => $status->value,
        ]);

        if ($status === OfferStatus::ToBeSubmitted || $proposal->current_version_id === null) {
            return;
        }

        // Listede tarih yoksa teklif en gec liste tarihinde verilmistir.
        $submittedAt = Carbon::createFromFormat('d.m.Y H:i', ($row['offer_date'] ?: self::LIST_DATE).' 12:00', DisplayTime::zone());

        $versions = app(ProposalVersionService::class);

        foreach ([ProposalVersionStatus::Review, ProposalVersionStatus::Approved, ProposalVersionStatus::Submitted] as $target) {
            $versions->changeStatus(
                (int) $proposal->current_version_id,
                $target,
                $target === ProposalVersionStatus::Submitted ? SubmissionChannel::Email : null,
                null,
                $target === ProposalVersionStatus::Submitted && $submittedAt instanceof Carbon ? $submittedAt : null,
            );
        }
    }

    /** Listedeki butun teklifleri kaybedilen potansiyel is "Kaybedildi" olur. */
    private function closeIfAllLost(BusinessCase $case): bool
    {
        $statuses = Proposal::query()->where('business_case_id', $case->getKey())->pluck('offer_status');

        if ($statuses->isEmpty() || $statuses->contains(fn (?OfferStatus $status): bool => $status !== OfferStatus::Lost)) {
            return false;
        }

        $case->refresh();

        if (! $case->acquisition_stage->canTransitionTo(AcquisitionStage::Lost)) {
            return false;
        }

        app(BusinessCaseService::class)->changeStage($case, AcquisitionStage::Lost, 'KKS teklif takip listesinde kaybedilen teklif (kırmızı satır).');

        return true;
    }

    /** @param  array<string, mixed>  $row */
    private function summary(array $row): string
    {
        $lines = ['KKS teklif takip listesinden aktarıldı ('.self::LIST_DATE.', '.$row['sheet'].', satır '.$row['excel_row'].').'];
        $dateLabel = $row['offer_status'] === OfferStatus::ToBeSubmitted->value ? 'Teklif verme tarihi' : 'Teklif tarihi';
        $power = trim((string) $row['power_mwp']);
        $storage = trim((string) $row['storage_mwh']);

        foreach ([
            'Kapsam' => (string) $row['scope'],
            'Proje gücü' => $power !== '' && is_numeric($power) ? $power.' MWp' : $power,
            'Depolama gücü' => $storage !== '' && is_numeric($storage) ? $storage.' MWh' : $storage,
            'Proje durumu' => (string) $row['status'],
            $dateLabel => (string) ($row['offer_date'] ?? '') !== ''
                ? (string) $row['offer_date']
                : trim($row['offer_date_text'].($row['offer_status'] === OfferStatus::ToBeSubmitted->value ? '' : ' (listede tarih yok; gönderim tarihi liste tarihi '.self::LIST_DATE.' alındı)')),
        ] as $label => $value) {
            if (trim($value) !== '') {
                $lines[] = $label.': '.trim($value);
            }
        }

        if ($row['offer_status'] === OfferStatus::Lost->value) {
            $lines[] = 'Listede kırmızı işaretli: kaybedilen teklif.';
        }

        return implode("\n", $lines);
    }

    /** RealPartySeeder ile ayni kural: kucuk harf, tek bosluk. */
    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->squish()->value();
    }
}
