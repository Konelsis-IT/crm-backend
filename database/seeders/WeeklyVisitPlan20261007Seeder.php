<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Acquisition\BusinessOutcome;
use App\Enums\Party\MeetingPlanSource;
use App\Enums\Party\MeetingPlanStatus;
use App\Models\Acquisition\BusinessCase;
use App\Models\Party\ContactRelationship;
use App\Models\Party\MeetingPlan;
use App\Models\Party\Party;
use App\Models\Party\PartyMeetingNote;
use App\Models\Personnel\Personnel;
use App\Services\Audit\ActorContext;
use App\Services\Party\ContactRelationshipService;
use App\Services\Party\MeetingPlanService;
use App\Services\Party\PartyMeetingNoteService;
use App\Services\Party\PartyRoleService;
use App\Services\Party\PartyService;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\TransactionRunner;
use Database\Seeders\Support\ProtectedSeeder;
use Database\Seeders\Support\SeedGuard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Haftalik ziyaret plani 07.10.2026 surumu ve gorusme raporlari (D-171,
 * 7 Ekim 2026 kullanici talimati: "Haftalik ziyaret planini ekleyelim ...
 * guncel tarih icin olanlar geldi, ekleyelim, eksikleri tamamlayalim. Uygun
 * yatirimci projeleriyle ve potansiyel is ile baglayalim"; "bunlarda ilgili
 * uzun gorusme notlari veya raporlar, bunlari da dogru sekilde ekleyelim").
 *
 * Kaynak: data/weekly_visit_plans_2026_10_07.php (sekiz hafta, 21.09
 * aktarimiyla ayni ayristirici) ve data/visit_reports_2026_10_07.php (yedi
 * Word raporu). Kurallar WeeklyVisitPlanSeeder (D-109) ile aynidir; ek olarak:
 *
 * - Firma eslestirmesi kullanicinin sohbette verdigi kararlardir (ALIASES,
 *   NEW_PARTIES, SPLITS): OZE Grup notlari OZE 2 ve OZE 3'e; IC ENTERRA = IC
 *   Holding Ictas Enerji; Ulusoy Elektrik = ULUSOY ENERJI; AK JEO notlari Ak
 *   Jeo'ya (Omer Akkoyun iki firmanin da yetkilisi); A1 Grup / A1
 *   Yenilenebilir tek taraf; Artibir Grup / Artibir Enerji tek taraf; Pasifik
 *   Enerji ayri firma. Birlesmis taraf hedefine gider (D-170).
 * - Var olan not (ayni ya da birbirini iceren metin) tekrar yazilmaz; plan
 *   ayni taraf + tarih + aktarim kaynagiyla bir kez acilir. Ayni haftadaki
 *   acik aktarim plani nottan sonra gerceklesti olur.
 * - Rapor: plandaki kisa notun ayrintisidir; nota "Ziyaret raporu" bolumu
 *   olarak eklenir (not zaten varsa sonuna eklenir; eski metin silinmez).
 *   ISTRICH'in baska firmalar icin verdigi bilgiler o firmalara da
 *   "ISTRICH'ten bilgi" notu olarak yazilir (kullanici karari).
 * - Is baglantisi: not, firmanin acik islerinden basligindaki guc (MW) ve
 *   yer adlari nottaki metinle en iyi uyusana baglanir; uyusma yoksa, not bir
 *   projeden soz ediyorsa ve firmanin tek acik isi varsa ona. Potansiyel isler listesindeki ayni
 *   metinli satirin isine her zaman baglanir. Bagi bos var olan nota bag
 *   eklenir (ileri giden guncelleme).
 *
 * FirmaTakipUpdate20261007Seeder ve PotentialJobs20261007Seeder'dan sonra
 * calisir. Her satir seed arsivine duser (bir kez); var olan kayitta yalniz
 * SeedGuard::allowingUpdates() icindeki ileri giden islemler yapilir.
 * preview() hicbir sey yazmaz. Kurulumda DEPLOY_SEEDERS ile calisir.
 */
final class WeeklyVisitPlan20261007Seeder extends ProtectedSeeder
{
    public const OWNER = 'ersin.ozdemir@konelsis.com';

    private const DATA_FILE = 'weekly_visit_plans_2026_10_07.php';

    private const REPORT_FILE = 'visit_reports_2026_10_07.php';

    /**
     * Plandaki firma adi => sistemdeki taraf adlari (ilk bulunan; birlesmis
     * taraf hedefine gider).
     *
     * @var array<string, list<string>>
     */
    public const ALIASES = [
        'İSKUR HOLDİNG' => ['İSKUR HOLDİNG', 'ISKUR HOLDİNG'],
        'ISKUR HOLDİNG' => ['İSKUR HOLDİNG', 'ISKUR HOLDİNG'],
        'AK JEO ENERJİ' => ['Ak Jeo Enerji Üretim San. Ve Tic. A.Ş'],
        'AK JEO ENERJİ (ALTERNATİF ENERJİ-PWD' => ['Ak Jeo Enerji Üretim San. Ve Tic. A.Ş'],
        'EFOR HOLDİNG' => ['EFOR ENERJİ'],
        'LİMAK HOLDİNG' => ['Limak Holding', 'LİMAK YENİLENEBİLİR ENERJİ'],
        'TÜREB' => ['TUREB'],
        'FERNAS' => ['FERNAS ŞİRKETLER GRUBU'],
        'IC ENTERRA' => ['IC HOLDİNG İÇTAŞ ENERJİ'],
        'KOLİN İNŞAAT' => ['KOLİN'],
        'BAYLAZ GRUP' => ['BAYLAZ GRUP ENERJİ İNŞAAT ANONİM ŞİRKETİ'],
        'DİYAR BATARYA ENERJİ - ZEDUR ENERJİ' => ['DİYAR BATARYA SİSTEMLERİ VE YENİLENEBİLİR ENERJİ YATIRIMLARI ANONİM ŞİRKETİ'],
        'ZEDUR ENERJİ - DİYAR BATARYA' => ['DİYAR BATARYA SİSTEMLERİ VE YENİLENEBİLİR ENERJİ YATIRIMLARI ANONİM ŞİRKETİ'],
        'ACM ENERJİ MÜHENDİSLİK' => ['ACM ENERJİ'],
        'EKVATOR ENERJİ -KUTUP ENERJİ' => ['EKVATOR ENERJİ'],
        'MATLI GRUP' => ['MATLI ŞİRKETLER GRUBU MATLI YEM'],
        'GMC SOLAR - YAYLA AGRO' => ['GMC SOLAR İNŞAAT TAAHHÜT GIDA ENERJİ ÜRETİM SANAYİ VE TİCARET A.Ş.'],
        'HARPUT TEKSTİL' => ['HARPUT TEKSTİL SANAYİ VE TİCARET A.Ş.'],
        'VAKKO TEKSTİL' => ['VAKKO TEKSTİL ve HAZIR GİYİM SANAYİ İŞLETMLERİ A.Ş.'],
        'GÜGLER' => ['GUGLER SU TRÜBÜNLERİ'],
        'SELENKA' => ['SELENKA ENERJİ'],
        'HİVE ENERJİ ÜRETİM A.Ş.' => ['HİVE ELEKTRİK ÜRETİM SANAYİ VE TİCARET ANONİM ŞİRKETİ'],
        'MAKİ ENERJİ' => ['MAKİ ELEKTRİK ENERJİ OPERASYONLARI YÖNETİMİ A.Ş.'],
        'Ulusoy Elektrik Enerji Yatırımları A.Ş.' => ['ULUSOY ENERJİ'],
        'YILDIZLAR İNŞAAT - ERN HOLDİNG' => ['ERN HOLDİNG'],
        'YILDIRIM GRUP' => ['YILDIRIM HOLDİNG'],
        'ENTEK ENERJİ A.Ş.' => ['ENTEK ELEKTRİK ÜRETİMİ A.Ş. (KOÇ Holding)', 'Entek Elektrik Üretim Anonim Şirketi'],
        'TÜPRAG MADEN' => ['TÜPRAG MADENCİLİK'],
        'TEİAŞ ALT KADEME' => ['TEIAS'],
        'TEDAŞ ALT KADEME' => ['TEDAS'],
        'ERL SOLAR' => ['ERL'],
        'ERGES MÜŞAVİRLİK' => ['ERGES'],
        'AKENSA' => ['Akensa Enerji Mühendislik Danışmanlık Sanayi ve Ticaret Limited Şirketi Çal Enerji Üretim Anonim Şirketi'],
        'VOİTH HYDRO' => ['VOITH HYDRO'],
        'ISTRICH' => ['İSTRİCH'],
    ];

    /**
     * Sistemde olmayan, adi belli taraflar: plandaki ad => [kisa ad, uzun ad,
     * network notu]. Birden cok yazilis ayni tarafa gider.
     *
     * @var array<string, array{0: string|null, 1: string, 2: string|null}>
     */
    public const NEW_PARTIES = [
        'A1 GRUP' => ['A1 GRUP', 'A1 YENİLENEBİLİR ENERJİ', 'Güler Yatırım Holding iştiraki (haftalık ziyaret planı, 28.09.2026).'],
        'A1 YENİLENEBİLİR ENERJİ' => ['A1 GRUP', 'A1 YENİLENEBİLİR ENERJİ', 'Güler Yatırım Holding iştiraki (haftalık ziyaret planı, 28.09.2026).'],
        'Artıbir Grup' => ['ARTIBİR GRUP', 'ARTIBİR ENERJİ A.Ş.', null],
        'ARTIBİR ENERJİ A.Ş.' => ['ARTIBİR GRUP', 'ARTIBİR ENERJİ A.Ş.', null],
        'HEKİMOĞLU DÖKÜM SANAYİ' => [null, 'HEKİMOĞLU DÖKÜM SANAYİ NAK. ve TİC. A.Ş.', null],
        'VRES ENERJİ BAYTEMİZ ENERJİ' => [null, 'VRES ENERJİ', null],
        'VRES ENERJİ' => [null, 'VRES ENERJİ', null],
    ];

    /** Ikiye yazilan firma: kullanici karari, OZE Grup notlari ikisine de. */
    public const SPLITS = [
        'OZE GRUP' => [
            ['OZE 2 ENERJİ A.Ş.', 'OZE 2 ENERJİ ANONİM ŞİRKETİ'],
            ['OZE 3 ENERJİ A.Ş.', 'OZE 3 ENERJİ ANONİM ŞİRKETİ'],
        ],
    ];

    /** Bag puaninda sayilmayan baslik kelimeleri. */
    private const STOP = ['proje', 'projesi', 'enerji', 'santrali', 'elektrik', 'depolama', 'depolamalı', 'tesisi', 'güneş', 'rüzgar', 'rüzgâr', 'holding', 'grup', 'grubu', 'potansiyel', 'teklifi', 'türbin', 'adet', 'anonim', 'şirketi'];

    private bool $dry = false;

    private ?FirmaTakipUpdate20261007Seeder $resolver = null;

    /** @var array<string, int> */
    private array $personnel = [];

    /** @var list<array{party: int|null, new: string|null, title: string, note: string|null}>|null */
    private ?array $plannedCases = null;

    /** @var array<string, int> */
    private array $totals = ['parties' => 0, 'contacts' => 0, 'notes' => 0, 'reports' => 0, 'links' => 0, 'plans' => 0, 'completed' => 0, 'skipped' => 0];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B34')) {
            $this->command?->warn('B34 (gorusme plani) uygulanmamis; haftalik ziyaret plani 07.10 atlandi.');

            return;
        }

        if (($admin = SystemAccountSeeder::actor()) !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $data = $this->reports();

        foreach ($this->rows() as $row) {
            $this->guarded('visit-1007:'.$row['excel'], fn (): mixed => $this->visitRow($row, $data['attach'][$row['excel']] ?? null)['done']);
        }

        foreach ($data['standalone'] as $report) {
            $this->guarded('report-1007:'.$report['key'], fn (): mixed => $this->standalone($report)['done']);
        }

        foreach ($data['sections'] as $section) {
            $this->guarded('section-1007:'.$section['key'], fn (): mixed => $this->section($section)['done']);
        }

        $this->command?->info(sprintf(
            'Haftalik ziyaret plani 07.10: %d yeni taraf, %d kisi, %d gorusme notu, %d rapor eklendi, %d nota is baglandi, %d plan, %d plan gerceklesti; %d satir atlandi.',
            $this->totals['parties'], $this->totals['contacts'], $this->totals['notes'], $this->totals['reports'],
            $this->totals['links'], $this->totals['plans'], $this->totals['completed'], $this->totals['skipped'],
        ));
    }

    /**
     * Yazmadan: her satirin tarafi, notlari, bagli isleri ve planlari.
     *
     * @return array{rows: list<array<string, mixed>>, standalone: list<array<string, mixed>>, sections: list<array<string, mixed>>}
     */
    public function preview(): array
    {
        $this->dry = true;
        $this->resolver()->planMerges();
        $data = $this->reports();
        $out = ['rows' => [], 'standalone' => [], 'sections' => []];

        foreach ($this->rows() as $row) {
            $result = $this->visitRow($row, $data['attach'][$row['excel']] ?? null);
            unset($result['done']);
            $out['rows'][] = ['excel' => $row['excel'], 'party' => $row['party'], ...$result];
        }

        foreach ($data['standalone'] as $report) {
            $result = $this->standalone($report);
            unset($result['done']);
            $out['standalone'][] = ['key' => $report['key'], ...$result];
        }

        foreach ($data['sections'] as $section) {
            $result = $this->section($section);
            unset($result['done']);
            $out['sections'][] = ['key' => $section['key'], ...$result];
        }

        $this->dry = false;

        return $out;
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $write
     */
    private function guarded(string $key, \Closure $write): void
    {
        $this->row($key, function () use ($key, $write): mixed {
            try {
                return SeedGuard::allowingUpdates(fn (): mixed => app(TransactionRunner::class)->run($write, 1));
            } catch (\Throwable $exception) {
                $this->totals['skipped']++;
                $this->command?->warn(sprintf('Haftalik ziyaret plani 07.10 %s atlandi (sonra yeniden denenecek): %s', $key, $exception->getMessage()));

                return null;
            }
        });
    }

    /**
     * Plan satiri: taraf(lar), eksik kisi, not (rapor ekli), plan.
     *
     * @param  array<string, mixed>  $row
     * @param  array{source: string, text: string}|null  $report
     * @return array{done: bool, parties: list<string>, steps: list<string>}
     */
    private function visitRow(array $row, ?array $report): array
    {
        $out = ['done' => true, 'parties' => [], 'steps' => []];
        $names = $this->rowNames($row);

        foreach ($names as $candidates) {
            [$party, $label] = $this->party($candidates, (string) $row['party'], (string) $row['role']);
            $out['parties'][] = $label;
            $contactId = $this->contacts($party, (array) $row['contacts'], $out['steps']);
            $participants = $this->ids((array) $row['participants']);

            foreach (array_values((array) $row['notes']) as $index => $note) {
                $attached = $index === 0 ? $report : null;
                $this->note($party, [
                    'text' => (string) $note['text'],
                    'report' => $attached,
                    'noted_on' => (string) $note['noted_on'],
                    'channel' => (string) $note['channel'],
                    'personnel' => $note['personnel'] ?? self::OWNER,
                    'contact_id' => $contactId,
                    'participants' => $participants,
                    'week' => (string) $row['week'],
                    'subject' => null,
                ], $out['steps']);
            }

            if (is_array($row['plan'] ?? null)) {
                $this->plan($party, $row, $contactId, $participants, $out['steps']);
            }
        }

        return $out;
    }

    /**
     * Plana bagli olmayan rapor (AK JEO, Tayfurlar 21.09).
     *
     * @param  array<string, mixed>  $report
     * @return array{done: bool, parties: list<string>, steps: list<string>}
     */
    private function standalone(array $report): array
    {
        $out = ['done' => true, 'parties' => [], 'steps' => []];
        [$party, $label] = $this->party((array) $report['party'], (string) $report['party'][0], 'employer');
        $out['parties'][] = $label;
        $contacts = $report['contact'] !== null ? [['name' => (string) $report['contact'], 'department' => null]] : [];
        $contactId = $this->contacts($party, $contacts, $out['steps']);

        $this->note($party, [
            'text' => (string) $report['text'],
            'report' => null,
            'noted_on' => (string) $report['noted_on'],
            'channel' => (string) $report['channel'],
            'personnel' => self::OWNER,
            'contact_id' => $contactId,
            'participants' => [],
            'week' => null,
            'subject' => 'Görüşme raporu ('.$report['source'].')',
        ], $out['steps']);

        return $out;
    }

    /**
     * ISTRICH'in baska firma icin verdigi bilgi: o firmaya not.
     *
     * @param  array<string, mixed>  $section
     * @return array{done: bool, parties: list<string>, steps: list<string>}
     */
    private function section(array $section): array
    {
        $out = ['done' => true, 'parties' => [], 'steps' => []];

        foreach ((array) $section['parties'] as $candidates) {
            [$party, $label] = $this->party($candidates, (string) $candidates[0], 'employer');
            $out['parties'][] = $label;

            $this->note($party, [
                'text' => (string) $section['text'],
                'report' => null,
                'noted_on' => (string) $section['noted_on'],
                'channel' => 'other',
                'personnel' => self::OWNER,
                'contact_id' => null,
                'participants' => [],
                'week' => null,
                'subject' => (string) $section['subject'],
            ], $out['steps']);
        }

        return $out;
    }

    /**
     * Not: varsa (ayni / birbirini iceren metin) rapor ve is bagi eklenir,
     * yoksa yazilir.
     *
     * @param  array<string, mixed>  $note
     * @param  list<string>  $steps
     */
    private function note(?Party $party, array $note, array &$steps): void
    {
        $text = trim((string) $note['text']);
        $report = $note['report'];
        $reportText = $report !== null ? "\n\nZiyaret raporu (".$report['source']."):\n".trim((string) $report['text']) : '';
        $case = $this->link($party, $text.$reportText);
        $caseLabel = $case === null ? '' : ' -> iş: '.$case['title'];
        $short = Str::limit(str_replace("\n", ' ', $text), 90);
        $existing = $party !== null ? $this->existingNote($party, $text) : null;

        if ($existing !== null) {
            $changes = [];

            if ($report !== null && ! str_contains(self::fingerprint((string) $existing->note), self::fingerprint(Str::limit((string) $report['text'], 200, '')))) {
                $changes['note'] = rtrim((string) $existing->note).$reportText;
                $steps[] = 'var olan nota rapor eklenir ('.$report['source'].'): '.$short;
            }

            if ($case !== null && $case['id'] !== null && $existing->business_case_id === null) {
                $changes['business_case_id'] = $case['id'];
                $steps[] = 'var olan not işe bağlanır'.$caseLabel;
            }

            if ($changes !== [] && ! $this->dry) {
                $existing->forceFill($changes)->save();
                $this->totals[isset($changes['note']) ? 'reports' : 'links'] += 1;
            }

            return;
        }

        $steps[] = 'yeni not '.$note['noted_on'].' ('.$note['channel'].')'.($report !== null ? ' + rapor ('.$report['source'].')' : '').$caseLabel.': '.$short;

        if ($this->dry || $party === null) {
            return;
        }

        $planId = $note['week'] !== null ? $this->openWeekPlan($party, (string) $note['week']) : null;

        /** @var PartyMeetingNote $created */
        $created = app(PartyMeetingNoteService::class)->create(array_filter([
            'party_id' => $party->getKey(),
            'contact_relationship_id' => $note['contact_id'],
            'personnel_id' => $this->id((string) $note['personnel']) ?? $this->id(self::OWNER),
            'noted_on' => $note['noted_on'],
            'channel' => $note['channel'],
            'subject' => $note['subject'],
            'note' => $text.$reportText,
            'business_case_id' => $case['id'] ?? null,
            PartyMeetingNoteService::PLAN_CONTEXT => $planId,
        ], static fn (mixed $value): bool => $value !== null));
        $this->totals['notes']++;
        $this->totals['reports'] += $report !== null ? 1 : 0;
        $this->totals['completed'] += $planId !== null ? 1 : 0;

        if ($note['participants'] !== []) {
            $plan = MeetingPlan::query()->where('meeting_note_id', $created->getKey())->first();

            if ($plan instanceof MeetingPlan) {
                app(MeetingPlanService::class)->ensureParticipants($plan, $note['participants']);
            }
        }
    }

    /** Tarafta ayni ya da birbirini iceren metinli not (arsivdekiler dahil). */
    private function existingNote(Party $party, string $text): ?PartyMeetingNote
    {
        $fingerprint = self::fingerprint($text);

        /** @var PartyMeetingNote|null $note */
        $note = PartyMeetingNote::query()
            ->whereIn('party_id', $this->resolver()->ownersOf($party))
            ->get()
            ->first(fn (PartyMeetingNote $item): bool => FirmaTakipUpdate20261007Seeder::known($fingerprint, [self::fingerprint((string) $item->note)])
                || self::overlaps($text, (string) $item->note));

        return $note;
    }

    /** Ayni haftanin notsuz, acik aktarim plani (21.09 aktariminda planli kalan satir). */
    private function openWeekPlan(Party $party, string $week): ?int
    {
        $start = Carbon::parse($week);

        $id = MeetingPlan::query()
            ->where('party_id', $party->getKey())
            ->where('source', MeetingPlanSource::Import->value)
            ->where('status', MeetingPlanStatus::Planned->value)
            ->whereNull('meeting_note_id')
            ->whereBetween('planned_on', [$start->toDateString(), $start->copy()->addDays(6)->toDateString()])
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<int>  $participants
     * @param  list<string>  $steps
     */
    private function plan(?Party $party, array $row, ?int $contactId, array $participants, array &$steps): void
    {
        $plan = $row['plan'];
        $note = $plan['note'] ?? null;

        if (str_contains((string) $row['party'], 'ALT KADEME')) {
            $note = trim('Alt kademe ile görüşülecek. '.($note ?? ''));
        }

        $exists = $party !== null && MeetingPlan::query()
            ->where('party_id', $party->getKey())
            ->whereDate('planned_on', $plan['planned_on'])
            ->where('source', MeetingPlanSource::Import->value)
            ->exists();

        if ($exists) {
            return;
        }

        $steps[] = 'plan '.$plan['planned_on'].' ('.$plan['status'].')'.($note !== null ? ': '.Str::limit($note, 60) : '');

        if ($this->dry || $party === null) {
            return;
        }

        app(MeetingPlanService::class)->create([
            'party_id' => $party->getKey(),
            'contact_relationship_id' => $contactId,
            'personnel_id' => $this->id($plan['personnel'] ?? self::OWNER),
            'planned_on' => $plan['planned_on'],
            'channel' => $plan['channel'],
            'note' => $note,
            'status' => $plan['status'],
            'source' => MeetingPlanSource::Import->value,
            'completed_at' => $plan['status'] === MeetingPlanStatus::Done->value ? Carbon::parse($plan['planned_on'], 'UTC') : null,
            'participant_ids' => $participants,
        ]);
        $this->totals['plans']++;
    }

    /**
     * Notun bagli olacagi is: Potansiyel isler listesinde ayni metinli satirin
     * isi; yoksa baslikta gecen guc (MW) ve yer adlariyla en iyi uyusan acik
     * is; uyusma yoksa tek acik is.
     *
     * @return array{id: int|null, title: string}|null
     */
    private function link(?Party $party, string $text): ?array
    {
        $candidates = [];

        if ($party !== null) {
            foreach (BusinessCase::query()->whereIn('primary_party_id', $this->resolver()->ownersOf($party))->get() as $case) {
                if (! in_array($case->outcome, [BusinessOutcome::Lost, BusinessOutcome::Cancelled], true)) {
                    $candidates[] = ['id' => (int) $case->getKey(), 'title' => (string) $case->title, 'note' => null];
                }
            }
        }

        // Potansiyel isler listesinden acilan isler (onizlemede henuz yok).
        foreach ($this->plannedCases() as $planned) {
            $same = $party !== null ? $planned['party'] === (int) $party->getKey() : false;

            if ($same && ! in_array($planned['title'], array_column($candidates, 'title'), true)) {
                $candidates[] = ['id' => null, 'title' => $planned['title'], 'note' => $planned['note']];
            } elseif ($same) {
                foreach ($candidates as $i => $candidate) {
                    if ($candidate['title'] === $planned['title']) {
                        $candidates[$i]['note'] = $planned['note'];
                    }
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        foreach ($candidates as $candidate) {
            if ($candidate['note'] !== null && self::similar($candidate['note'], $text)) {
                return ['id' => $candidate['id'], 'title' => $candidate['title']];
            }
        }

        $scores = [];
        $haystack = self::fingerprint($text);
        $numbers = self::megawattNumbers($text);

        foreach ($candidates as $i => $candidate) {
            $score = 0;

            foreach (self::megawattNumbers($candidate['title']) as $mw) {
                $score += in_array($mw, $numbers, true) ? 3 : 0;
            }

            foreach (self::keywords($candidate['title'], $party) as $word) {
                $score += str_contains($haystack, $word) ? 1 : 0;
            }

            $scores[$i] = $score;
        }

        arsort($scores);
        $best = array_key_first($scores);
        $top = $scores[$best];
        $ties = count(array_filter($scores, static fn (int $score): bool => $score === $top));

        if ($top >= 3 && $ties === 1) {
            return ['id' => $candidates[$best]['id'], 'title' => $candidates[$best]['title']];
        }

        // Tek acik is: yalniz not bir projeden soz ediyorsa (guc, GES/RES/HES, EDT, teklif);
        // "tanitim maili atildi" gibi genel not firmada kalir.
        $aboutProject = preg_match('/\d\s*mw|\b(?:d?ges|d?res|d?hes|edt|teklif)/iu', $text) === 1;

        return count($candidates) === 1 && $aboutProject ? ['id' => $candidates[0]['id'], 'title' => $candidates[0]['title']] : null;
    }

    /**
     * Potansiyel isler listesinden acilan (ya da acilacak) isler: taraf, baslik, not.
     *
     * @return list<array{party: int|null, new: string|null, title: string, note: string|null}>
     */
    private function plannedCases(): array
    {
        if ($this->plannedCases !== null) {
            return $this->plannedCases;
        }

        $this->plannedCases = [];

        foreach ((new PotentialJobs20261007Seeder)->plannedCases() as $planned) {
            $party = $this->resolver()->findParty($planned['names']);
            $this->plannedCases[] = ['party' => $party?->getKey() !== null ? (int) $party->getKey() : null, 'new' => $planned['new'], 'title' => $planned['title'], 'note' => $planned['note']];
        }

        return $this->plannedCases;
    }

    /**
     * Taraf: bulunur (birlesmis taraf hedefine gider) ya da acilir.
     *
     * @param  list<string>  $candidates
     * @return array{0: Party|null, 1: string}
     */
    private function party(array $candidates, string $listName, string $role): array
    {
        // Bulunamayan taraf kararlastirilan adla acilir (or. "ERN HOLDING", "TUPRAG MADENCILIK").
        [$short, $long, $network] = self::NEW_PARTIES[$listName] ?? [null, $candidates[0] ?? $listName, null];
        $party = $this->resolver()->findParty(array_values(array_unique([...$candidates, $long, ...array_filter([$short])])));

        if ($party !== null) {
            return [$party, '#'.$party->getKey().' '.$party->display_name];
        }

        if ($this->dry) {
            return [null, 'YENİ: '.$long.($short !== null ? ' (kısa: '.$short.')' : '')];
        }

        /** @var Party $party */
        $party = app(PartyService::class)->create([
            'party_kind' => 'organization',
            'display_name' => $short ?? $long,
            'country_code' => 'TR',
            'status' => 'prospect',
            'network_note' => $network,
            'organization_profile' => array_filter(['legal_name' => $long, 'trade_name' => $short], static fn (?string $value): bool => $value !== null),
        ]);

        app(PartyRoleService::class)->create(['party_id' => $party->getKey(), 'role_code' => $role, 'status' => 'active']);
        $this->totals['parties']++;

        return [$party, '#'.$party->getKey().' '.$party->display_name.' (yeni)'];
    }

    /**
     * Satirin taraf adlari: OZE Grup iki taraf; digerleri esleme listesi,
     * 21.09 aktarimindaki 'match' ya da plandaki ad.
     *
     * @param  array<string, mixed>  $row
     * @return list<list<string>>
     */
    private function rowNames(array $row): array
    {
        $name = (string) $row['party'];

        if (isset(self::SPLITS[$name])) {
            return self::SPLITS[$name];
        }

        return [array_values(array_unique(array_filter([...(self::ALIASES[$name] ?? []), (string) ($row['match'] ?? ''), $name])))];
    }

    /**
     * Eksik kisileri acar; satirin ilk kisisinin kimligini doner.
     *
     * @param  list<array{name: string, department: ?string}>  $contacts
     * @param  list<string>  $steps
     */
    private function contacts(?Party $party, array $contacts, array &$steps): ?int
    {
        $existing = [];

        if ($party !== null) {
            foreach (ContactRelationship::query()->whereIn('organization_party_id', $this->resolver()->ownersOf($party))->get() as $contact) {
                $existing[FirmaTakipUpdate20261007Seeder::personKey($contact->displayName())] = $contact;
            }
        }

        $first = null;
        $empty = $existing === [];

        foreach ($contacts as $row) {
            $name = trim((string) $row['name']);
            $key = FirmaTakipUpdate20261007Seeder::personKey($name);

            if ($key === '') {
                continue;
            }

            $contact = $existing[$key] ?? null;

            if ($contact === null) {
                $steps[] = 'yeni kişi: '.$name;

                if (! $this->dry && $party !== null) {
                    /** @var ContactRelationship $contact */
                    $contact = app(ContactRelationshipService::class)->create([
                        'organization_party_id' => $party->getKey(),
                        'contact_name' => $name,
                        'relationship_role' => 'other',
                        'department_note' => filled($row['department'] ?? null) ? (string) $row['department'] : null,
                        'is_primary' => $empty,
                    ]);
                    $existing[$key] = $contact;
                    $empty = false;
                    $this->totals['contacts']++;
                }
            }

            $first ??= $contact instanceof ContactRelationship ? (int) $contact->getKey() : null;
        }

        return $first;
    }

    /**
     * Metin baska bir metinle buyuk olcude ayni mi: 40 harflik parcalarin
     * yarisindan cogu digerinde geciyor (Potansiyel isler / rapor / plan notu).
     */
    public static function overlaps(string $a, string $b): bool
    {
        [$short, $long] = mb_strlen(self::fingerprint($a)) <= mb_strlen(self::fingerprint($b))
            ? [self::fingerprint($a), self::fingerprint($b)]
            : [self::fingerprint($b), self::fingerprint($a)];

        if (mb_strlen($short) < 40) {
            return $short !== '' && str_contains($long, $short);
        }

        $hits = 0;
        $windows = 0;

        for ($offset = 0; $offset + 40 <= mb_strlen($short); $offset += 20) {
            $windows++;
            $hits += str_contains($long, mb_substr($short, $offset, 40)) ? 1 : 0;
        }

        return $windows > 0 && $hits / $windows >= 0.5;
    }

    /**
     * $summary metninin (en az 8 kelime) kelimelerinin en az %60'i $source'ta geciyor mu.
     * Potansiyel isler listesindeki notlar plan notu ya da raporun ozetidir
     * (kelimeler ayni, cumleler farkli); karakter karsilastirmasi yakalamaz.
     */
    public static function similar(string $summary, string $source): bool
    {
        $words = static function (string $text): array {
            $lower = mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $text), 'UTF-8');

            return array_values(array_unique(array_filter(preg_split('/[^\p{L}\p{N}]+/u', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [], static fn (string $word): bool => mb_strlen($word) >= 4)));
        };
        $summaryWords = $words($summary);

        if (count($summaryWords) < 8) {
            return self::overlaps($summary, $source);
        }

        return count(array_intersect($summaryWords, $words($source))) / count($summaryWords) >= 0.6;
    }

    /**
     * Potansiyel isler listesinin notu bu aktarimda tarihli olarak var mi
     * (plan notu, rapor ya da ISTRICH bolumu). Varsa PotentialJobs20261007Seeder
     * notu tarihsiz ikinci kez yazmaz; bu seeder tarihli notu o ise baglar.
     */
    public static function covers(string $text): bool
    {
        static $sources = null;

        if ($sources === null) {
            $sources = [];

            foreach (require __DIR__.'/data/'.self::DATA_FILE as $row) {
                foreach ((array) $row['notes'] as $note) {
                    $sources[] = (string) $note['text'];
                }
            }

            $reports = require __DIR__.'/data/'.self::REPORT_FILE;

            foreach ([...array_values($reports['attach']), ...$reports['standalone'], ...$reports['sections']] as $item) {
                $sources[] = (string) $item['text'];
            }
        }

        foreach ($sources as $source) {
            if (self::similar($text, $source)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> "120MW", "140 mw", "38,8 MW" => "120", "140", "38.8" */
    private static function megawattNumbers(string $text): array
    {
        preg_match_all('/(\d+(?:[.,]\d+)?)\s*mw/iu', $text, $matches);

        return array_values(array_unique(array_map(static fn (string $n): string => str_replace(',', '.', $n), $matches[1])));
    }

    /** @return list<string> basliktaki ayirt edici kelimeler (yer adi gibi) */
    private static function keywords(string $title, ?Party $party): array
    {
        $lower = static fn (string $s): string => mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $s), 'UTF-8');
        $own = $party !== null ? preg_split('/[^\p{L}]+/u', $lower((string) $party->display_name), -1, PREG_SPLIT_NO_EMPTY) : [];
        $words = preg_split('/[^\p{L}]+/u', $lower($title), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, static fn (string $word): bool => mb_strlen($word) >= 4
            && ! in_array($word, self::STOP, true) && ! in_array($word, $own ?: [], true))));
    }

    /** Turkce kucuk harf, harf ve rakam disi atilir. */
    public static function fingerprint(string $text): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $text), 'UTF-8'));
    }

    private function resolver(): FirmaTakipUpdate20261007Seeder
    {
        return $this->resolver ??= new FirmaTakipUpdate20261007Seeder;
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = require __DIR__.'/data/'.self::DATA_FILE;

        return $rows;
    }

    /** @return array{attach: array<string, array{source: string, text: string}>, standalone: list<array<string, mixed>>, sections: list<array<string, mixed>>} */
    private function reports(): array
    {
        /** @var array{attach: array<string, array{source: string, text: string}>, standalone: list<array<string, mixed>>, sections: list<array<string, mixed>>} $reports */
        $reports = require __DIR__.'/data/'.self::REPORT_FILE;

        return $reports;
    }

    private function id(?string $email): ?int
    {
        if ($email === null) {
            return null;
        }

        if ($this->personnel === []) {
            $this->personnel = Personnel::query()->pluck('id', 'email')
                ->mapWithKeys(fn ($id, $mail): array => [Str::lower((string) $mail) => (int) $id])
                ->all();
        }

        return $this->personnel[Str::lower($email)] ?? null;
    }

    /**
     * @param  list<string>  $emails
     * @return list<int>
     */
    private function ids(array $emails): array
    {
        return array_values(array_filter(array_map(fn (string $email): ?int => $this->id($email), $emails)));
    }
}
