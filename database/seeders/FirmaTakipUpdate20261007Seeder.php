<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Acquisition\BusinessSourceKind;
use App\Enums\Acquisition\LicenseStatus;
use App\Enums\Party\VisitPriority;
use App\Models\Acquisition\BusinessCase;
use App\Models\Party\ContactRelationship;
use App\Models\Party\Party;
use App\Models\Party\PartyMeetingNote;
use App\Models\Personnel\Personnel;
use App\Services\Acquisition\BusinessCaseService;
use App\Services\Audit\ActorContext;
use App\Services\Party\AddressService;
use App\Services\Party\CommunicationPointService;
use App\Services\Party\ContactRelationshipService;
use App\Services\Party\PartyMeetingNoteService;
use App\Services\Party\PartyMergeService;
use App\Services\Party\PartyRoleService;
use App\Services\Party\PartyService;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\TransactionRunner;
use Database\Seeders\Support\ProtectedSeeder;
use Database\Seeders\Support\SeedGuard;
use Illuminate\Support\Str;

/**
 * Firma takip listesinin 07.10.2026 surumu (D-170, 7 Ekim 2026 kullanici
 * talimati: "Bunlar yatirimci projeleri, var olanlarda gerekiyorsa guncelleme
 * seed'i olussun, olmayanlarda yeni olusturulsun hem taraf hem yatirimci
 * projesi olarak, hatti bagliysa var olan teklif ile baglantilari da
 * kurulsun ... Acilmasi gereken taraflari da acalim, eslesen kaydi yoksa").
 *
 * Kaynak: data/firma_takip_2026_10_07.php (21.09 aktarimiyla ayni ayristirici).
 * Sira:
 *
 * 1. Birlestirmeler (MERGES): kullanicinin sohbette verdigi kararlar (AKSA,
 *    ENTEK, AKFEN, FERNAS, LIMAK, YILDIRIM) ve "cift kayitlardan kurtulmaliyiz"
 *    dedigi yedi cift. PartyMergeService kaynak tarafin her seyini hedefe
 *    tasir; kaynak silinmez, "birlesti" durumuna gecer. Projeler birlestirilmez.
 * 2. Ad duzeltmeleri (NAMES): kisa ad / uzun ad (unvan). Kullanici kurali:
 *    ikisi bilinmeyen tarafta yalniz uzun ad yazilir, elle duzeltilir.
 * 3. Firmalar: listedeki firma sistemde yoksa yeni taraf (uzun ad = listedeki
 *    ad); varsa yalniz eksik adres (hic yoksa), kanal, yetkili kisi ve yeni
 *    gorusme notu eklenir. Var olan bilgi degistirilmez, silinmez.
 * 4. Projeler = yatirimci projesi. Firmanin var olan potansiyel isi (baslik,
 *    adsiz satirda "– guc proje", ayni ad kalibi; KKS'den gelen ve teklife
 *    bagli is dahil) bulunursa yeni kayit acilmaz: bos Proje durumu (Onlisans,
 *    Lisans, 5.1.h, 5.1.c, YEKA) doldurulur, ozette olmayan proje durumu
 *    aciklamaya "07.10.2026 listesi" satiri olarak eklenir. Bulunamazsa
 *    "Yatirimci projesi" turunde yeni potansiyel is acilir (B47). Yeni listede
 *    bos gelen eski izin (CED / IDK) notlari korunur.
 *
 * Listeden cikan firma ve projelere dokunulmaz. Var olan kayitlarda yalniz
 * SeedGuard::allowingUpdates() icindeki ileri giden islemler yapilir; her
 * satir tek islemdir ve seed arsivine duser (bir kez). preview() hicbir sey
 * yazmadan yapilacaklari listeler. Kurulumda DEPLOY_SEEDERS ile calisir.
 */
final class FirmaTakipUpdate20261007Seeder extends ProtectedSeeder
{
    public const LIST_DATE = '07.10.2026';

    private const DATA_FILE = 'firma_takip_2026_10_07.php';

