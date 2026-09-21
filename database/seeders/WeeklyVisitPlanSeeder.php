<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Party\MeetingPlanSource;
use App\Enums\Party\MeetingPlanStatus;
use App\Exceptions\DuplicateRecordException;
use App\Models\Party\ContactRelationship;
use App\Models\Party\MeetingPlan;
use App\Models\Party\Party;
use App\Models\Party\PartyRole;
use App\Models\Personnel\Personnel;
use App\Services\Party\ContactRelationshipService;
use App\Services\Party\MeetingPlanService;
use App\Services\Party\PartyMeetingNoteService;
use App\Services\Party\PartyRoleService;
use App\Services\Party\PartyService;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Haftalik ziyaret plani aktarimi (Haftalik Ziyaret Plani.xlsx, 17.08-18.09.2026;
 * 21 Eylul 2026 kullanici talimati, D-109). Kaynak dizi
 * database/seeders/data/weekly_visit_plans.php dosyasindadir.
 *
 * Kullanici karari (21 Eylul 2026, B34):
 *  - Tarihli ziyaret notlari Ersin Ozdemir'in, tarihli telefon notlari KISI
 *    sutunundaki personelin (M.T. Mukaddes Tekik, Y.F. Yusuf Gokcan Fil,
 *    E.O. Ersin Ozdemir) gorusme notu olur; bas harf bossa Ersin Ozdemir.
 *    Sistemdeki personeli bos notlara da Ersin Ozdemir yazilir
 *    (MeetingNotePersonnelSeeder); personel sonradan kendisi gunceller.
 *  - Notu olmayan satir gorusme planidir (sorumlu Ersin Ozdemir); yetkili
 *    kisi sutununda "MG" yazan satirda Mustafa Gunes katilan personeldir.
 *  - Firma sistemde yoksa "Aday" durumunda acilir (resmi kurumlar Resmi kurum,
 *    digerleri Isveren tipinde).
 *  - REIS ENERJI, FERNAS INSAAT, LIMAK YENILENEBILIR ENERJI ve CENGIZ INSAAT
 *    ayri firmalardir; benzer adli firmalarla eslenmez (PartySplitSeeder).
 *
 * Gorusme notlari servis uzerinden yazildigi icin plana kendiliginden yansir
 * (MeetingPlanService::syncFromNote). Idempotent: ayni metinli not ve ayni
 * tarihli aktarim plani ikinci kez yazilmaz. Sonunda MeetingNotePersonnelSeeder
 * personeli bos notlara Ersin Ozdemir yazar, MeetingPlanBackfillSeeder var olan
 * notlari plana yansitir. Kalici uretim verisidir; uretimde de calisir.
 */
class WeeklyVisitPlanSeeder extends Seeder
{
    /** @var array<string, int> */
    private array $totals = ['parties_created' => 0, 'roles' => 0, 'contacts' => 0, 'notes' => 0, 'plans' => 0];

    /** @var array<string, int> e-posta => personel kimligi */
    private array $personnel = [];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B16') || ! SchemaReadiness::hasBatch('B28') || ! SchemaReadiness::hasBatch('B34')) {
            $this->command?->warn('Taraf (B16), gorusme notu (B28) ya da gorusme plani (B34) tablolari yok; WeeklyVisitPlanSeeder atlandi.');

