<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Acquisition\TenderAccessMode;
use App\Enums\Shared\ActiveStatus;
use App\Exceptions\DuplicateRecordException;
use App\Models\Acquisition\TenderSource;
use App\Models\Party\ActivityArea;
use App\Models\Party\ContactRelationship;
use App\Models\Party\Party;
use App\Models\Party\PartyRole;
use App\Services\Acquisition\TenderSourceService;
use App\Services\Party\AddressService;
use App\Services\Party\CommunicationPointService;
use App\Services\Party\ContactRelationshipService;
use App\Services\Party\PartyActivityAreaService;
use App\Services\Party\PartyMeetingNoteService;
use App\Services\Party\PartyRoleService;
use App\Services\Party\PartyService;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Pazar haritasi aktarimi (Firma_Harita_Takip.xlsx, Genel_Harita; 21 Eylul
 * 2026 kullanici talimati, D-107). Kaynak dizi
 * database/seeders/data/market_map.php dosyasindadir.
 *
 * Kullanici karari (21 Eylul 2026, B33):
 *  - Haritadaki her firma organizasyon tarafi olur ("Aday"; Excel'de
 *    "kapandi" yazan firma "Pasif"). Ureticiler / ekipman firmalari
 *    Tedarikci, proje ve CED firmalari Danisman, kamu kurumlari Resmi kurum,
 *    dernekler Dernek / oda, buyuk firmalar Isveren tipinde acilir.
 *  - Haritadaki kategori faaliyet satiri olur: Proje tipi + Faaliyet alani +
 *    Alt faaliyet alani; alt basliklar (Avrupa / Cin / Yerli / Ithal /
 *    Yabanci) firmanin kokeni olur. Rakip isaretini ilgili personel verir;
 *    aktarim isaretlemez.
 *  - "Aranmayacak" gorusme notu olarak, "samimi" ve referans notlari
 *    ("... beyin selami", "... bey verdi") kisinin Network alanina yazilir.
 *  - Ihale takip sutunu Ihale kaynaklarina eklenir (kod ile; EKAP zaten
 *    ProjectCatalogSeeder ile gelir).
 *
 * Idempotent: firma normalized_name ile ('match' doluysa sistemdeki o adla)
 * bulunur; var olan firmada yalniz eksikler eklenir (tip, faaliyet satiri,
 * adres, kisi, kanal, not; bos duran koken). Hicbir kayit silinmez. Kalici
 * uretim verisidir; uretimde de calisir. Yazmalar servisler uzerinden gider.
 */
class MarketMapSeeder extends Seeder
{
    /** @var array<string, int> */
    private array $totals = [
        'parties_created' => 0,
        'parties_existing' => 0,
        'roles' => 0,
        'activities' => 0,
        'addresses' => 0,
        'contacts' => 0,
        'contact_channels' => 0,
        'notes' => 0,
        'tender_sources' => 0,
    ];

    /** @var array<string, int> */
    private array $areaIds = [];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B16') || ! SchemaReadiness::hasBatch('B33')) {
            $this->command?->warn('Taraf (B16) ya da faaliyet alani (B33) tablolari yok; MarketMapSeeder atlandi.');