    /**
     * [kaynak taraf adlari, hedef taraf adlari]: kaynak hedefe katilir.
     * Adlar sistemdeki (normalize) adlardir; ilk bulunan kullanilir.
     *
     * @var list<array{0: list<string>, 1: list<string>, 2: string}>
     */
    public const MERGES = [
        [['AKSA ENERJİ TİCARETİ A.Ş.'], ['Aksa Yenilenebilir Enerji Üretim Anonim Şirketi', 'AKSA YENİLENEBİLİR ENERJİ ÜRETİM A.Ş.'], 'AKSA: kullanıcı kararı, tek AKSA (07.10.2026).'],
        [['ENTEK ELEKTRİK ÜRETİMİ ANONİM ŞİRKETİ'], ['Entek Elektrik Üretim Anonim Şirketi', 'ENTEK ELEKTRİK ÜRETİMİ A.Ş. (KOÇ Holding)'], 'ENTEK: kullanıcı kararı, tek taraf; projeler birleştirilmez (07.10.2026).'],
        [['AKFEN'], ['AKFEN ELEKTRİK ENERJİSİ TOPTAN SATIŞ A.Ş.', 'AKFEN HOLDİNG'], 'AKFEN: kullanıcı kararı, tek taraf AKFEN HOLDİNG (07.10.2026).'],
        [['FERNAS İNŞAAT'], ['FERNAS ŞİRKETLER GRUBU'], 'FERNAS: kullanıcı kararı, hepsi FERNAS Şirketler Grubu altında (07.10.2026).'],
        [['LİMAK İNŞAAT'], ['LİMAK YENİLENEBİLİR ENERJİ', 'Limak Holding'], 'LİMAK: kullanıcı kararı, hepsi birleşir (07.10.2026).'],
        [['YILDIRIM ENERJİ'], ['YILDIRIM HOLDİNG'], 'YILDIRIM: kullanıcı kararı, hepsi YILDIRIM HOLDİNG (07.10.2026).'],
        [['ADİS ELEKTRİK ENERJİSİ TEDARİK A.Ş.'], ['ADİS ELEKTRİK ENERJİSİ TEDARİK ANONİM ŞİRKETİ'], 'Çift kayıt: kullanıcı kararı (07.10.2026).'],
        [['AXİS ENERJİ A.Ş.'], ['AXİS ENERJİ ANONİM ŞİRKETİ'], 'Çift kayıt: kullanıcı kararı (07.10.2026).'],
        [['İNOVATİF ENERJİ TEDARİK A.Ş.'], ['İNOVATİF ENERJİ TEDARİK ANONİM ŞİRKETİ'], 'Çift kayıt: kullanıcı kararı (07.10.2026).'],
        [['SYCS İNŞAAT ÇİMENTO SAN.VE TİC.A.Ş.'], ['SYCS İNŞAAT ÇİMENTO SANAYİ VE TİCARET ANONİM ŞİRKETİ'], 'Çift kayıt: kullanıcı kararı (07.10.2026).'],
        [['VAKKO TEKSTİL A.Ş.'], ['VAKKO TEKSTİL ve HAZIR GİYİM SANAYİ İŞLETMLERİ A.Ş.'], 'Çift kayıt: kullanıcı kararı (07.10.2026).'],
        [['BAYLAZ ENERJİ'], ['BAYLAZ GRUP ENERJİ İNŞAAT ANONİM ŞİRKETİ'], 'Çift kayıt: kullanıcı kararı (07.10.2026).'],
        [['PANDA PANGES'], ['PANGES PANDA ALÜMİNYUM'], 'Çift kayıt: doğru ad PANGES PANDA ALÜMİNYUM, "PANDA PANGES" yok (kullanıcı, 07.10.2026).'],
    ];

    /**
     * Ad duzeltmeleri: [taraf adlari (once eski ad), kisa ad, uzun ad (unvan)].
     * Kisa ad null: bilinmiyor, elle girilecek (kullanici kurali).
     *
     * @var list<array{0: list<string>, 1: string|null, 2: string}>
     */
    public const NAMES = [
        [['Aksa Yenilenebilir Enerji Üretim Anonim Şirketi'], null, 'AKSA YENİLENEBİLİR ENERJİ ÜRETİM A.Ş.'],
        [['Entek Elektrik Üretim Anonim Şirketi'], null, 'ENTEK ELEKTRİK ÜRETİMİ A.Ş. (KOÇ Holding)'],
        [['AKFEN ELEKTRİK ENERJİSİ TOPTAN SATIŞ A.Ş.'], 'AKFEN HOLDİNG', 'AKFEN ELEKTRİK ENERJİSİ TOPTAN SATIŞ A.Ş.'],
        [['BAYBURT GRUP'], 'BAYBURT GRUP', 'BAYBURT GRUP İNŞAAT NAKLİYAT MADENCİLİK İTHALAT İHRACAT SANAYİ VE TİCARET ANONİM ŞİRKETİ'],
        [['ISKUR HOLDİNG'], 'İSKUR HOLDİNG', 'İSKUR TEKSTİL ENERJİ TİC. VE SAN. A.Ş.'],
        [['LİMAK YENİLENEBİLİR ENERJİ'], 'Limak Holding', 'Limak Yenilenebilir Enerji Anonim Şirketi'],
        [['OZE 2 ENERJİ ANONİM ŞİRKETİ'], 'OZE GRUP', 'OZE 2 ENERJİ A.Ş.'],
        [['OZE 3 ENERJİ ANONİM ŞİRKETİ'], 'OZE GRUP', 'OZE 3 ENERJİ A.Ş.'],
        [['YILDIRIM HOLDİNG'], 'YILDIRIM HOLDİNG', 'YILDIRIM HOLDİNG'],
        [['TÜRK TELEKOM'], null, 'TÜRK TELEKOMÜNASYON ANONİM ŞİRKETİ'],
        [['TAYFURLAR ENERJİ'], 'TAYFURLAR ENERJİ', 'TAYFURLAR ENERJİ ELEKTRİK ÜRETİM ANONİM ŞİRKETİ'],
        [['BAYLAZ GRUP ENERJİ İNŞAAT ANONİM ŞİRKETİ'], 'BAYLAZ ENERJİ', 'BAYLAZ GRUP ENERJİ İNŞAAT ANONİM ŞİRKETİ'],
    ];