            return;
        }

        // Yanlis eslenmis firmalar once ayrilir; ayri firmalarin notlari cift yazilmaz.
        $this->call(PartySplitSeeder::class);

        $this->personnel = Personnel::query()->pluck('id', 'email')
            ->mapWithKeys(fn ($id, $email): array => [Str::lower((string) $email) => (int) $id])
            ->all();

        /** @var list<array<string, mixed>> $rows */
        $rows = require database_path('seeders/data/weekly_visit_plans.php');

        foreach ($rows as $row) {
            $party = $this->seedParty($row);
            $this->seedRole($party, (string) $row['role']);
            $contacts = SchemaReadiness::hasBatch('B27') ? $this->seedContacts($party, (array) $row['contacts']) : [];
            $contactId = $contacts === [] ? null : reset($contacts);
            $participants = $this->ids((array) $row['participants']);

            foreach ((array) $row['notes'] as $note) {
                $this->seedNote($party, $note, $contactId, $participants);
            }

            if (is_array($row['plan'] ?? null)) {
                $this->seedPlan($party, $row['plan'], $contactId, $participants);
            }
        }

        $this->command?->info(sprintf(
            'Haftalik ziyaret plani: %d firma acildi, %d tip, %d kisi, %d gorusme notu, %d plan eklendi.',
            $this->totals['parties_created'],
            $this->totals['roles'],
            $this->totals['contacts'],
            $this->totals['notes'],
            $this->totals['plans'],
        ));

        $this->call(MeetingNotePersonnelSeeder::class);
        $this->call(MeetingPlanBackfillSeeder::class);
    }

    /**
     * Firmayi 'match' (sistemdeki tam ad), yoksa adiyla bulur; ikisi de yoksa
     * esleme adiyla acar (sonradan pazar haritasi calissa bile cift kayit olmaz).
     *
     * @param  array<string, mixed>  $row
     */
    private function seedParty(array $row): Party
    {
        $names = array_values(array_unique(array_filter([(string) ($row['match'] ?? ''), (string) $row['party']])));

        foreach ($names as $name) {
            /** @var Party|null $party */
            $party = Party::query()
                ->where('party_kind', 'organization')
                ->where('normalized_name', $this->normalize($name))
                ->first();

            if ($party !== null) {
                return $party;
            }
        }

        $name = $names[0];

        /** @var Party $party */
        $party = app(PartyService::class)->create([
            'party_kind' => 'organization',
            'display_name' => $name,
            'country_code' => 'TR',
            'status' => 'prospect',
            'organization_profile' => ['legal_name' => $name],
        ]);
        $this->totals['parties_created']++;

        return $party;
    }

    private function seedRole(Party $party, string $role): void
    {
        $open = $party->roles()->get()->first(
            fn (PartyRole $row): bool => $row->valid_until === null,
        );

        // Firmanin zaten acik bir tipi varsa dokunulmaz (haritadaki tip korunur).
        if ($open !== null) {
            return;
        }

        try {
            app(PartyRoleService::class)->create(['party_id' => $party->getKey(), 'role_code' => $role, 'status' => 'active']);
            $this->totals['roles']++;
        } catch (DuplicateRecordException) {
            // Tip baska bir yoldan acilmis.
        }
    }

    /**
     * @param  list<array{name: string, department: ?string}>  $contacts
     * @return array<string, int> ad => kisi kimligi
     */
    private function seedContacts(Party $party, array $contacts): array
    {
        $existing = $party->contacts()->get()->keyBy(
            fn (ContactRelationship $contact): string => $this->normalize($contact->displayName()),
        );
        $ids = [];

        foreach ($contacts as $row) {
            $name = trim((string) $row['name']);
            $key = $this->normalize($name);

            /** @var ContactRelationship|null $contact */
            $contact = $existing->get($key);

            if ($contact === null) {
                /** @var ContactRelationship $contact */
                $contact = app(ContactRelationshipService::class)->create([
                    'organization_party_id' => $party->getKey(),
                    'contact_name' => $name,
                    'relationship_role' => 'other',
                    'department_note' => filled($row['department'] ?? null) ? (string) $row['department'] : null,
                    'is_primary' => $existing->isEmpty(),
                ]);
                $existing->put($key, $contact);
                $this->totals['contacts']++;
            }

            $ids[$key] = (int) $contact->getKey();
        }

        return $ids;
    }

    /**
     * @param  array{channel: string, noted_on: string, personnel: ?string, text: string}  $note
     * @param  list<int>  $participants
     */
    private function seedNote(Party $party, array $note, ?int $contactId, array $participants): void
    {
        $text = trim((string) $note['text']);
        $key = $this->normalize($text);

        foreach ($party->meetingNotes()->get() as $existing) {
            if ($this->normalize((string) $existing->note) === $key) {
                return;
            }
        }

        $created = app(PartyMeetingNoteService::class)->create([
            'party_id' => $party->getKey(),
            'contact_relationship_id' => $contactId,
            'personnel_id' => $this->id($note['personnel'] ?? null),
            'noted_on' => $note['noted_on'],
            'channel' => $note['channel'],
            'note' => $text,
        ]);
        $this->totals['notes']++;

        // Ziyarete katilan personel (MG) nottan dogan plan satirina eklenir.
        if ($participants !== []) {
            $plan = MeetingPlan::query()->where('meeting_note_id', $created->getKey())->first();

            if ($plan instanceof MeetingPlan) {
                app(MeetingPlanService::class)->ensureParticipants($plan, $participants);
            }
        }
    }

    /**
     * @param  array{planned_on: string, status: string, channel: string, personnel: ?string, note: ?string}  $plan
     * @param  list<int>  $participants
     */
    private function seedPlan(Party $party, array $plan, ?int $contactId, array $participants): void
    {
        $exists = MeetingPlan::query()
            ->where('party_id', $party->getKey())
            ->whereDate('planned_on', $plan['planned_on'])
            ->where('source', MeetingPlanSource::Import->value)
            ->exists();

        if ($exists) {
            return;
        }

        app(MeetingPlanService::class)->create([
            'party_id' => $party->getKey(),
            'contact_relationship_id' => $contactId,
            'personnel_id' => $this->id($plan['personnel'] ?? null),
            'planned_on' => $plan['planned_on'],
            'channel' => $plan['channel'],
            'note' => $plan['note'] ?? null,
            'status' => $plan['status'],
            'source' => MeetingPlanSource::Import->value,
            'completed_at' => $plan['status'] === MeetingPlanStatus::Done->value ? Carbon::parse($plan['planned_on'], 'UTC') : null,
            'participant_ids' => $participants,
        ]);
        $this->totals['plans']++;
    }

    private function id(?string $email): ?int
    {
        return $email === null ? null : ($this->personnel[Str::lower($email)] ?? null);
    }

    /**
     * @param  list<string>  $emails
     * @return list<int>
     */
    private function ids(array $emails): array
    {
        return array_values(array_filter(array_map(fn (string $email): ?int => $this->id($email), $emails)));
    }

    /** Uygulamanin normalized_name kurali: kucuk harf, tek bosluk. */
    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->squish()->value();
    }
}
