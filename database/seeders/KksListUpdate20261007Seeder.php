<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\ProposalVersionStatus;
use App\Enums\Acquisition\SubmissionChannel;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Party\PartyMeetingNote;
use App\Models\Personnel\Personnel;
use App\Services\Acquisition\ProposalService;
use App\Services\Acquisition\ProposalVersionService;
use App\Services\Audit\ActorContext;
use App\Services\Party\PartyMeetingNoteService;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\TransactionRunner;
use App\Support\DisplayTime;
use Database\Seeders\Support\SeedGuard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * KKS teklif takip listesinin 07.10.2026 guncellemesi (D-169, 7 Ekim 2026
 * kullanici talimati: "eksik verileri yine excel'e girmisler, bu yeni bilgileri
 * iceri aktarmaliyiz. Verilecek ve verilen teklifler ... Bazi belgeler bir
 * sonraki asamaya gecmis olabilir, o durumlarda da guncelleme yapacagiz. Ama
 * daha once kayitli bilgileri kesinlikle silmiyor, goz ardi etmiyoruz ...
 * eksigi tamamliyor veya guncel durumlarini aktariyor olabiliriz").
 *
 * Kaynak: data/kks_proposals_2026_10_07.php; onceki liste data/kks_proposals.php
 * (22.09.2026, KksProposalSeeder). Her satir icin:
 *
 * - Satir onceki listede varsa (firma + proje adi + kapsam; onceki adi bos olan
 *   ayni firma ve kapsamdaki tek satir da ayni satirdir) onun teklifi bulunur
 *   (ozetteki "22.09.2026, sayfa, satir" izi ya da potansiyel is + baslik).
 * - Teklif varsa: durum yalniz ileri gider (Verilecek -> Verilen -> Kacan
 *   firsat; Onaylandi ve Kacan firsat korunur, geri gidilmez). Verilen olunca
 *   surum Taslak -> Incelemede -> Onaylandi -> Gonderildi yurur, gonderim tarihi
 *   listedeki teklif tarihidir. Degisen bilgi (teklif tarihi, proje durumu, guc,
 *   proje adi) surum ozetine "07.10.2026 listesi" satiri olarak EKLENIR; eski
 *   satirlar silinmez, yeni surum acilmaz.
 * - Teklif yoksa (yeni satir): KksProposalSeeder kurallariyla taraf, potansiyel
 *   is ve teklif acilir (var olan taraf / potansiyel isin altina da).
 * - Notlar: "(gg.aa.yyyy" ve kisa "gg/aa" tarihleri yeni gorusme baslatir; potansiyel
 *   iste ayni metni iceren not (arsivdekiler dahil) varsa parca eklenmez, yoksa
 *   yeni gorusme notu acilir (teklife bagli). Tarihsiz parca liste tarihini alir.
 * - Listeden cikmis satirlara dokunulmaz; hicbir kayit silinmez.
 * - Butun teklifleri kaybedilen potansiyel is "Kaybedildi" olur.
 *
 * Var olan kayitlarda yalniz SeedGuard::allowingUpdates() icindeki ileri giden
 * islemler yapilir. Her satir seed arsivine duser (bir kez islenir). preview()
 * hicbir sey yazmadan yapilacaklari listeler. Kurulumda DEPLOY_SEEDERS ile calisir.
 */
final class KksListUpdate20261007Seeder extends KksProposalSeeder
{
    public const LIST_DATE = '07.10.2026';

    public const DATA_FILE = 'kks_proposals_2026_10_07.php';

    private const PREVIOUS_FILE = 'kks_proposals.php';

    private const PREVIOUS_DATE = '22.09.2026';

    /**
     * Listeden cikan satirlar icin kullanicinin karari (7 Ekim 2026: "OVA
     * Cayirlar DRES kacan firsat, ama AKSA Mersin DRES EIH verilen teklif yap").
     * Anahtar: onceki listedeki "sayfa|satir". Burada olmayan cikan satirlara
     * dokunulmaz (AKSA Mersin DRES EIH zaten Verilen teklif).
     *
     * @var array<string, string>
     */
    private const REMOVED_DECISIONS = [
        'VERİLEN TEKLİFLER|32' => 'lost',
    ];

    /** Yeni listedeki firma adlari => sistemdeki taraf adi. */
    public const PARTY_ALIASES = [
        ...KksProposalSeeder::PARTY_ALIASES,
        'GUGLER' => 'GUGLER SU TRÜBÜNLERİ',
    ];

    private bool $dry = false;

    /** @var list<string> onizlemede eklenecek not metinleri */
    private array $dryNotes = [];

    /** @var array{rows: list<array<string, mixed>>, case_rows: array<string, list<array<string, mixed>>>}|null */
    private ?array $planCache = null;

    /** @var array<int, true> durumu degisen teklifin potansiyel isi */
    private array $touchedCases = [];

    /** @var array<string, int> */
    private array $totals = ['created' => 0, 'advanced' => 0, 'summaries' => 0, 'notes' => 0, 'unchanged' => 0, 'parties' => 0, 'cases' => 0, 'skipped' => 0, 'proposals' => 0, 'lost_cases' => 0];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B29')) {
            $this->command?->warn('B29 uygulanmamis; KKS 07.10 guncellemesi atlandi.');

            return;
        }

        $ownerId = $this->ownerId();

        if ($ownerId === null) {
            $this->command?->warn(FirmaTakipBusinessCaseSeeder::OWNER_EMAIL.' bulunamadi; KKS 07.10 guncellemesi atlandi.');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $parties = [];
        $cases = [];

        foreach ($this->plan()['rows'] as $row) {
            // Satir tek islemdir; hata olursa yalniz o satir geri alinir, arsive dusmez
            // ve sonraki kurulumda yeniden denenir (kurulum durmaz). Ileri giden
            // guncellemeler (durum, ozet satiri, potansiyel is durumu) izinlidir;
            // silme ve geri alma yine durdurulur.
            $this->row('kks-1007:'.$this->rowKey($row), function () use ($row, $ownerId, &$parties, &$cases): ?Proposal {
                try {
                    return SeedGuard::allowingUpdates(fn (): ?Proposal => app(TransactionRunner::class)->run(
                        fn (): ?Proposal => $this->process($row, $ownerId, $parties, $cases)['proposal'],
                        1,
                    ));
                } catch (\Throwable $exception) {
                    // Geri alinan islemde acilmis taraf / potansiyel is onbellekte kalmasin.
                    $parties = [];
                    $cases = [];
                    $this->totals['skipped']++;
                    $this->command?->warn(sprintf('KKS 07.10 satir %s %d atlandi (sonra yeniden denenecek): %s', $row['sheet'], $row['excel_row'], $exception->getMessage()));

                    return null;
                }
            });
        }

        foreach (self::REMOVED_DECISIONS as $where => $status) {
            $this->row('kks-1007-removed:'.$where, function () use ($where, $status): ?Proposal {
                try {
                    return SeedGuard::allowingUpdates(fn (): ?Proposal => app(TransactionRunner::class)->run(
                        fn (): ?Proposal => $this->removedRow($where, OfferStatus::from($status))['proposal'],
                        1,
                    ));
                } catch (\Throwable $exception) {
                    $this->command?->warn('KKS 07.10: listeden cikan '.$where.' islenemedi (sonra yeniden denenecek): '.$exception->getMessage());

                    return null;
                }
            });
        }

        foreach (array_keys($this->touchedCases) as $caseId) {
            $case = BusinessCase::query()->find($caseId);

            try {
                if ($case !== null && SeedGuard::allowingUpdates(fn (): bool => $this->closeIfAllLost($case))) {
                    $this->totals['lost_cases']++;
                }
            } catch (\Throwable $exception) {
                $this->command?->warn('KKS 07.10: potansiyel is '.$caseId.' Kaybedildi yapilamadi: '.$exception->getMessage());
            }
        }

        $this->command?->info(sprintf(
            'KKS 07.10 guncellemesi: %d yeni teklif, %d durum ilerledi, %d ozete satir eklendi, %d yeni gorusme notu, %d degismeyen; %d yeni taraf, %d yeni potansiyel is, %d potansiyel is Kaybedildi.',
            $this->totals['created'],
            $this->totals['advanced'],
            $this->totals['summaries'],
            $this->totals['notes'],
            $this->totals['unchanged'],
            $this->totals['parties'],
            $this->totals['cases'],
            $this->totals['lost_cases'],
        ));
    }

    /**
     * Yazmadan: her satir icin bulunan teklif ve yapilacaklar.
     *
     * @return list<array<string, mixed>>
     */
    public function preview(): array
    {
        $this->dry = true;
        $parties = [];
        $cases = [];
        $out = [];

        foreach ($this->plan()['rows'] as $row) {
            $this->dryNotes = [];
            $result = $this->process($row, 0, $parties, $cases);
            unset($result['proposal']);
            $out[] = ['row' => $row['sheet'].' '.$row['excel_row'], 'firm' => $row['firm'], 'title' => $row['proposal_title'], ...$result, 'notes' => $this->dryNotes];
        }

        foreach (self::REMOVED_DECISIONS as $where => $status) {
            $result = $this->removedRow($where, OfferStatus::from($status));
            $out[] = ['row' => 'listeden cikan '.$where, 'firm' => '', 'title' => $result['proposal']?->title ?? '-', 'action' => $result['action'], 'steps' => $result['steps'], 'notes' => []];
        }

        $this->dry = false;

        return $out;
    }

    /**
     * Listeden cikan satir: kullanicinin karariyla durum ileri gider ve ozete
     * "listede yer almiyor" satiri eklenir.
     *
     * @return array{proposal: Proposal|null, action: string, steps: list<string>}
     */
    private function removedRow(string $where, OfferStatus $target): array
    {
        static $previous = null;
        $previous ??= require __DIR__.'/data/'.self::PREVIOUS_FILE;
        [$sheet, $line] = explode('|', $where);
        $item = collect($previous)->first(fn (array $row): bool => $row['sheet'] === $sheet && (int) $row['excel_row'] === (int) $line);
        $proposal = $item !== null ? $this->existingProposal(['proposal_title' => $this->proposalTitle($item), 'case_id' => null], $item) : null;

        if ($proposal === null) {
            return ['proposal' => null, 'action' => 'bulunamadi', 'steps' => []];
        }

        $steps = [];

        if (($status = $this->advance($proposal, ['offer_status' => $target->value, 'offer_date' => $item['offer_date'] ?? ''])) !== null) {
            $steps[] = $status;
        }

        $text = static::LIST_DATE.' listesinde yer almıyor; kullanıcı kararıyla '.$target->getLabel().' sayıldı.';
        $steps[] = 'ozete: '.$text;

        if (! $this->dry) {
            $version = $proposal->refresh()->currentVersion;

            if ($version instanceof ProposalVersion && ! str_contains((string) $version->summary, $text)) {
                $version->forceFill(['summary' => trim((string) $version->summary)."\n".$text])->save();
                $this->totals['summaries']++;
            }
        }

        return ['proposal' => $proposal, 'action' => 'guncelle', 'steps' => $steps];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $parties
     * @param  array<string, mixed>  $cases
     * @return array{proposal: Proposal|null, action: string, steps: list<string>}
     */
    private function process(array $row, int $ownerId, array &$parties, array &$cases): array
    {
        $previous = $this->previousRow($row);
        $proposal = $this->existingProposal($row, $previous);

        if ($proposal === null) {
            return $this->createRow($row, $ownerId, $parties, $cases);
        }

        $steps = [];

        if (($status = $this->advance($proposal, $row)) !== null) {
            $steps[] = $status;
        }

        if (($summary = $this->appendSummary($proposal, $row, $previous)) !== null) {
            $steps[] = $summary;
        }

        $notes = $this->addNotes($proposal, $row, $ownerId);

        if ($notes > 0) {
            $steps[] = $notes.' yeni gorusme notu';
        }

        if ($steps === []) {
            $this->totals['unchanged']++;
        }

        return ['proposal' => $proposal, 'action' => $steps === [] ? 'degisiklik yok' : 'guncelle', 'steps' => $steps];
    }

    /**
     * Onceki listedeki ayni satir: firma + ad + kapsam; bulunamazsa onceki adi bos,
     * ayni firma ve kapsamdaki tek satir.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function previousRow(array $row): ?array
    {
        static $previous = null;
        $previous ??= require __DIR__.'/data/'.self::PREVIOUS_FILE;
        $key = fn (array $item): string => $this->normalize((string) $item['firm']).'|'.$this->normalize((string) $item['name']).'|'.$item['scope'];

        foreach ($previous as $item) {
            if ($key($item) === $key($row)) {
                return $item;
            }
        }

        $unnamed = array_values(array_filter($previous, fn (array $item): bool => trim((string) $item['name']) === ''
            && $this->normalize((string) $item['firm']) === $this->normalize((string) $row['firm'])
            && $item['scope'] === $row['scope']));

        return count($unnamed) === 1 && trim((string) $row['name']) !== '' ? $unnamed[0] : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $previous
     */
    private function existingProposal(array $row, ?array $previous): ?Proposal
    {
        if ($previous !== null) {
            $tag = '('.self::PREVIOUS_DATE.', '.$previous['sheet'].', satır '.$previous['excel_row'].').';

            /** @var Proposal|null $tagged */
            $tagged = Proposal::query()
                ->whereHas('currentVersion', fn ($version) => $version->where('summary', 'like', '%'.$tag.'%'))
                ->first();

            if ($tagged !== null) {
                return $tagged;
            }
        }

        // Iz bulunamazsa (ozet elle degistirilmis olabilir) potansiyel is + baslik.
        $titles = array_unique(array_filter([$row['proposal_title'], $previous !== null ? $this->proposalTitle($previous) : null]));

        if ($row['case_id'] === null) {
            return null;
        }

        /** @var Proposal|null $proposal */
        $proposal = Proposal::query()->where('business_case_id', $row['case_id'])->whereIn('title', $titles)->first();

        return $proposal;
    }

    /**
     * Yeni satir: KksProposalSeeder kurallariyla taraf / potansiyel is / teklif.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $parties
     * @param  array<string, mixed>  $cases
     * @return array{proposal: Proposal|null, action: string, steps: list<string>}
     */
    private function createRow(array $row, int $ownerId, array &$parties, array &$cases): array
    {
        $steps = [
            $row['party_name'] === null ? 'yeni taraf: '.$row['firm'] : 'taraf: '.$row['party_name'],
            $row['case_id'] === null ? 'yeni potansiyel is: '.$row['case_title'] : 'potansiyel is: '.$row['case_title'],
            'yeni teklif ('.OfferStatus::from((string) $row['offer_status'])->getLabel().')',
            count($this->segments($row)).' gorusme notu adayi',
        ];

        if ($this->dry) {
            foreach ($this->segments($row) as $entry) {
                $this->dryNotes[] = ($entry['date'] ?? 'tarihsiz').' · '.Str::limit($entry['text'], 140);
            }

            return ['proposal' => null, 'action' => 'yeni', 'steps' => $steps];
        }

        $party = $parties[$row['party_key']] ??= $this->partyFor($row, $this->totals);
        $caseKey = $party->getKey().'|'.$this->normalize((string) $row['case_title']);
        $createdCases = [];
        $this->planCache ??= $this->plan();
        $case = $cases[$caseKey] ??= $this->caseFor($party, $row, $this->planCache['case_rows'][$row['party_key'].'|'.$this->normalize((string) $row['case_title'])] ?? [$row], $ownerId, $this->totals, $createdCases);
        $proposal = $this->openProposal($case, $row);
        $this->totals['created']++;
        $this->addNotes($proposal, $row, $ownerId);

        if ($proposal->offer_status === OfferStatus::Lost) {
            $this->touchedCases[(int) $case->getKey()] = true;
        }

        return ['proposal' => $proposal, 'action' => 'yeni', 'steps' => $steps];
    }

    /**
     * Durum yalniz ileri: Verilecek -> Verilen -> Kacan firsat. Onaylandi ve
     * Kacan firsat korunur.
     *
     * @param  array<string, mixed>  $row
     */
    private function advance(Proposal $proposal, array $row): ?string
    {
        $rank = static fn (?OfferStatus $status): int => match ($status) {
            null, OfferStatus::ToBeSubmitted => 0,
            OfferStatus::Submitted => 1,
            OfferStatus::Lost, OfferStatus::Approved => 2,
        };
        $current = $proposal->offer_status;
        $target = OfferStatus::from((string) $row['offer_status']);

        if ($rank($target) <= $rank($current)) {
            return null;
        }

        $step = ($current?->getLabel() ?? '-').' -> '.$target->getLabel();

        if ($this->dry) {
            return $step;
        }

        SeedGuard::allowingUpdates(function () use ($proposal, $row, $target): void {
            $this->submitVersion($proposal, $row);

            if ($target === OfferStatus::Lost) {
                app(ProposalService::class)->syncOfferStatus([$proposal->refresh()], OfferStatus::Lost);
                $this->touchedCases[(int) $proposal->business_case_id] = true;
            }
        });

        $this->totals['advanced']++;

        return $step;
    }

    /**
     * Surum Gonderildi'ye yurur (gonderim tarihi listedeki teklif tarihi);
     * Gonderildi teklif durumunu Verilen yapar.
     *
     * @param  array<string, mixed>  $row
     */
    private function submitVersion(Proposal $proposal, array $row): void
    {
        $versions = app(ProposalVersionService::class);
        $submittedAt = Carbon::createFromFormat('d.m.Y H:i', ((string) ($row['offer_date'] ?? '') ?: self::LIST_DATE).' 12:00', DisplayTime::zone());

        foreach ([ProposalVersionStatus::Review, ProposalVersionStatus::Approved, ProposalVersionStatus::Submitted] as $target) {
            $version = $proposal->refresh()->currentVersion;

            if ($version === null || ! $version->status->canTransitionTo($target)) {
                continue;
            }

            $versions->changeStatus(
                (int) $version->getKey(),
                $target,
                $target === ProposalVersionStatus::Submitted ? SubmissionChannel::Email : null,
                null,
                $target === ProposalVersionStatus::Submitted && $submittedAt instanceof Carbon ? $submittedAt : null,
            );
        }

        // Surum zaten gonderildiyse (ya da yurumediyse) teklif durumu yine Verilen olur.
        app(ProposalService::class)->syncOfferStatus([$proposal->refresh()], OfferStatus::Submitted, [OfferStatus::ToBeSubmitted]);
    }

    /**
     * Degisen bilgiler surum ozetine eklenir (eski satirlar kalir, yeni surum acilmaz).
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $previous
     */
    private function appendSummary(Proposal $proposal, array $row, ?array $previous): ?string
    {
        if ($previous === null) {
            return null;
        }

        $lines = [];
        $date = static fn (array $item): string => trim((string) ($item['offer_date'] ?? '') !== '' ? (string) $item['offer_date'] : (string) $item['offer_date_text']);

        foreach ([
            'Proje adı' => [trim((string) $previous['name']), trim((string) $row['name'])],
            'Teklif tarihi' => [$date($previous), $date($row)],
            'Proje durumu' => [trim((string) $previous['status']), trim((string) $row['status'])],
            'Proje gücü' => [trim((string) $previous['power_mwp']), trim((string) $row['power_mwp'])],
            'Depolama gücü' => [trim((string) $previous['storage_mwh']), trim((string) $row['storage_mwh'])],
        ] as $label => [$before, $after]) {
            if ($after !== '' && $after !== $before) {
                $lines[] = $label.': '.$after.($before !== '' ? ' (önceki: '.Str::limit($before, 120).')' : '');
            }
        }

        if ($lines === []) {
            return null;
        }

        $text = static::LIST_DATE.' listesi ('.$row['sheet'].', satır '.$row['excel_row'].'): '.implode('; ', $lines).'.';

        if ($this->dry) {
            return 'ozete: '.$text;
        }

        $version = $proposal->refresh()->currentVersion;

        if ($version instanceof ProposalVersion && ! str_contains((string) $version->summary, static::LIST_DATE.' listesi')) {
            SeedGuard::allowingUpdates(function () use ($version, $text): void {
                $version->forceFill(['summary' => trim((string) $version->summary)."\n".$text])->save();
            });
            $this->totals['summaries']++;
        }

        return 'ozete: '.$text;
    }

    /**
     * Not parcalari: "(gg.aa.yyyy" ve kisa "gg/aa" yeni gorusme baslatir.
     *
     * @param  array<string, mixed>  $row
     * @return list<array{date: string|null, text: string}>
     */
    private function segments(array $row): array
    {
        $year = substr(static::LIST_DATE, -4);
        // "(gg.aa.yyyy", metin icindeki ciplak "gg.aa.yyyy" (ardindan "yapilan" /
        // "tarihinde" gelmiyorsa) ve kisa "gg/aa" yeni gorusme baslatir.
        $pattern = '/\((\d{1,2})\.(\d{1,2})\.(\d{4})(?=[\s)])|(?<=^|[\s(.;,])(\d{1,2})\/(\d{1,2})(?!\d)|(?<=^|[\s.;,])(\d{1,2})\.(\d{1,2})\.(\d{4})(?!\d)(?!\.?\s*(?:yapılan|tarihinde))/u';
        $entries = [];
        $seen = [];

        foreach ([(string) $row['current_notes'], (string) $row['notes']] as $text) {
            foreach (preg_split('/\R/u', $text) ?: [] as $line) {
                $line = trim($line);

                if ($line === '' || preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
                    continue;
                }

                $starts = [];

                foreach ($matches as $match) {
                    [$day, $month, $y] = match (true) {
                        isset($match[6]) && $match[6][1] >= 0 && $match[6][0] !== '' => [(int) $match[6][0], (int) $match[7][0], (int) $match[8][0]],
                        isset($match[4]) && $match[4][1] >= 0 && $match[4][0] !== '' => [(int) $match[4][0], (int) $match[5][0], (int) $year],
                        default => [(int) $match[1][0], (int) $match[2][0], (int) $match[3][0]],
                    };

                    if (checkdate($month, $day, $y)) {
                        $starts[] = ['offset' => $match[0][1], 'length' => strlen($match[0][0]), 'date' => sprintf('%02d.%02d.%04d', $day, $month, $y), 'paren' => str_starts_with($match[0][0], '(')];
                    }
                }

                $head = $starts === [] ? $line : substr($line, 0, $starts[0]['offset']);
                $parts = trim($head) !== '' ? [$this->bareEntry(trim($head))] : [];

                foreach ($starts as $index => $start) {
                    $end = $starts[$index + 1]['offset'] ?? strlen($line);
                    $body = substr($line, $start['offset'] + $start['length'], $end - $start['offset'] - $start['length']);
                    $parts[] = ['date' => $start['date'], 'text' => $start['paren'] ? $this->closeDateParen($body) : trim(ltrim(trim($body), '-–: '))];
                }

                foreach ($parts as $part) {
                    $part['text'] = $this->balance(trim((string) $part['text'], " \t;,"));
                    $fingerprint = $this->fingerprint($part['text']);

                    if (mb_strlen($fingerprint) < 4 || isset($seen[$fingerprint])) {
                        continue;
                    }

                    $seen[$fingerprint] = true;
                    $entries[] = $part;
                }
            }
        }

        return $entries;
    }

    /** Parcanin basinda / sonunda eslesmeyen parantez atilir; "(3 firma var)" korunur. */
    private function balance(string $text): string
    {
        $text = trim($text);

        while ($text !== '' && str_starts_with($text, '(') && substr_count($text, '(') > substr_count($text, ')')) {
            $text = trim(substr($text, 1));
        }

        while ($text !== '' && str_ends_with($text, ')') && substr_count($text, ')') > substr_count($text, '(')) {
            $text = trim(substr($text, 0, -1));
        }

        // Kapanmamis ic parantez (or. "(basari") kapatilir.
        if (substr_count($text, '(') > substr_count($text, ')')) {
            $text .= str_repeat(')', substr_count($text, '(') - substr_count($text, ')'));
        }

        return $text;
    }

    /**
     * Potansiyel iste ayni metni iceren not yoksa yeni gorusme notu.
     *
     * @param  array<string, mixed>  $row
     */
    private function addNotes(Proposal $proposal, array $row, int $ownerId): int
    {
        $existing = PartyMeetingNote::query()
            ->where('business_case_id', $proposal->business_case_id)
            ->pluck('note')
            ->map(fn (?string $note): string => $this->fingerprint((string) $note))
            ->filter(fn (string $note): bool => $note !== '')
            ->all();
        $personnel = $this->personnelNames();
        $added = 0;

        foreach ($this->segments($row) as $entry) {
            [$personnelId, $text] = $this->splitPersonnel($entry['text'], $personnel);
            $fingerprint = $this->fingerprint($text);

            if ($this->known($fingerprint, $existing)) {
                continue;
            }

            $existing[] = $fingerprint;
            $added++;

            if ($this->dry) {
                $this->dryNotes[] = ($entry['date'] ?? 'tarihsiz').' · '.Str::limit($text, 140);

                continue;
            }

            $case = $proposal->businessCase;
            app(PartyMeetingNoteService::class)->create([
                'party_id' => $case->primary_party_id,
                'business_case_id' => $case->getKey(),
                'proposal_ids' => [(int) $proposal->getKey()],
                'personnel_id' => $personnelId ?? $ownerId,
                'noted_on' => Carbon::createFromFormat('d.m.Y', $entry['date'] ?? static::LIST_DATE)->toDateString(),
                'channel' => $this->channel($text)->value,
                'subject' => Str::limit((string) $case->title, 200, ''),
                'note' => $entry['date'] === null ? $text.' (Listede tarih yok; liste tarihi '.static::LIST_DATE.' yazıldı.)' : $text,
            ]);
            $this->totals['notes']++;
        }

        return $added;
    }

    /**
     * Metin zaten kayitli mi: ayni ya da birbirini iceren (en az 12 harf) not.
     *
     * @param  list<string>  $existing
     */
    private function known(string $fingerprint, array $existing): bool
    {
        foreach ($existing as $note) {
            if ($note === $fingerprint) {
                return true;
            }

            if (mb_strlen($fingerprint) >= 12 && str_contains($note, $fingerprint)) {
                return true;
            }

            // Kayitli not yeni parcanin icindeyse ve fazlasi kucukse ayni not sayilir.
            if (mb_strlen($note) >= 12 && str_contains($fingerprint, $note) && mb_strlen($fingerprint) - mb_strlen($note) < 20) {
                return true;
            }
        }

        return false;
    }

    private function ownerId(): ?int
    {
        $id = Personnel::query()->where('email', FirmaTakipBusinessCaseSeeder::OWNER_EMAIL)->value('id');

        return $id === null ? null : (int) $id;
    }
}