    /**
     * Listedeki adi sistemdekiyle birebir ayni olmayan firmalar => sistemdeki
     * taraf adlari (ad duzeltmesinden sonraki ad dahil). Burada olmayan ve adi
     * birebir eslesmeyen firma yeni taraf olarak acilir.
     *
     * @var array<string, list<string>>
     */
    public const PARTY_ALIASES = [
        'AKSA YENİLENEBİLİR ENERJİ ÜRETİM A.Ş' => ['AKSA YENİLENEBİLİR ENERJİ ÜRETİM A.Ş.', 'Aksa Yenilenebilir Enerji Üretim Anonim Şirketi'],
        'ARY Holding' => ['ARY HOLDİNG'],
        'BAYBURT GRUP İNŞAAT NAKLİYAT MADENCİLİK İTHALAT İHRACAT SANAYİ VE TİCARET ANONİM ŞİRKETİ' => ['BAYBURT GRUP'],
        'DİYAR BATARYA SİS.VE YENİLEBİLİR ENERJİ YATIRIMLARI A.Ş.' => ['DİYAR BATARYA SİSTEMLERİ VE YENİLENEBİLİR ENERJİ YATIRIMLARI ANONİM ŞİRKETİ'],
        'ECOWİND 1 ENERJİ ANONİM ŞİRKETİ' => ['Ecowind 1 Enerji Anonim Şirketi'],
        'İSKUR TEKSTİL ENERJİ TİC. VE SAN. A.Ş.' => ['İSKUR HOLDİNG', 'ISKUR HOLDİNG'],
        'KUVVET ENERJİ ANONİM ŞİRKETİ' => ['Kuvvet Enerji Anonim Şirketi'],
        'Merge Enerji' => ['MERGE ENERJİ'],
        'Nokta Holding' => ['NOKTA HOLDİNG'],
        'PANDA ALÜMİNYUM PANGES' => ['PANGES PANDA ALÜMİNYUM'],
        'TAYFURLAR ENERJİ ELEKTRİK ÜRETİM ANONİM ŞİRKETİ' => ['TAYFURLAR ENERJİ'],
        'TÜRK TELEKOMÜNASYON ANONİM ŞİRKETİ' => ['TÜRK TELEKOM'],
    ];

    /** Listedeki proje turu => [proje tipi kodu, kapsamlar]. */
    public const TYPES = [
        'GES EDT' => ['GES', ['ges', 'bes']],
        'DGES' => ['GES', ['ges', 'bes']],
        'GES' => ['GES', ['ges']],
        'RES EDT' => ['RES', ['res', 'bes']],
        'DRES' => ['RES', ['res', 'bes']],
        'RES' => ['RES', ['res']],
        'HES' => ['HES', ['hes']],
        'İEH' => ['ENH', ['enh_eih']],
        'EİH' => ['ENH', ['enh_eih']],
    ];

    private bool $dry = false;

    /** @var array<int, int> onizlemede: birlesecek kaynak taraf => hedef */
    private array $plannedMerges = [];

    /** @var array<string, int> */
    private array $totals = [
        'merged' => 0, 'renamed' => 0, 'parties' => 0, 'addresses' => 0, 'channels' => 0,
        'contacts' => 0, 'notes' => 0, 'cases_created' => 0, 'cases_filled' => 0, 'cases_same' => 0, 'skipped' => 0,
    ];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B28')) {
            $this->command?->warn('B28 uygulanmamis; firma takip 07.10 guncellemesi atlandi.');

