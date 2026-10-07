<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Enums\Party\PartyKind;
use App\Enums\Party\PartyRoleCode;
use App\Enums\Party\PartyStatus;
use App\Exceptions\Party\PartyMergeNotAllowedException;
use App\Exceptions\RecordNotFoundException;
use App\Models\Party\Party;
use App\Query\Party\PartyMergeQueries;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use BackedEnum;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Taraf birlestirme (D-170, 7 Ekim 2026 kullanici talimati: ayni firmanin
 * iki kaydi tek kayitta toplanir).
 *
 * merge(kaynak, hedef): kaynaga bakan HER kayit (PartyMergeQueries::REFERENCES,
 * parties.id'ye giden butun yabanci anahtarlar) hedefe tasinir; kaynak
 * "Birlestirildi" (status merged) olur, merged_into_party_id hedefi gosterir
 * ve arsive alinir (zaten arsivliyse arsiv bilgisi korunur). Hicbir satir
 * silinmez. Tek transaction; her iki tarafa "party.merged" hareketi yazilir.
 *
 * Tekillik kurallari (cift kayit olusmaz, hicbir sey silinmez):
 * - Taraf tipi (party_roles): hedefte ayni tip (Musteri / Yatirimci / Isveren
 *   tek tiptir, D-167) acikken kaynagin acik satiri tasinir ve kapatilir
 *   (status ended, valid_until simdi); gecmis satirlar oldugu gibi tasinir.
 * - Adres: hedefte ayni turde birincil adres varsa kaynagin adresi birincil
 *   olmadan tasinir.
 * - Iletisim kanali (ayni tur + deger), lisans / sertifika (tur + no), yillik
 *   degerlendirme (yil), faaliyet satiri (proje tipi + alan + alt alan),
 *   sozlesme tarafi (surum + rol), faaliyet katilimcisi (faaliyet): hedefte
 *   ayni satir varsa kaynaktaki satir kaynakta kalir (atlanir).
 * - Kisi iliskisi: tasima kurulusu kendisine baglayacaksa satir kaynakta kalir.
 * - Kurulus profili: hedefin profili korunur; hedefte bos olan alanlar (kisa
 *   ad, sicil no, vergi dairesi / no, kurulus yili, web sitesi, sektor,
 *   personel bandi, grup ana sirketi) kaynaktan doldurulur. Uzun ad (unvan)
 *   hedefinkidir. Kaynagin vergi no'su hedefe gecerse kaynakta bosaltilir
 *   (vergi no tekildir). Kisi profili hedefinkidir.
 * - Taraf alanlari: network, ziyaret onceligi, koken, ulke hedefte bossa
 *   kaynaktan gelir; kaynak rakip firmaysa hedef de rakip isaretlenir.
 * - Grup ana sirketi kaynak olan kurulus hedefe baglanir; hedefin kendisi ise
 *   bag kaldirilir (kayit kendisini gosteremez).
 * - Personel Hareketleri kaynakta kalir (gecmis).
 *
 * Seeder icinden de cagrilabilir: tasimalar sorgu guncellemesidir, taraf ve
 * profil yazimi Eloquent'tir; SeedGuard altinda yalniz
 * SeedGuard::allowingUpdates() icinde calisir (silme yok).
 */
final class PartyMergeService extends AbstractService
{
    protected string $model = Party::class;

    protected string $subjectType = 'party';

    /** Hedefte bossa kaynaktan doldurulan kurulus profili alanlari. */
    private const PROFILE_FILL = [
        'trade_name', 'registration_no', 'tax_office', 'tax_number', 'founded_year', 'website_url',
        'sector_code', 'personnel_band', 'group_parent_party_id',
    ];

    /**
     * Tekillik denetimi icin okunan kolonlar.
     *
     * @var array<string, list<string>>
     */
    private const SELECT = [
        'party_roles' => ['role_code', 'valid_from', 'valid_until'],
        'addresses' => ['address_type', 'is_primary'],
        'communication_points' => ['channel_type', 'normalized_value'],
        'contact_relationships' => ['organization_party_id', 'contact_party_id'],
        'party_licenses' => ['license_type', 'license_no'],
        'party_certificates' => ['certificate_type', 'certificate_no'],
        'party_annual_reviews' => ['review_year'],
        'party_activity_areas' => ['project_type', 'activity_area_id', 'sub_activity_area_id'],
        'contract_parties' => ['contract_version_id', 'contract_role'],
        'business_development_activity_participants' => ['activity_id'],
    ];

    /** Hedefte ayni anahtar varsa kaynakta kalan satirlar: tablo => anahtar kolonlari. */
    private const UNIQUE_KEYS = [
        'communication_points' => ['channel_type', 'normalized_value'],
        'party_licenses' => ['license_type', 'license_no'],
        'party_certificates' => ['certificate_type', 'certificate_no'],
        'party_annual_reviews' => ['review_year'],
        'party_activity_areas' => ['project_type', 'activity_area_id', 'sub_activity_area_id'],
        'contract_parties' => ['contract_version_id', 'contract_role'],
        'business_development_activity_participants' => ['activity_id'],
    ];

    public const ACTION_MOVE = 'move';

    /** Tasindi ve kapatildi (taraf tipi). */
    public const ACTION_END = 'end';

    /** Tasindi, birincil adres degil. */
    public const ACTION_UNPRIMARY = 'unprimary';

    /** Hedefte ayni satir var; kaynakta kaldi. */
    public const ACTION_SKIP = 'skip';

    /** Bag kaldirildi (hedef kendisini gosteremez). */
    public const ACTION_CLEAR = 'clear';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly PartyMergeQueries $queries,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * Kaynagi hedefe birlestirir ve hedefi doner.
     *
     * @throws PartyMergeNotAllowedException
     */
    public function merge(Party|int $source, Party|int $target, ?string $reason = null): Party
    {
        return $this->transactions->run(function () use ($source, $target, $reason): Party {
            [$from, $to] = $this->lockPair($source, $target);
            $this->assertMergeable($from, $to);

            $reason = filled($reason) ? Str::of((string) $reason)->squish()->value() : null;
            $plan = $this->plan($from, $to);

            // Once kaynak isaretlenir: seed korumasi (SeedGuard) izin vermiyorsa
            // hicbir sey yazilmadan durur.
            $this->markMerged($from, $to, $reason);
            $this->execute($plan, (int) $from->getKey(), (int) $to->getKey());
            $filled = $this->fillTargetBlanks($from, $to);
            $summary = $this->summarize($plan);

            $this->recordActivity($from, 'merged', array_filter([
                'hedef_taraf_id' => (int) $to->getKey(),
                'hedef_taraf' => (string) $to->display_name,
                'gerekce' => $reason,
                'tasinan' => $summary,
            ], static fn (mixed $value): bool => $value !== null && $value !== []));

            $this->recordActivity($to, 'merged', array_filter([
                'kaynak_taraf_id' => (int) $from->getKey(),
                'kaynak_taraf' => (string) $from->display_name,
                'gerekce' => $reason,
                'tasinan' => $summary,
                'doldurulan' => $filled,
            ], static fn (mixed $value): bool => $value !== null && $value !== []));

            /** @var Party $fresh */
            $fresh = Party::query()->with(['organizationProfile', 'personProfile', 'roles'])->findOrFail($to->getKey());

            return $fresh;
        });
    }

    /**
     * Birlestirmenin yazmadan ozeti: "tablo.kolon" => hedefe tasinacak satir
     * sayisi. Hedefte ayni satir oldugu icin kaynakta kalacaklar
     * "tablo.kolon (kaynakta kalir)" anahtariyla sayilir. Yalniz sifirdan buyuk
     * sayilar doner.
     *
     * @return array<string, int>
     *
     * @throws PartyMergeNotAllowedException
     */
    public function preview(Party|int $source, Party|int $target): array
    {
        $counts = [];

        foreach ($this->details($source, $target)['tables'] as $name => $actions) {
            $kept = $actions[self::ACTION_SKIP] ?? 0;
            $moved = array_sum($actions) - $kept;

            if ($moved > 0) {
                $counts[$name] = $moved;
            }

            if ($kept > 0) {
                $counts[$name.' (kaynakta kalir)'] = $kept;
            }
        }

        return $counts;
    }

    /**
     * Ayrintili onizleme: tablo.kolon basina islem (move / end / unprimary /
     * skip / clear) sayilari ve hedefte doldurulacak bos alanlar.
     *
     * @return array{
     *     source: array{id: int, name: string},
     *     target: array{id: int, name: string},
     *     tables: array<string, array<string, int>>,
     *     moved: int,
     *     kept_on_source: int,
     *     fills: list<string>
     * }
     *
     * @throws PartyMergeNotAllowedException
     */
    public function details(Party|int $source, Party|int $target): array
    {
        $from = $this->find($source);
        $to = $this->find($target);
        $this->assertMergeable($from, $to);

        $plan = $this->plan($from, $to);
        $skipped = count(array_filter($plan, static fn (array $op): bool => $op['action'] === self::ACTION_SKIP));
        $fills = $this->blankFills($from, $to);

        return [
            'source' => ['id' => (int) $from->getKey(), 'name' => (string) $from->display_name],
            'target' => ['id' => (int) $to->getKey(), 'name' => (string) $to->display_name],
            'tables' => $this->summarize($plan),
            'moved' => count($plan) - $skipped,
            'kept_on_source' => $skipped,
            'fills' => array_keys($fills['party'] + $fills['profile']),
        ];
    }

    /**
     * @return array{0: Party, 1: Party}
     */
    private function lockPair(Party|int $source, Party|int $target): array
    {
        $sourceId = $source instanceof Party ? (int) $source->getKey() : $source;
        $targetId = $target instanceof Party ? (int) $target->getKey() : $target;

        $rows = Party::query()
            ->with(['organizationProfile', 'personProfile'])
            ->whereKey(array_unique([$sourceId, $targetId]))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(static fn (Party $party): int => (int) $party->getKey());

        $from = $rows->get($sourceId);
        $to = $rows->get($targetId);

        if (! $from instanceof Party || ! $to instanceof Party) {
            throw RecordNotFoundException::make();
        }

        return [$from, $to];
    }

    private function find(Party|int $party): Party
    {
        /** @var Party|null $found */
        $found = Party::query()
            ->with(['organizationProfile', 'personProfile'])
            ->find($party instanceof Party ? $party->getKey() : $party);

        if ($found === null) {
            throw RecordNotFoundException::make();
        }

        return $found;
    }

    /** Kaynak ve hedef farkli, ayni turde, birlestirilmemis; hedef arsivde degil. */
    private function assertMergeable(Party $from, Party $to): void
    {
        if ((int) $from->getKey() === (int) $to->getKey()
            || $from->party_kind !== $to->party_kind
            || $from->status === PartyStatus::Merged
            || $to->status === PartyStatus::Merged
            || $to->archived_at !== null) {
            throw PartyMergeNotAllowedException::make([
                'source' => (string) $from->display_name,
                'target' => (string) $to->display_name,
            ]);
        }
    }

    /**
     * Kaynaga bakan her satir icin karar.
     *
     * @return list<array{table: string, column: string, key: string, id: int, action: string, set: array<string, mixed>}>
     */
    private function plan(Party $from, Party $to): array
    {
        $source = (int) $from->getKey();
        $target = (int) $to->getKey();
        $plan = [];

        foreach ($this->queries->references() as $table => $columns) {
            foreach ($columns as $column) {
                $rows = $this->queries->rows($table, $column, $source, self::SELECT[$table] ?? []);

                if ($rows->isEmpty()) {
                    continue;
                }

                $key = $this->queries->keyOf($table);

                foreach ($this->decide($table, $column, $rows, $target) as $id => [$action, $set]) {
                    $plan[] = ['table' => $table, 'column' => $column, 'key' => $key, 'id' => $id, 'action' => $action, 'set' => $set];
                }
            }
        }

        return $plan;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array{0: string, 1: array<string, mixed>}>
     */
    private function decide(string $table, string $column, Collection $rows, int $target): array
    {
        $key = $this->queries->keyOf($table);
        $decisions = [];

        if ($table === 'party_roles') {
            $open = $this->queries->rows($table, $column, $target, ['role_code', 'valid_until'])
                ->filter(static fn (object $row): bool => $row->valid_until === null)
                ->map(fn (object $row): string => $this->roleKey((string) $row->role_code))
                ->flip()
                ->all();

            foreach ($rows as $row) {
                $code = $this->roleKey((string) $row->role_code);

                if ($row->valid_until === null && isset($open[$code])) {
                    $decisions[(int) $row->{$key}] = [self::ACTION_END, ['status' => 'ended', 'valid_until' => $this->endAt($row->valid_from)]];

                    continue;
                }

                if ($row->valid_until === null) {
                    $open[$code] = true;
                }

                $decisions[(int) $row->{$key}] = [self::ACTION_MOVE, []];
            }

            return $decisions;
        }

        if ($table === 'addresses') {
            $primary = $this->queries->rows($table, $column, $target, ['address_type', 'is_primary'])
                ->filter(static fn (object $row): bool => (bool) $row->is_primary)
                ->map(static fn (object $row): string => (string) $row->address_type)
                ->flip()
                ->all();

            foreach ($rows as $row) {
                if ((bool) $row->is_primary && isset($primary[(string) $row->address_type])) {
                    $decisions[(int) $row->{$key}] = [self::ACTION_UNPRIMARY, ['is_primary' => false]];

                    continue;
                }

                if ((bool) $row->is_primary) {
                    $primary[(string) $row->address_type] = true;
                }

                $decisions[(int) $row->{$key}] = [self::ACTION_MOVE, []];
            }

            return $decisions;
        }

        if (isset(self::UNIQUE_KEYS[$table])) {
            $fields = self::UNIQUE_KEYS[$table];
            $taken = $this->queries->rows($table, $column, $target, $fields)
                ->map(fn (object $row): string => $this->rowKey($row, $fields))
                ->flip()
                ->all();

            foreach ($rows as $row) {
                $rowKey = $this->rowKey($row, $fields);

                if (isset($taken[$rowKey])) {
                    $decisions[(int) $row->{$key}] = [self::ACTION_SKIP, []];

                    continue;
                }

                $taken[$rowKey] = true;
                $decisions[(int) $row->{$key}] = [self::ACTION_MOVE, []];
            }

            return $decisions;
        }

        foreach ($rows as $row) {
            $id = (int) $row->{$key};

            $decisions[$id] = match (true) {
                // Kurulus kisi iliskisinde iki uc ayni taraf olamaz.
                $table === 'contact_relationships' && $column === 'organization_party_id' && (int) ($row->contact_party_id ?? 0) === $target,
                $table === 'contact_relationships' && $column === 'contact_party_id' && (int) ($row->organization_party_id ?? 0) === $target,
                $table === 'parties' && $id === $target => [self::ACTION_SKIP, []],
                // Hedefin grup ana sirketi kaynaksa bag kaldirilir.
                $table === 'organization_profiles' && $id === $target => [self::ACTION_CLEAR, []],
                default => [self::ACTION_MOVE, []],
            };
        }

        return $decisions;
    }

    /**
     * @param  list<array{table: string, column: string, key: string, id: int, action: string, set: array<string, mixed>}>  $plan
     */
    private function execute(array $plan, int $source, int $target): void
    {
        foreach ($plan as $op) {
            if ($op['action'] === self::ACTION_SKIP) {
                continue;
            }

            $values = $op['action'] === self::ACTION_CLEAR
                ? [$op['column'] => null]
                : [$op['column'] => $target, ...$op['set']];

            DB::table($op['table'])
                ->where($op['key'], $op['id'])
                ->where($op['column'], $source)
                ->update([...$values, ...$this->auditValues($op['table'])]);
        }
    }

    /**
     * Guncelleme izi: guncelleyen personel ve surum (kolon varsa).
     *
     * @return array<string, mixed>
     */
    private function auditValues(string $table): array
    {
        $values = [];

        if ($this->queries->hasColumn($table, 'updated_by_personnel_id')) {
            $values['updated_by_personnel_id'] = $this->actor->personnelId();
        }

        if ($this->queries->hasColumn($table, 'row_version')) {
            $values['row_version'] = DB::raw('`row_version` + 1');
        }

        return $values;
    }

    private function markMerged(Party $from, Party $to, ?string $reason): void
    {
        $from->forceFill([
            'status' => PartyStatus::Merged,
            'merged_into_party_id' => (int) $to->getKey(),
        ]);

        // Birlestirilen taraf listelerden, secimlerden ve sayimlardan cikar;
        // zaten arsivliyse arsiv bilgisi korunur.
        if ($from->archived_at === null) {
            $from->forceFill([
                'archived_at' => Carbon::now('UTC'),
                'archived_by_personnel_id' => $this->actor->personnelId(),
                'archive_reason' => Str::of($reason ?? (string) __('party.merge.archive_reason', ['name' => (string) $to->display_name]))
                    ->squish()
                    ->limit(100, '')
                    ->value(),
            ]);
        }

        $this->saveWithoutVersion($from);
    }

    /**
     * Hedefte bos olan alanlari kaynaktan doldurur (PartyService uzerinden:
     * gorunen ad kurali ve cift kayit ozeti yeniden hesaplanir).
     *
     * @return list<string> doldurulan alanlar
     */
    private function fillTargetBlanks(Party $from, Party $to): array
    {
        $fills = $this->blankFills($from, $to);
        $data = $fills['party'];

        if ($fills['profile'] !== []) {
            // Vergi no tekildir: kaynakta bosaltilip hedefe yazilir.
            if (array_key_exists('tax_number', $fills['profile'])) {
                DB::table('organization_profiles')
                    ->where('party_id', (int) $from->getKey())
                    ->update(['tax_number' => null, ...$this->auditValues('organization_profiles')]);
            }

            $data['organization_profile'] = $fills['profile'];
        }

        if ($data === []) {
            return [];
        }

        app(PartyService::class)->update((int) $to->getKey(), $data);

        return array_keys($fills['party'] + $fills['profile']);
    }

    /**
     * @return array{party: array<string, mixed>, profile: array<string, mixed>}
     */
    private function blankFills(Party $from, Party $to): array
    {
        $party = [];
        $columns = ['country_code'];

        if (SchemaReadiness::hasBatch('B28')) {
            $columns = [...$columns, 'network_note', 'visit_priority'];
        }

        if (SchemaReadiness::hasBatch('B33')) {
            $columns[] = 'origin';

            if ((bool) $from->is_competitor && ! (bool) $to->is_competitor) {
                $party['is_competitor'] = true;
            }
        }

        foreach ($columns as $column) {
            $value = self::raw($from->getAttribute($column));

            if (blank(self::raw($to->getAttribute($column))) && filled($value)) {
                $party[$column] = $value;
            }
        }

        $profile = [];
        $source = $from->organizationProfile;
        $target = $to->organizationProfile;

        if ($from->party_kind === PartyKind::Organization && $source !== null && $target !== null) {
            foreach (self::PROFILE_FILL as $column) {
                $value = self::raw($source->getAttribute($column));

                if (! blank(self::raw($target->getAttribute($column))) || blank($value)) {
                    continue;
                }

                // Grup ana sirketi hedefin kendisi ya da kaynak olamaz.
                if ($column === 'group_parent_party_id' && in_array((int) $value, [(int) $from->getKey(), (int) $to->getKey()], true)) {
                    continue;
                }

                $profile[$column] = $value;
            }
        }

        return ['party' => $party, 'profile' => $profile];
    }

    /**
     * @param  list<array{table: string, column: string, key: string, id: int, action: string, set: array<string, mixed>}>  $plan
     * @return array<string, array<string, int>> "tablo.kolon" => islem => satir sayisi
     */
    private function summarize(array $plan): array
    {
        $summary = [];

        foreach ($plan as $op) {
            $name = $op['table'].'.'.$op['column'];
            $summary[$name][$op['action']] = ($summary[$name][$op['action']] ?? 0) + 1;
        }

        return $summary;
    }

    /** Taraf tipinin tekillik anahtari: Musteri / Yatirimci Isveren sayilir (D-167). */
    private function roleKey(string $code): string
    {
        return PartyRoleCode::tryFrom($code)?->canonical()->value ?? $code;
    }

    /**
     * @param  list<string>  $fields
     */
    private function rowKey(object $row, array $fields): string
    {
        return implode('|', array_map(static fn (string $field): string => mb_strtolower(trim((string) ($row->{$field} ?? ''))), $fields));
    }

    /** Kapatma ani: simdi, ama baslangictan en az bir saniye sonra (valid_until > valid_from). */
    private function endAt(mixed $validFrom): string
    {
        $now = Carbon::now('UTC');
        $minimum = filled($validFrom) ? Carbon::parse((string) $validFrom, 'UTC')->addSecond() : $now;

        return ($now->greaterThan($minimum) ? $now : $minimum)->format('Y-m-d H:i:s.u');
    }

    private static function raw(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