            return;
        }

        // Ayri firmalar once ayrilir (CENGIZ -> CENGIZ HOLDING); yeniden calisinca cift kayit olmaz.
        $this->call(PartySplitSeeder::class);
        $this->call(ActivityAreaSeeder::class);
        $this->areaIds = ActivityArea::query()->pluck('id', 'code')->map(fn ($id): int => (int) $id)->all();

        /** @var array{noted_on: string, tender_sources: list<array<string, mixed>>, parties: list<array<string, mixed>>} $data */
        $data = require database_path('seeders/data/market_map.php');

        $this->seedTenderSources($data['tender_sources']);

        $hasContacts = SchemaReadiness::hasBatch('B27');
        $hasNetwork = SchemaReadiness::hasBatch('B28');

        if (! $hasContacts) {
            $this->command?->warn('B27 uygulanmamis; yetkili kisiler ve kisi kanallari atlandi.');
        }

        if (! $hasNetwork) {
            $this->command?->warn('B28 uygulanmamis; network ve gorusme notlari atlandi.');
        }

        foreach ($data['parties'] as $company) {
            $party = $this->seedParty($company);

            foreach ((array) $company['roles'] as $role) {
                $this->seedRole($party, (string) $role);
            }

            $this->seedActivities($party, (array) $company['activities']);
            $this->seedCity($party, $company['city'] ?? null);

            $contacts = [];

            if ($hasContacts) {
                $contacts = $this->seedContacts($party, (array) $company['contacts'], $hasNetwork);
            }

            if ($hasNetwork) {
                $this->seedNotes($party, (array) $company['notes'], $contacts, (string) $data['noted_on']);
            }
        }

        $this->command?->info(sprintf(
            'Pazar haritasi: %d firma olusturuldu, %d zaten vardi; %d ihale kaynagi eklendi.',
            $this->totals['parties_created'],
            $this->totals['parties_existing'],
            $this->totals['tender_sources'],
        ));
        $this->command?->info(sprintf(
            'Eklenen: %d tip, %d faaliyet satiri, %d adres, %d kisi, %d kisi kanali, %d gorusme notu.',
            $this->totals['roles'],
            $this->totals['activities'],
            $this->totals['addresses'],
            $this->totals['contacts'],
            $this->totals['contact_channels'],
            $this->totals['notes'],
        ));
    }

    /**
     * Son calismanin sayaclari (dogrulama ve raporlama icin).
     *
     * @return array<string, int>
     */
    public function totals(): array
    {
        return $this->totals;
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     */
    private function seedTenderSources(array $sources): void
    {
        foreach ($sources as $source) {
            if (TenderSource::query()->where('code', $source['code'])->exists()) {
                continue;
            }

            app(TenderSourceService::class)->create([
                'code' => $source['code'],
                'name_tr' => $source['name_tr'],
                'name_en' => $source['name_en'],
                'source_type' => $source['source_type'],
                'base_url' => $source['base_url'],
                'access_mode' => TenderAccessMode::Manual->value,
                'scraping_allowed' => false,
                'status' => ActiveStatus::Active->value,
            ]);

            $this->totals['tender_sources']++;
        }
    }

    /**
     * Firmayi bulur ya da acar. Var olan firmada yalniz bos duran koken doldurulur;
     * durum degistirilmez.
     *
     * @param  array<string, mixed>  $company
     */
    private function seedParty(array $company): Party
    {
        $name = trim((string) $company['name']);
        $lookup = filled($company['match'] ?? null) ? (string) $company['match'] : $name;
        $origin = filled($company['origin'] ?? null) ? (string) $company['origin'] : null;

        /** @var Party|null $party */
        $party = Party::query()
            ->where('party_kind', 'organization')
            ->where('normalized_name', $this->normalize($lookup))
            ->first();

        if ($party === null) {
            /** @var Party $party */
            $party = app(PartyService::class)->create([
                'party_kind' => 'organization',
                'display_name' => $name,
                'country_code' => 'TR',
                'status' => (string) ($company['status'] ?? 'prospect'),
                'origin' => $origin,
                'is_competitor' => false,
                'organization_profile' => ['legal_name' => $name],
            ]);

            $this->totals['parties_created']++;

            return $party;
        }

        $this->totals['parties_existing']++;

        if ($origin !== null && $party->getAttribute('origin') === null) {
            /** @var Party $party */
            $party = app(PartyService::class)->update($party, ['origin' => $origin]);
        }

        return $party;
    }

    /** Ayni tipte acik (valid_until bos) satir yoksa tipi acar. */
    private function seedRole(Party $party, string $role): void
    {
        $open = $party->roles()->get()->first(
            fn (PartyRole $row): bool => $row->role_code->value === $role && $row->valid_until === null,
        );

        if ($open !== null) {
            return;
        }

        try {
            app(PartyRoleService::class)->create([
                'party_id' => $party->getKey(),
                'role_code' => $role,
                'status' => 'active',
            ]);

            $this->totals['roles']++;
        } catch (DuplicateRecordException) {
            // Tip baska bir yoldan acilmis; mevcut kayit korunur.
        }
    }

    /**
     * @param  list<array{project_type: ?string, area: string, sub: ?string}>  $activities
     */
    private function seedActivities(Party $party, array $activities): void
    {
        $rows = [];

        foreach ($activities as $activity) {
            $areaId = $this->areaIds[$activity['area']] ?? null;
            $subId = $activity['sub'] !== null ? ($this->areaIds[$activity['sub']] ?? null) : null;

            if ($areaId === null || ($activity['sub'] !== null && $subId === null)) {
                $this->command?->warn(sprintf('%s: faaliyet alani bulunamadi (%s / %s), satir atlandi.', $party->display_name, $activity['area'], (string) $activity['sub']));

                continue;
            }

            $rows[] = [
                'project_type' => $activity['project_type'],
                'activity_area_id' => $areaId,
                'sub_activity_area_id' => $subId,
            ];
        }

        if ($rows !== []) {
            $this->totals['activities'] += app(PartyActivityAreaService::class)->ensure($party, $rows);
        }
    }

    /** CED firmalarinda yalniz sehir bilinir; firmanin hic adresi yoksa ofis adresi olarak yazilir. */
    private function seedCity(Party $party, ?string $city): void
    {
        if (blank($city) || $party->addresses()->exists()) {
            return;
        }

        app(AddressService::class)->create([
            'party_id' => $party->getKey(),
            'address_type' => 'office',
            'line1' => (string) $city,
            'city' => (string) $city,
            'country_code' => 'TR',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $this->totals['addresses']++;
    }

    /**
     * Kisileri ada gore (buyuk/kucuk harf duyarsiz) eslestirir, eksikleri acar
     * ve kanallarini yazar. Ad => kisi kimligi haritasi doner (notlar icin).
     *
     * @param  list<array{name: string, network: ?string, channels: list<array{type: string, value: string}>}>  $contacts
     * @return array<string, int>
     */
    private function seedContacts(Party $party, array $contacts, bool $hasNetwork): array
    {
        $existing = $party->contacts()->get()->keyBy(
            fn (ContactRelationship $contact): string => $this->normalize($contact->displayName()),
        );
        $first = $existing->isEmpty();
        $seen = [];

        foreach ($party->communicationPoints()->get() as $point) {
            $seen[$point->channel_type->value.'|'.$this->normalize((string) $point->normalized_value)] = true;
        }

        $ids = [];

        foreach ($contacts as $row) {
            $name = trim((string) $row['name']);
            $key = $this->normalize($name);
            $network = filled($row['network'] ?? null) ? (string) $row['network'] : null;

            /** @var ContactRelationship|null $contact */
            $contact = $existing->get($key);

            if ($contact === null) {
                /** @var ContactRelationship $contact */
                $contact = app(ContactRelationshipService::class)->create([
                    'organization_party_id' => $party->getKey(),
                    'contact_name' => $name,
                    'relationship_role' => 'other',
                    'is_primary' => $first,
                ] + ($hasNetwork ? ['network_note' => $network] : []));

                $existing->put($key, $contact);
                $first = false;
                $this->totals['contacts']++;
            } elseif ($hasNetwork && $network !== null && $contact->getAttribute('network_note') === null) {
                /** @var ContactRelationship $contact */
                $contact = app(ContactRelationshipService::class)->update($contact, ['network_note' => $network]);
                $existing->put($key, $contact);
            }

            $ids[$key] = (int) $contact->getKey();

            foreach ($row['channels'] ?? [] as $channel) {
                $value = trim((string) $channel['value']);
                $channelKey = $channel['type'].'|'.$this->normalize($value);

                if ($value === '' || isset($seen[$channelKey])) {
                    continue;
                }

                app(CommunicationPointService::class)->create([
                    'party_id' => $party->getKey(),
                    'contact_relationship_id' => (int) $contact->getKey(),
                    'channel_type' => $channel['type'],
                    'value' => $value,
                    'is_primary' => false,
                    'status' => 'active',
                ]);

                $seen[$channelKey] = true;
                $this->totals['contact_channels']++;
            }
        }

        return $ids;
    }

    /**
     * Metni ayni olan not varsa atlar, yoksa yazar ("aranmayacak" notlari dahil).
     *
     * @param  list<array{subject: string, text: string, next_action: ?string, contact: ?string}>  $notes
     * @param  array<string, int>  $contacts
     */
    private function seedNotes(Party $party, array $notes, array $contacts, string $notedOn): void
    {
        $existing = [];

        foreach ($party->meetingNotes()->get() as $note) {
            $existing[$this->normalize((string) $note->note)] = true;
        }

        foreach ($notes as $row) {
            $text = trim((string) $row['text']);
            $key = $this->normalize($text);

            if ($text === '' || isset($existing[$key])) {
                continue;
            }

            app(PartyMeetingNoteService::class)->create([
                'party_id' => $party->getKey(),
                'contact_relationship_id' => filled($row['contact'] ?? null) ? ($contacts[$this->normalize((string) $row['contact'])] ?? null) : null,
                'noted_on' => $notedOn,
                'channel' => 'other',
                'subject' => $row['subject'],
                'note' => $text,
                'next_action' => filled($row['next_action'] ?? null) ? (string) $row['next_action'] : null,
                'personnel_id' => null,
            ]);

            $existing[$key] = true;
            $this->totals['notes']++;
        }
    }

    /** Uygulamanin normalized_name / normalized_value kurali: kucuk harf, tek bosluk. */
    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->squish()->value();
    }
}
