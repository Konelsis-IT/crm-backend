<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Party\MeetingPlanSource;
use App\Models\Party\ContactRelationship;
use App\Models\Party\MeetingPlan;
use App\Models\Party\Party;
use App\Models\Party\PartyMeetingNote;
use App\Services\Party\ContactRelationshipService;
use App\Services\Party\MeetingPlanService;
use App\Services\Party\PartyMeetingNoteService;
use App\Services\Party\PartyRoleService;
use App\Services\Party\PartyService;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Ziyaret plani aktariminin yanlis esledigi firmalari ayirir (21 Eylul 2026
 * kullanici karari). Asagidaki firmalar ayri taraflardir:
 *
 *  - REIS ENERJI              / REIS RS ENERJI ELEKTRIK URETIM SAN.TIC.A.S.
 *  - FERNAS INSAAT            / FERNAS SIRKETLER GRUBU
 *  - LIMAK YENILENEBILIR ENERJI / LIMAK INSAAT
 *  - CENGIZ INSAAT            / CENGIZ HOLDING (haritadaki "CENGIZ" yeniden adlandirilir)
 *
 * Ziyaret plani satirlarinin (data/weekly_visit_plans.php) eski firmaya yazdigi
 * gorusme notlari ve aktarim planlari yeni firmaya tasinir; notlardan dogan
 * plan satirlari notla birlikte gecer. Kisiler: yalniz aktarimin eski firmaya
 * actigi kisiler tasinir; eski firmanin kendi kisisi (or. Limak Insaat'in Can
 * Degirmenci'si) yeni firmada ayrica acilir. Eski firmanin diger kayitlarina
 * dokunulmaz, hicbir kayit silinmez.
 *
 * Idempotent; MarketMapSeeder ve WeeklyVisitPlanSeeder basinda da calisir, ki
 * o seeder'lar yeniden calistiginda cift kayit olusmasin. Yeni kurulumda
 * tasinacak bir sey yoktur.
 */
class PartySplitSeeder extends Seeder
{
    /**
     * Yeni taraf => eski (yanlis eslenen) taraf ve aktarimin eski tarafa actigi,
     * yeni tarafa tasinacak kisiler.
     *
     * @var array<string, array{from: string, move_contacts: list<string>}>
     */
    private const SPLITS = [
        'REİS ENERJİ' => ['from' => 'REİS RS ENERJİ ELEKTRİK ÜRETİM SAN.TİC.A.Ş.', 'move_contacts' => ['ÖMER REİS', 'ÖMER ÇEBİ', 'ÖMER NACİ ÇEBİ']],
        'FERNAS İNŞAAT' => ['from' => 'FERNAS ŞİRKETLER GRUBU', 'move_contacts' => []],
        'LİMAK YENİLENEBİLİR ENERJİ' => ['from' => 'LİMAK İNŞAAT', 'move_contacts' => []],
        'CENGİZ İNŞAAT' => ['from' => 'CENGİZ', 'move_contacts' => []],
    ];

    /** @var array<string, string> eski ad => yeni ad */
    private const RENAMES = ['CENGİZ' => 'CENGİZ HOLDİNG'];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B16')) {
            return;
        }

        // Notlar (B28) ve planlar (B34) varsa tasinir; yeniden adlandirma her durumda.
        if (SchemaReadiness::hasBatch('B28') && SchemaReadiness::hasBatch('B34')) {
            /** @var list<array<string, mixed>> $rows */
            $rows = require database_path('seeders/data/weekly_visit_plans.php');

            foreach (self::SPLITS as $name => $split) {
                $old = $this->find([$split['from'], self::RENAMES[$split['from']] ?? null]);

                if ($old !== null) {
                    $this->split($old, $name, $split['move_contacts'], array_values(array_filter($rows, fn (array $row): bool => $row['party'] === $name)));
                }
            }
        }

        foreach (self::RENAMES as $from => $to) {
            $party = $this->find([$from]);

            if ($party !== null && $this->find([$to]) === null) {
                app(PartyService::class)->update($party, ['display_name' => $to, 'organization_profile' => ['legal_name' => $to]]);
                $this->command?->info("Taraf yeniden adlandirildi: {$from} -> {$to}");
            }
        }
    }

    /**
     * @param  list<string>  $moveContacts
     * @param  list<array<string, mixed>>  $rows
     */
    private function split(Party $old, string $name, array $moveContacts, array $rows): void
    {
        $texts = [];
        $dates = [];

        foreach ($rows as $row) {
            foreach ((array) $row['notes'] as $note) {
                $texts[] = $this->normalize((string) $note['text']);
            }

            if (is_array($row['plan'] ?? null)) {
                $dates[] = (string) $row['plan']['planned_on'];
            }
        }

        $notes = $old->meetingNotes()->get()
            ->filter(fn (PartyMeetingNote $note): bool => in_array($this->normalize((string) $note->note), $texts, true))
            ->values();
        $plans = MeetingPlan::query()
            ->where('party_id', $old->getKey())
            ->where('source', MeetingPlanSource::Import->value)
            ->whereNull('meeting_note_id')
            ->get()
            ->filter(fn (MeetingPlan $plan): bool => in_array($plan->planned_on?->toDateString(), $dates, true))
            ->values();

        if ($notes->isEmpty() && $plans->isEmpty()) {
            return;
        }

        $new = $this->find([$name]) ?? $this->createParty($name, (string) ($rows[0]['role'] ?? 'employer'));
        $contacts = SchemaReadiness::hasBatch('B27') ? $this->contacts($old, $new, $moveContacts, $rows) : [];

        foreach ($notes as $note) {
            app(PartyMeetingNoteService::class)->update($note, [
                'party_id' => $new->getKey(),
                'contact_relationship_id' => $contacts[(int) $note->contact_relationship_id] ?? null,
            ]);
        }

        foreach ($plans as $plan) {
            app(MeetingPlanService::class)->moveToParty($plan, (int) $new->getKey(), $contacts[(int) $plan->contact_relationship_id] ?? null);
        }

        $this->command?->info(sprintf('%s -> %s: %d gorusme notu, %d plan tasindi.', $old->display_name, $name, $notes->count(), $plans->count()));
    }

    /**
     * Ziyaret satirlarindaki kisileri yeni firmada hazirlar.
     *
     * @param  list<string>  $moveContacts
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, int> eski kisi kimligi => yeni firmadaki kisi kimligi
     */
    private function contacts(Party $old, Party $new, array $moveContacts, array $rows): array
    {
        $oldContacts = $old->contacts()->get()->keyBy(fn (ContactRelationship $contact): string => $this->normalize($contact->displayName()));
        $newContacts = $new->contacts()->get()->keyBy(fn (ContactRelationship $contact): string => $this->normalize($contact->displayName()));
        $move = array_map(fn (string $contact): string => $this->normalize($contact), $moveContacts);
        $map = [];

        foreach ($rows as $row) {
            foreach ((array) $row['contacts'] as $data) {
                $key = $this->normalize((string) $data['name']);
                /** @var ContactRelationship|null $current */
                $current = $oldContacts->get($key);

                if ($current !== null && in_array($key, $move, true)) {
                    app(ContactRelationshipService::class)->update($current, [
                        'organization_party_id' => $new->getKey(),
                        'is_primary' => $newContacts->isEmpty(),
                    ]);
                    $oldContacts->forget($key);
                    $newContacts->put($key, $current);
                    $map[(int) $current->getKey()] = (int) $current->getKey();

                    continue;
                }

                /** @var ContactRelationship|null $target */
                $target = $newContacts->get($key);

                if ($target === null) {
                    /** @var ContactRelationship $target */
                    $target = app(ContactRelationshipService::class)->create([
                        'organization_party_id' => $new->getKey(),
                        'contact_name' => trim((string) $data['name']),
                        'relationship_role' => 'other',
                        'department_note' => filled($data['department'] ?? null) ? (string) $data['department'] : null,
                        'is_primary' => $newContacts->isEmpty(),
                    ]);
                    $newContacts->put($key, $target);
                }

                if ($current !== null) {
                    $map[(int) $current->getKey()] = (int) $target->getKey();
                }
            }
        }

        return $map;
    }

    private function createParty(string $name, string $role): Party
    {
        /** @var Party $party */
        $party = app(PartyService::class)->create([
            'party_kind' => 'organization',
            'display_name' => $name,
            'country_code' => 'TR',
            'status' => 'prospect',
            'organization_profile' => ['legal_name' => $name],
        ]);
        app(PartyRoleService::class)->create(['party_id' => $party->getKey(), 'role_code' => $role, 'status' => 'active']);

        return $party;
    }

    /**
     * @param  list<string|null>  $names
     */
    private function find(array $names): ?Party
    {
        foreach (array_filter($names) as $name) {
            /** @var Party|null $party */
            $party = Party::query()
                ->where('party_kind', 'organization')
                ->where('normalized_name', $this->normalize($name))
                ->first();

            if ($party !== null) {
                return $party;
            }
        }

        return null;
    }

    /** Uygulamanin normalized_name kurali: kucuk harf, tek bosluk. */
    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->squish()->value();
    }
}
