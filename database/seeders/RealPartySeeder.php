<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Party\VisitPriority;
use App\Exceptions\DuplicateRecordException;
use App\Models\Party\ContactRelationship;
use App\Models\Party\Party;
use App\Models\Party\PartyRole;
use App\Services\Party\AddressService;
use App\Services\Party\CommunicationPointService;
use App\Services\Party\ContactRelationshipService;
use App\Services\Party\PartyMeetingNoteService;
use App\Services\Party\PartyRoleService;
use App\Services\Party\PartyService;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Gercek taraf verisi: firma takip listesi (Firma_Takip_Listesi.xlsx, 14
 * Eylul 2026 surumu; 16 Eylul 2026 kullanici talimatiyla aktarildi). Kaynak
 * dizi database/seeders/data/real_parties.php dosyasindadir; bu seeder o
 * diziyi okur ve her firmayi "prospect" durumunda organizasyon tarafi
 * olarak yazar. Kurgusal ornek seeder'lardan TAMAMEN BAGIMSIZDIR.
 *
 * Kullanici karari (16 Eylul 2026, B28):
 *  - Listedeki rol (isveren / otorite) taraf rolu olarak acilir.
 *  - "Network" (firmanin nereden tanindigi) parties.network_note'a,
 *    "ziyaret onceligi" (1 acil ziyaret, 2 rutin gorusme, 3 telefon)
 *    parties.visit_priority'ye, kisinin network'u
 *    contact_relationships.network_note'a yazilir. Bu alanlar B28
 *    uygulanmadan yazilamaz; o zaman uyari verilir ve atlanir.
 *  - Firma kartvizitindeki adres, web sitesi, telefon ve e-posta firmanin
 *    kendi kanallaridir; yetkili kisi ve kanallari kisi kaydi (B27) olur.
 *  - "Notlarimiz" sutunu gorusme notu (party_meeting_notes, B28) olur;
 *    gorusen personel bos birakilir, arayuzde "Sistem" gorunur.
 *  - Projeler (listedeki 'projects' anahtari) SIMDILIK AKTARILMAZ; kullanici
 *    proje gelistirmeleri bitince ayri bir adimla aktarilmasina karar verdi.
 *
 * Idempotent: firma normalized_name ile bulunur; var olan firmanin rolu,
 * adresi, kanali, kisisi ve notu tekrar yazilmaz, yalniz eksikler eklenir.
 * Hicbir kayit silinmez, truncate edilmez. Bu seeder kalici uretim
 * verisidir ve uretimde de calisir.
 *
 * Yazmalar servisler uzerinden gider (S-2); party_no, normalized_name,
 * normalized_value ve Personel Hareketleri kaydi uygulama kurallariyla
 * ayni olur.
 */
class RealPartySeeder extends Seeder
{
    /** @var array<string, int> */
    private array $totals = [
        'parties_created' => 0,
        'parties_existing' => 0,
        'roles' => 0,
        'addresses' => 0,
        'channels' => 0,
        'contacts' => 0,
        'contact_channels' => 0,
        'notes' => 0,
    ];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B16')) {
            $this->command?->warn('Taraf tablolari (B16) yok; RealPartySeeder atlandi.');