            return;
        }

        $ownerId = self::ownerId();

        if ($ownerId === null) {
            $this->command?->warn(FirmaTakipBusinessCaseSeeder::OWNER_EMAIL.' bulunamadi; firma takip 07.10 guncellemesi atlandi.');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        foreach (self::MERGES as [$sources, $targets, $reason]) {
            $this->guarded('merge:'.$this->normalize($sources[0]).'>'.$this->normalize($targets[0]), fn (): ?Party => $this->merge($sources, $targets, $reason)['party']);
        }

        foreach (self::NAMES as [$names, $short, $long]) {
            $this->guarded('name:'.$this->normalize($names[0]), fn (): ?Party => $this->rename($names, $short, $long)['party']);
        }

        foreach ($this->firms() as $firm) {
            $name = (string) $firm['name'];
            $party = $this->guarded('firm:'.$this->normalize($name), fn (): ?Party => $this->firm($firm, $ownerId)['party']);
            $party ??= $this->resolveFirm($name);

            if ($party === null) {
                continue;
            }

            foreach ($this->titled($firm['projects'] ?? []) as [$title, $project]) {
                $this->guarded('project:'.$this->normalize($name).'|'.$this->normalize($title), fn (): ?BusinessCase => $this->project($party, $title, $project, $ownerId)['case']);
            }
        }

        $this->command?->info(sprintf(
            'Firma takip 07.10: %d taraf birlesti, %d ad duzeltildi, %d yeni taraf; %d adres, %d kanal, %d kisi, %d gorusme notu eklendi; %d yeni yatirimci projesi, %d var olan iste proje durumu dolduruldu, %d degismeyen; %d satir atlandi.',
            $this->totals['merged'], $this->totals['renamed'], $this->totals['parties'], $this->totals['addresses'],
            $this->totals['channels'], $this->totals['contacts'], $this->totals['notes'], $this->totals['cases_created'],
            $this->totals['cases_filled'], $this->totals['cases_same'], $this->totals['skipped'],
        ));
    }

    /**
     * Yazmadan: birlestirmeler, ad duzeltmeleri, firma ve proje adimlari.
     *
     * @return array{merges: list<array<string, mixed>>, names: list<array<string, mixed>>, firms: list<array<string, mixed>>, projects: list<array<string, mixed>>}
     */
    public function preview(): array
    {
        $this->dry = true;
        $out = ['merges' => [], 'names' => [], 'firms' => [], 'projects' => []];

        foreach (self::MERGES as [$sources, $targets, $reason]) {
            $result = $this->merge($sources, $targets, $reason);
            unset($result['party']);
            $out['merges'][] = $result;
        }

        foreach (self::NAMES as [$names, $short, $long]) {
            $result = $this->rename($names, $short, $long);
            unset($result['party']);
            $out['names'][] = $result;
        }

        foreach ($this->firms() as $firm) {
            $result = $this->firm($firm, 0);
            $party = $result['party'];
            unset($result['party']);
            $out['firms'][] = ['firm' => $firm['name'], ...$result];

            foreach ($this->titled($firm['projects'] ?? []) as [$title, $project]) {
                $item = $this->project($party, $title, $project, 0);
                unset($item['case']);
                $out['projects'][] = ['firm' => $firm['name'], 'title' => $title, 'row' => $project['excel_row'] ?? null, ...$item];
            }
        }

        $this->dry = false;

        return $out;
    }

    /**
     * Onizleme icin: birlesmeleri yazmadan planlar; sonraki findParty() ve
     * ownersOf() birlesmis gibi calisir (diger D-170 / D-171 onizlemeleri).
     */
    public function planMerges(): void
    {
        $this->dry = true;

        foreach (self::MERGES as [$sources, $targets, $reason]) {
            $this->merge($sources, $targets, $reason);
        }

        $this->dry = false;
    }

    /**
     * Tarafin isleri kimlerde: kendisi ve ona katilmis (ya da onizlemede
     * katilacak) taraflar.
     *
     * @return list<int>
     */
    public function ownersOf(Party $party): array
    {
        $owners = [(int) $party->getKey(), ...Party::query()->where('merged_into_party_id', $party->getKey())->pluck('id')->map(fn ($id): int => (int) $id)->all()];

        foreach ($this->plannedMerges as $source => $target) {
            if ($target === (int) $party->getKey()) {
                $owners[] = $source;
            }
        }

        return array_values(array_unique($owners));
    }

    /**
     * Satir tek islemdir; hata olursa yalniz o satir geri alinir, arsive
     * dusmez ve sonraki kurulumda yeniden denenir.
     *
     * @template T
     *
     * @param  \Closure(): T  $write
     * @return T|null
     */
    private function guarded(string $key, \Closure $write): mixed
    {
        return $this->row($key, function () use ($key, $write): mixed {
            try {
                return SeedGuard::allowingUpdates(fn (): mixed => app(TransactionRunner::class)->run($write, 1));
            } catch (\Throwable $exception) {
                $this->totals['skipped']++;
                $this->command?->warn(sprintf('Firma takip 07.10 %s atlandi (sonra yeniden denenecek): %s', $key, $exception->getMessage()));

                return null;
            }
        });
    }

    /**
     * @param  list<string>  $sources
     * @param  list<string>  $targets
     * @return array{party: Party|null, source: string, target: string, action: string, moves: array<string, int>}
     */
    private function merge(array $sources, array $targets, string $reason): array
    {
        $source = $this->findParty($sources, false);
        $target = $this->findParty($targets);
        $result = ['party' => $target, 'source' => $sources[0], 'target' => $targets[0], 'action' => '', 'moves' => []];

        if ($target === null) {
            return [...$result, 'action' => 'hedef taraf bulunamadi'];
        }

        if ($source === null || $source->getKey() === $target->getKey() || $source->merged_into_party_id !== null) {
            return [...$result, 'action' => 'zaten birlesmis ya da kaynak yok'];
        }

        $service = app(PartyMergeService::class);

        if ($this->dry) {
            $this->plannedMerges[(int) $source->getKey()] = (int) $target->getKey();

            return [...$result, 'action' => '#'.$source->getKey().' -> #'.$target->getKey(), 'moves' => array_filter($service->preview($source, $target))];
        }

        $service->merge($source, $target, $reason);
        $this->totals['merged']++;

        return [...$result, 'party' => $target->refresh(), 'action' => 'birlesti'];
    }

    /**
     * @param  list<string>  $names
     * @return array{party: Party|null, names: string, action: string}
     */
    private function rename(array $names, ?string $short, string $long): array
    {
        $party = $this->findParty([...$names, $long, ...array_filter([$short])]);
        $label = $names[0].' -> kısa: '.($short ?? '(boş)').', uzun: '.$long;

        if ($party === null) {
            return ['party' => null, 'names' => $label, 'action' => 'taraf bulunamadi'];
        }

        $profile = $party->organizationProfile;

        if ($profile !== null && $profile->legal_name === $long && ($short === null || $profile->trade_name === $short)) {
            return ['party' => $party, 'names' => $label, 'action' => 'zaten bu ad'];
        }

        if (! $this->dry) {
            app(PartyService::class)->update($party, [
                'organization_profile' => array_filter(['legal_name' => $long, 'trade_name' => $short], static fn (?string $value): bool => $value !== null),
            ]);
            $this->totals['renamed']++;
        }

        return ['party' => $party, 'names' => $label, 'action' => '#'.$party->getKey().' '.$party->display_name.' -> guncellenir'];
    }

    /**
     * Firma: taraf (yoksa yeni) ve eksik alt kayitlar.
     *
     * @param  array<string, mixed>  $firm
     * @return array{party: Party|null, party_label: string, steps: list<string>}
     */
    private function firm(array $firm, int $ownerId): array
    {
        $name = trim((string) $firm['name']);
        $party = $this->resolveFirm($name);
        $steps = [];

        if ($party === null) {
            $steps[] = 'yeni taraf (uzun ad = listedeki ad)';

            if ($this->dry) {
                $steps[] = count($firm['contacts'] ?? []).' kisi, '.count($firm['channels'] ?? []).' kanal, '.count($firm['notes'] ?? []).' not';

                return ['party' => null, 'party_label' => 'YENİ: '.$name, 'steps' => $steps];
            }

            $party = $this->createParty($name, $firm);
        }

        $label = '#'.$party->getKey().' '.$party->display_name;
        $party->load(['addresses', 'communicationPoints', 'contacts', 'meetingNotes']);

        if (($address = $firm['address'] ?? null) !== null && $party->addresses->isEmpty()) {
            $steps[] = 'adres';

            if (! $this->dry) {
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
        }

        $ledger = $this->channelLedger($party);
        $added = $this->addChannels($party, $firm['channels'] ?? [], $ledger);

        if ($added > 0) {
            $steps[] = $added.' kanal';
        }

        [$contacts, $contactChannels] = $this->addContacts($party, $firm['contacts'] ?? [], $ledger);

        if ($contacts + $contactChannels > 0) {
            $steps[] = $contacts.' kisi, '.$contactChannels.' kisi kanali';
        }

        $notes = $this->addNotes($party, $firm['notes'] ?? [], $ownerId);

        if ($notes !== []) {
            $steps[] = count($notes).' not: '.implode(' | ', array_map(static fn (string $note): string => Str::limit($note, 90), $notes));
        }

        return ['party' => $party, 'party_label' => $label, 'steps' => $steps];
    }

    /** @param  array<string, mixed>  $firm */
    private function createParty(string $name, array $firm): Party
    {
        /** @var Party $party */
        $party = app(PartyService::class)->create([
            'party_kind' => 'organization',
            'display_name' => $name,
            'country_code' => 'TR',
            'status' => 'prospect',
            'network_note' => filled($firm['network'] ?? null) ? (string) $firm['network'] : null,
            'visit_priority' => VisitPriority::tryFromRank($firm['priority'] ?? null)?->value,
            'organization_profile' => ['legal_name' => $name],
        ]);

        app(PartyRoleService::class)->create([
            'party_id' => $party->getKey(),
            'role_code' => (string) ($firm['role'] ?? 'employer'),
            'status' => 'active',
        ]);

        $this->totals['parties']++;

        return $party;
    }

    /**
     * Proje: var olan is bulunursa bos proje durumu doldurulur, yoksa yeni
     * yatirimci projesi acilir.
     *
     * @param  array<string, mixed>  $project
     * @return array{case: BusinessCase|null, action: string, steps: list<string>}
     */
    private function project(?Party $party, string $title, array $project, int $ownerId): array
    {
        $status = trim((string) ($project['status'] ?? ''));
        $license = $this->licenseStatus($status);
        $case = $party !== null ? $this->existingCase($party, $title, $project) : null;

        if ($case === null) {
            if ($this->dry || $party === null) {
                return ['case' => null, 'action' => 'yeni yatirimci projesi', 'steps' => array_values(array_filter([$license?->getLabel(), trim((string) ($project['type'] ?? '')), trim((string) ($project['power'] ?? ''))]))];
            }

            // Adsiz satir: "Firma – 101 MW proje" (21.09 aktarimiyla ayni kalip).
            $final = str_starts_with($title, '– ') ? Str::limit((string) $party->display_name, 180, '').' '.$title : $title;

            /** @var BusinessCase $created */
            $created = app(BusinessCaseService::class)->create($this->payload($party, $final, $project, $ownerId, $license));
            $this->totals['cases_created']++;

            return ['case' => $created, 'action' => 'yeni yatirimci projesi', 'steps' => []];
        }

        $steps = [];
        $changes = [];

        if ($license !== null && $case->license_status === null && SchemaReadiness::hasBatch('B43')) {
            $changes['license_status'] = $license->value;
            $steps[] = 'Proje durumu: '.$license->getLabel();
        }

        $description = (string) $case->short_description;

        if ($status !== '' && ! str_contains($this->fingerprint($description), $this->fingerprint($status))) {
            $line = self::LIST_DATE.' listesi (satır '.($project['excel_row'] ?? '?').'): Proje durumu: '.$status.'.';
            $changes['short_description'] = trim($description."\n".$line);
            $steps[] = 'aciklamaya: '.$line;
        }

        $label = '#'.$case->getKey().' '.Str::limit((string) $case->title, 70).($case->proposals()->exists() ? ' (teklife bagli)' : '');

        if ($changes === []) {
            $this->totals['cases_same']++;

            return ['case' => $case, 'action' => 'var: '.$label, 'steps' => []];
        }

        if (! $this->dry) {
            $case->forceFill($changes)->save();
            $this->totals['cases_filled']++;
        }

        return ['case' => $case, 'action' => 'guncelle: '.$label, 'steps' => $steps];
    }

    /**
     * Firmanin (ve ona katilan taraflarin) isleri arasinda listedeki proje.
     *
     * @param  array<string, mixed>  $project
     */
    private function existingCase(Party $party, string $title, array $project): ?BusinessCase
    {
        $cases = BusinessCase::query()->whereIn('primary_party_id', $this->ownersOf($party))->get();
        $name = trim((string) ($project['name'] ?? ''));
        $key = $this->fingerprint($title);

        // Adsiz satir: basligi "<firma> – 101 MW proje" olan is (firma adi o gunku ad olabilir).
        if ($name === '') {
            $unnamed = fn (BusinessCase $case): bool => str_ends_with($this->normalize((string) $case->title), $this->normalize($title));

            return $cases->first($unnamed) ?? $this->siblingCases($party)->first($unnamed);
        }

        $match = $cases->first(fn (BusinessCase $case): bool => $this->normalize((string) $case->title) === $this->normalize($title))
            ?? $cases->first(fn (BusinessCase $case): bool => $this->fingerprint((string) $case->title) === $key);

        if ($match !== null) {
            return $match;
        }

        // Ad kalibi: "MANISA GORDES" = "MANISA GORDES GES"; en az 6 harf.
        $match = $cases->first(function (BusinessCase $case) use ($key): bool {
            $other = $this->fingerprint((string) $case->title);

            return mb_strlen($key) >= 6 && mb_strlen($other) >= 6 && (str_contains($other, $key) || str_contains($key, $other));
        });

        if ($match !== null) {
            return $match;
        }

        return $this->siblingCases($party)->first(fn (BusinessCase $case): bool => $this->normalize((string) $case->title) === $this->normalize($title));
    }

    /**
     * Ayni adla baslayan kardes firmalarin isleri (or. KUVVET A.S. / KUVVET LTD,
     * ENERJISA / Enerjisa Enerji Uretim A.S.): yeni liste satiri kardes firmaya
     * yazilmis olabilir; baslik ayniysa ayni projedir, ikinci kayit acilmaz.
     *
     * @return \Illuminate\Support\Collection<int, BusinessCase>
     */
    private function siblingCases(Party $party): \Illuminate\Support\Collection
    {
        $first = explode(' ', $this->normalize((string) $party->display_name))[0];

        return BusinessCase::query()
            ->whereHas('primaryParty', fn ($query) => $query->where('normalized_name', $first)->orWhere('normalized_name', 'like', $first.' %'))
            ->get();
    }

    /**
     * BusinessCaseService::create verisi (FirmaTakipBusinessCaseSeeder ile ayni kalip).
     *
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    private function payload(Party $party, string $title, array $project, int $ownerId, ?LicenseStatus $license): array
    {
        [$typeCode, $scopes] = self::TYPES[mb_strtoupper(trim((string) ($project['type'] ?? '')), 'UTF-8')] ?? [null, []];
        $power = trim((string) ($project['power'] ?? ''));
        $lines = ['Firma takip listesinden aktarıldı ('.self::LIST_DATE.(isset($project['excel_row']) ? ', satır '.$project['excel_row'] : '').').'];

        foreach (['status' => 'Proje durumu', 'type' => 'Proje türü', 'power' => 'Proje gücü', 'regulatory_note' => 'ÇED / lisans notu'] as $field => $label) {
            if (($value = trim((string) ($project[$field] ?? ''))) !== '') {
                $lines[] = $label.': '.$value;
            }
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
            'scopes' => self::scopeRows($scopes, $power),
            'license_status' => $license?->value,
            'development_kind' => 'investor_project',
        ];
    }

    /**
     * @param  list<string>  $scopes
     * @return array<string, array<string, mixed>>
     */
    public static function scopeRows(array $scopes, string $power): array
    {
        $note = $power !== '' ? Str::limit('Listedeki proje gücü: '.$power, 255, '') : null;
        $rows = [];

        foreach ($scopes as $scope) {
            $rows[$scope] = ['note' => $scope === 'bes' ? Str::limit('Elektrik depolama tesisi (EDT)'.($power !== '' ? ' – '.$power : ''), 255, '') : $note];

            if ($scope === 'ges') {
                $rows[$scope]['capacity_mw'] = self::megawatts($power);
            }
        }

        return $rows;
    }

    /** "30MW/30MWH" -> 30, "6,75 Mwe" -> 6.75; MWh ve MWm sayilmaz. */
    public static function megawatts(string $power): ?string
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*mw(?:e|p)?(?![a-zçğıöşü])/iu', $power, $match) !== 1) {
            return null;
        }

        return str_replace(',', '.', $match[1]);
    }

    /** Listedeki proje durumu => Proje durumu; CED / IDK gibi degerler yalniz aciklamada. */
    private function licenseStatus(string $status): ?LicenseStatus
    {
        $upper = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $status), 'UTF-8');

        return match (true) {
            str_contains($upper, 'YEKA') => LicenseStatus::Yeka,
            str_contains($upper, 'ÖNLİSANS') || str_contains($upper, 'ÖN LİSANS') => LicenseStatus::PreLicense,
            str_contains($upper, 'LİSANS') => LicenseStatus::License,
            str_contains($upper, '5.1.H') => LicenseStatus::Unlicensed51H,
            str_contains($upper, '5.1.C') => LicenseStatus::Unlicensed51C,
            default => null,
        };
    }

    /**
     * Basliklar (FirmaTakipBusinessCaseSeeder kurali): adsiz satir "Firma – guc
     * proje"; ayni adli farkli satirlara guc (ya da satir no) eki.
     *
     * @param  list<array<string, mixed>>  $projects
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    private function titled(array $projects): array
    {
        $groups = [];

        foreach ($projects as $project) {
            $name = trim((string) ($project['name'] ?? ''));
            $power = trim((string) ($project['power'] ?? ''));
            $title = $name !== '' ? Str::limit($name, 255, '') : ($power !== '' ? $power.' proje' : 'proje (adı belirtilmemiş)');
            $groups[$this->normalize($title)][] = [$title, $project];
        }

        $result = [];

        foreach ($groups as $group) {
            $unique = [];

            foreach ($group as [$title, $project]) {
                $unique[json_encode(array_diff_key($project, ['excel_row' => true]))] ??= [$title, $project];
            }

            $unique = array_values($unique);

            if (count($unique) === 1) {
                $result[] = $unique[0];

                continue;
            }

            $powers = array_map(static fn (array $item): string => trim((string) ($item[1]['power'] ?? '')), $unique);
            $distinct = count(array_unique($powers)) === count($powers) && ! in_array('', $powers, true);

            foreach ($unique as [$title, $project]) {
                $result[] = [Str::limit($title, 240, '').' ('.($distinct ? trim((string) $project['power']) : 'satır '.($project['excel_row'] ?? '?')).')', $project];
            }
        }

        // Adsiz satir basligi firmanin adini alir (yeni is acilirken).
        return array_map(fn (array $item): array => [$this->unnamedTitle($item[0], $item[1]), $item[1]], $result);
    }

    /** @param  array<string, mixed>  $project */
    private function unnamedTitle(string $title, array $project): string
    {
        return trim((string) ($project['name'] ?? '')) === '' ? '– '.$title : $title;
    }

    /**
     * Listedeki firmanin sistemdeki tarafi (birlesmis taraf hedefine gider).
     */
    public function resolveFirm(string $name): ?Party
    {
        return $this->findParty([$name, ...(self::PARTY_ALIASES[$name] ?? [])]);
    }

    /**
     * Adlardan ilk bulunan taraf; birlesmis taraf hedefine gider (onizlemede
     * planlanan birlesme de izlenir).
     *
     * @param  list<string>  $names
     */
    public function findParty(array $names, bool $followMerge = true): ?Party
    {
        foreach ($names as $name) {
            /** @var Party|null $party */
            $party = Party::query()
                ->where('party_kind', 'organization')
                ->where('normalized_name', $this->normalize($name))
                ->orderByRaw("status = 'merged'")
                ->first();

            if ($party === null) {
                continue;
            }

            for ($hop = 0; $followMerge && $hop < 5; $hop++) {
                $next = $party->merged_into_party_id ?? ($this->plannedMerges[(int) $party->getKey()] ?? null);

                if ($next === null) {
                    break;
                }

                $party = Party::query()->find($next) ?? $party;
            }

            return $party;
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function firms(): array
    {
        /** @var list<array<string, mixed>> $firms */
        $firms = require __DIR__.'/data/'.self::DATA_FILE;

        return $firms;
    }

    /**
     * @return array{seen: array<string, true>, types: array<string, true>}
     */
    private function channelLedger(Party $party): array
    {
        $ledger = ['seen' => [], 'types' => []];

        foreach ($party->communicationPoints as $point) {
            $type = $point->channel_type->value;
            $ledger['seen'][$type.'|'.$this->fingerprint((string) $point->value)] = true;
            $ledger['types'][$type] = true;
        }

        return $ledger;
    }

    /**
     * @param  list<array{type: string, value: string}>  $channels
     * @param  array{seen: array<string, true>, types: array<string, true>}  $ledger
     */
    private function addChannels(Party $party, array $channels, array &$ledger, ?int $contactId = null): int
    {
        $added = 0;

        foreach ($channels as $channel) {
            $type = (string) $channel['type'];
            $value = trim((string) $channel['value']);
            $key = $type.'|'.$this->fingerprint($value);

            if ($value === '' || isset($ledger['seen'][$key])) {
                continue;
            }

            $ledger['seen'][$key] = true;
            $added++;

            if ($this->dry) {
                continue;
            }

            app(CommunicationPointService::class)->create([
                'party_id' => $party->getKey(),
                'channel_type' => $type,
                'value' => $value,
                'is_primary' => $contactId === null && ! isset($ledger['types'][$type]),
                'status' => 'active',
                'contact_relationship_id' => $contactId,
            ]);

            if ($contactId === null) {
                $ledger['types'][$type] = true;
            }

            $this->totals['channels']++;
        }

        return $added;
    }

    /**
     * Ada gore (Turkce kucuk harf, harf disi atilir) eslesmeyen kisiyi acar.
     *
     * @param  list<array{name: string, role: string, network: ?string, channels: list<array{type: string, value: string}>}>  $contacts
     * @param  array{seen: array<string, true>, types: array<string, true>}  $ledger
     * @return array{0: int, 1: int}
     */
    private function addContacts(Party $party, array $contacts, array &$ledger): array
    {
        $existing = [];

        foreach ($party->contacts as $contact) {
            $existing[$this->personKey($contact->displayName())] = $contact;
        }

        $first = $existing === [];
        $added = 0;
        $channels = 0;

        foreach ($contacts as $row) {
            $name = trim((string) $row['name']);
            $key = $this->personKey($name);

            if ($key === '') {
                continue;
            }

            $contact = $existing[$key] ?? null;

            if ($contact === null) {
                $added++;

                if (! $this->dry) {
                    /** @var ContactRelationship $contact */
                    $contact = app(ContactRelationshipService::class)->create([
                        'organization_party_id' => $party->getKey(),
                        'contact_name' => $name,
                        'relationship_role' => (string) ($row['role'] ?? 'other'),
                        'is_primary' => $first,
                        'network_note' => filled($row['network'] ?? null) ? (string) $row['network'] : null,
                    ]);
                    $existing[$key] = $contact;
                    $this->totals['contacts']++;
                }

                $first = false;
            }

            $channels += $this->addChannels($party, $row['channels'] ?? [], $ledger, $contact instanceof ContactRelationship ? (int) $contact->getKey() : null);
        }

        return [$added, $channels];
    }

    /**
     * Tarafta ayni metni iceren not yoksa gorusme notu (arsivdekiler dahil).
     *
     * @param  list<array{on: string, channel: string, subject: ?string, text: string}>  $notes
     * @return list<string> eklenen not metinleri
     */
    private function addNotes(Party $party, array $notes, int $ownerId): array
    {
        $existing = PartyMeetingNote::query()
            ->where('party_id', $party->getKey())
            ->pluck('note')
            ->map(fn (?string $note): string => $this->fingerprint((string) $note))
            ->filter(fn (string $note): bool => $note !== '')
            ->values()
            ->all();
        $added = [];

        foreach ($notes as $row) {
            $text = trim((string) $row['text']);
            $fingerprint = $this->fingerprint($text);
            $on = (string) ($row['on'] ?? '');

            if (mb_strlen($fingerprint) < 4 || self::known($fingerprint, $existing)) {
                continue;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $on) !== 1 || ! checkdate((int) substr($on, 5, 2), (int) substr($on, 8, 2), (int) substr($on, 0, 4))) {
                $this->command?->warn(sprintf('%s: gecersiz gorusme tarihi "%s", not atlandi.', $party->display_name, $on));

                continue;
            }

            $existing[] = $fingerprint;
            $added[] = $on.' · '.$text;

            if ($this->dry) {
                continue;
            }

            app(PartyMeetingNoteService::class)->create([
                'party_id' => $party->getKey(),
                'noted_on' => $on,
                'channel' => $row['channel'],
                'subject' => filled($row['subject'] ?? null) ? (string) $row['subject'] : null,
                'note' => $text,
                'personnel_id' => $ownerId,
            ]);
            $this->totals['notes']++;
        }

        return $added;
    }

    /**
     * Metin zaten kayitli mi: ayni ya da birbirini iceren (en az 12 harf) not
     * (KksListUpdate20261007Seeder ile ayni kural).
     *
     * @param  list<string>  $existing
     */
    public static function known(string $fingerprint, array $existing): bool
    {
        foreach ($existing as $note) {
            if ($note === $fingerprint
                || (mb_strlen($fingerprint) >= 12 && str_contains($note, $fingerprint))
                || (mb_strlen($note) >= 12 && str_contains($fingerprint, $note) && mb_strlen($fingerprint) - mb_strlen($note) < 20)) {
                return true;
            }
        }

        return false;
    }

    public static function ownerId(): ?int
    {
        $id = Personnel::query()->where('email', FirmaTakipBusinessCaseSeeder::OWNER_EMAIL)->value('id');

        return $id === null ? null : (int) $id;
    }

    /** Kisi adi karsilastirmasi: Turkce kucuk harf, yalniz harf. */
    public static function personKey(string $name): string
    {
        $lower = mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $name), 'UTF-8');

        return (string) preg_replace('/[^\p{L}]+/u', '', $lower);
    }

    /** Tekrar karsilastirmasi: Turkce kucuk harf, harf ve rakam disi atilir. */
    private function fingerprint(string $text): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $text), 'UTF-8'));
    }

    /** Uygulamanin normalized_name kurali: kucuk harf, tek bosluk. */
    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->squish()->value();
    }
}