            return;
        }

        /** @var list<array<string, mixed>> $companies */
        $companies = require database_path('seeders/data/real_parties.php');

        $hasContacts = SchemaReadiness::hasBatch('B27');
        $hasNetwork = SchemaReadiness::hasBatch('B28');

        if (! $hasContacts) {
            $this->command?->warn('B27 uygulanmamis; yetkili kisiler ve kisi kanallari atlandi.');
        }

        if (! $hasNetwork) {
            $this->command?->warn('B28 uygulanmamis; network, ziyaret onceligi ve gorusme notlari atlandi.');
        }

        foreach ($companies as $company) {
            $party = $this->seedParty($company, $hasNetwork);

            $this->seedRole($party, (string) $company['role']);
            $this->seedAddress($party, $company['address'] ?? null);

            $ledger = $this->channelLedger($party);
            $this->seedChannels($party, $company['channels'] ?? [], $ledger);

            if ($hasContacts) {
                $this->seedContacts($party, $company['contacts'] ?? [], $ledger, $hasNetwork);
            }

            if ($hasNetwork) {
                $this->seedNotes($party, $company['notes'] ?? []);
            }
        }

        $this->command?->info(sprintf(
            'Gercek taraflar: %d firma olusturuldu, %d zaten vardi.',
            $this->totals['parties_created'],
            $this->totals['parties_existing'],
        ));
        $this->command?->info(sprintf(
            'Eklenen: %d rol, %d adres, %d kanal, %d kisi, %d kisi kanali, %d gorusme notu.',
            $this->totals['roles'],
            $this->totals['addresses'],
            $this->totals['channels'],
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
     * Firmayi normalized_name ile bulur, yoksa olusturur. Var olan firmada
     * yalniz bos duran network / ziyaret onceligi doldurulur.
     *
     * @param  array<string, mixed>  $company
     */
    private function seedParty(array $company, bool $hasNetwork): Party
    {
        $name = trim((string) $company['name']);
        $network = filled($company['network'] ?? null) ? (string) $company['network'] : null;
        $priority = $hasNetwork ? VisitPriority::tryFromRank($company['priority'] ?? null)?->value : null;

        /** @var Party|null $party */
        $party = Party::query()
            ->where('party_kind', 'organization')
            ->where('normalized_name', $this->normalize($name))
            ->first();

        if ($party === null) {
            /** @var Party $party */
            $party = app(PartyService::class)->create([
                'party_kind' => 'organization',
                'display_name' => $name,
                'country_code' => 'TR',
                'status' => 'prospect',
                'organization_profile' => ['legal_name' => $name],
            ] + ($hasNetwork ? ['network_note' => $network, 'visit_priority' => $priority] : []));

            $this->totals['parties_created']++;

            return $party;
        }

        $this->totals['parties_existing']++;

        if ($hasNetwork) {
            $fill = [];

            if ($network !== null && $party->getAttribute('network_note') === null) {
                $fill['network_note'] = $network;
            }

            if ($priority !== null && $party->getAttribute('visit_priority') === null) {
                $fill['visit_priority'] = $priority;
            }

            if ($fill !== []) {
                /** @var Party $party */
                $party = app(PartyService::class)->update($party, $fill);
            }
        }

        return $party;
    }

    /** Ayni rolde acik (valid_until bos) satir yoksa rolu acar. */
    private function seedRole(Party $party, string $role): void
    {
        $open = $party->roles->first(
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
            // Rol baska bir yoldan acilmis; mevcut kayit korunur.
        }
    }

    /**
     * Firmanin hic adresi yoksa kartvizit adresini ofis adresi olarak yazar.
     *
     * @param  array{line1: string, district: ?string, city: string}|null  $address
     */
    private function seedAddress(Party $party, ?array $address): void
    {
        if ($address === null || $party->addresses->isNotEmpty()) {
            return;
        }

        app(AddressService::class)->create([
            'party_id' => $party->getKey(),
            'address_type' => 'office',
            'line1' => $address['line1'],
            'district' => $address['district'] ?? null,
            'city' => $address['city'],
            'country_code' => 'TR',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $this->totals['addresses']++;
    }

    /**
     * Taraftaki mevcut kanallarin defteri: benzersizlik anahtari
     * (tur|normalize deger; DB'deki party_id + channel_type + normalized_value
     * anahtariyla ayni) ve o turde kanali olan turler (asil isareti icin).
     *
     * @return array{seen: array<string, true>, types: array<string, true>}
     */
    private function channelLedger(Party $party): array
    {
        $ledger = ['seen' => [], 'types' => []];

        foreach ($party->communicationPoints as $point) {
            $type = $point->channel_type->value;

            $ledger['seen'][$type.'|'.$this->normalize((string) $point->normalized_value)] = true;
            $ledger['types'][$type] = true;
        }

        return $ledger;
    }

    /**
     * Defterde olmayan kanallari yazar. Kisi kanali icin $contactId verilir;
     * bos ise kanal firmanin kendisinindir. "Varsayilan" (is_primary) yalniz
     * firmanin kendi kanallarinda, tur basina ilk kanal icin isaretlenir;
     * kisi kanallari varsayilan yapilmaz.
     *
     * @param  list<array{type: string, value: string}>  $channels
     * @param  array{seen: array<string, true>, types: array<string, true>}  $ledger
     */
    private function seedChannels(Party $party, array $channels, array &$ledger, ?int $contactId = null): void
    {
        $withOwner = SchemaReadiness::hasBatch('B27');

        foreach ($channels as $channel) {
            $type = (string) $channel['type'];
            $value = trim((string) $channel['value']);
            $key = $type.'|'.$this->normalize($value);

            if ($value === '' || isset($ledger['seen'][$key])) {
                continue;
            }

            app(CommunicationPointService::class)->create([
                'party_id' => $party->getKey(),
                'channel_type' => $type,
                'value' => $value,
                'is_primary' => $contactId === null && ! isset($ledger['types'][$type]),
                'status' => 'active',
            ] + ($withOwner ? ['contact_relationship_id' => $contactId] : []));

            $ledger['seen'][$key] = true;

            if ($contactId === null) {
                $ledger['types'][$type] = true;
            }
            $this->totals[$contactId === null ? 'channels' : 'contact_channels']++;
        }
    }

    /**
     * Yetkili kisileri ada gore (buyuk/kucuk harf duyarsiz) eslestirir,
     * eksikleri acar ve kisi kanallarini ayni taraf defteriyle yazar.
     *
     * @param  list<array{name: string, role: string, network: ?string, channels: list<array{type: string, value: string}>}>  $contacts
     * @param  array{seen: array<string, true>, types: array<string, true>}  $ledger
     */
    private function seedContacts(Party $party, array $contacts, array &$ledger, bool $hasNetwork): void
    {
        $existing = $party->contacts->keyBy(
            fn (ContactRelationship $contact): string => $this->normalize($contact->displayName()),
        );
        $first = $existing->isEmpty();

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
                    'relationship_role' => (string) $row['role'],
                    'is_primary' => $first,
                ] + ($hasNetwork ? ['network_note' => filled($row['network'] ?? null) ? (string) $row['network'] : null] : []));

                $existing->put($key, $contact);
                $first = false;
                $this->totals['contacts']++;
            } elseif ($hasNetwork && filled($row['network'] ?? null) && $contact->getAttribute('network_note') === null) {
                // Var olan kiside yalniz bos duran network doldurulur (B28 sonradan
                // uygulandiginda ikinci calistirma icin).
                /** @var ContactRelationship $contact */
                $contact = app(ContactRelationshipService::class)->update($contact, ['network_note' => (string) $row['network']]);
                $existing->put($key, $contact);
            }

            $this->seedChannels($party, $row['channels'] ?? [], $ledger, (int) $contact->getKey());
        }
    }

    /**
     * Metni ayni olan gorusme notu varsa atlar, yoksa yazar.
     *
     * @param  list<array{on: string, channel: string, subject: ?string, text: string}>  $notes
     */
    private function seedNotes(Party $party, array $notes): void
    {
        $existing = [];

        foreach ($party->meetingNotes as $note) {
            $existing[$this->normalize((string) $note->note)] = true;
        }

        foreach ($notes as $row) {
            $text = trim((string) $row['text']);
            $key = $this->normalize($text);
            $on = (string) ($row['on'] ?? '');

            if ($text === '' || isset($existing[$key])) {
                continue;
            }

            // Tarih kesin dogrulanir: model 'date' donusumu bozuk tarihi sessizce kaydirir.
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $on) !== 1 || ! checkdate((int) substr($on, 5, 2), (int) substr($on, 8, 2), (int) substr($on, 0, 4))) {
                $this->command?->warn(sprintf('%s: gecersiz gorusme tarihi "%s", not atlandi.', $party->display_name, $on));

                continue;
            }

            app(PartyMeetingNoteService::class)->create([
                'party_id' => $party->getKey(),
                'noted_on' => $on,
                'channel' => $row['channel'],
                'subject' => filled($row['subject'] ?? null) ? (string) $row['subject'] : null,
                'note' => $text,
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
