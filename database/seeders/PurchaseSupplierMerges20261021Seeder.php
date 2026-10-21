<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Party\PartyKind;
use App\Enums\Party\PartyStatus;
use App\Models\Party\Party;
use App\Models\Procurement\PurchaseSupplier;
use App\Models\Scopes\HidePurchaseOnlyPartiesScope;
use App\Services\Audit\ActorContext;
use App\Services\Party\PartyMergeService;
use App\Services\Party\PartyService;
use App\Services\Platform\SchemaReadiness;
use App\Services\Procurement\PurchaseSupplierService;
use App\Services\Support\TransactionRunner;
use App\Support\TurkishFold;
use Database\Seeders\Support\ProtectedSeeder;
use Database\Seeders\Support\SeedGuard;
use Throwable;

/**
 * Tedarikci listesinin ilk aktarimini duzeltir (D-232 ek; kullanici 21 Ekim
 * 2026: "Taraflarda var mı diye bir sekme yapmışsın ben birleştir demiştim
 * neden birleştirmedin ? Birleştir. Hepsi aynı firma belli oluyor. Sadece şu
 * olabilir kısa isim uzun isim şeklinde veriyi yaz." ve "ekstra eklicez hizmet
 * alanını var olan faaliyet hizmet alanlarını kaldırmak yok var olan yapıyı
 * bozmak yok").
 *
 * PurchaseSuppliers20261021Seeder'in ilk hali her firmayi yalniz tedarikci
 * olarak yukledi. Bu seeder o veritabaninda:
 *
 * - Ayni firmanin ayri yuklenen iki kaydini (SAME) birlestirir: kaynagin
 *   hizmet alanlari hedefe eklenir, kaynagin tedarikci kaydi arsive alinir,
 *   taraf PartyMergeService ile hedefe birlesir.
 * - Taraflar'da da olan 14 firmanin yalniz tedarikci kaydini Taraflar'daki
 *   firmaya birlestirir: Taraflar kaydi kalir (faaliyet alanlari, roller,
 *   kisiler, notlar degismez); tedarikci kaydi ve hizmet alanlari ona gecer,
 *   firma iki listede de gorunur.
 * - Kisa / uzun adi ve "Excel'de ayrıca" notunu yazar; ad yalniz hala ilk
 *   aktarimdaki gibiyse (kisa ad bos), not yalniz bossa ya da ilk aktarimin
 *   notuyla basliyorsa degisir.
 *
 * Aktarimin yeni hali bunlari zaten dogru yazdigi icin ilk hali calismamis
 * veritabaninda bu seeder hicbir sey degistirmez. Var olan kayitlara yazdigi
 * yerler kullanicinin istedigi birlestirme ve ad yazimidir
 * (SeedGuard::allowingUpdates); hicbir kayit silinmez.
 */
final class PurchaseSupplierMerges20261021Seeder extends ProtectedSeeder
{
    /** Ilk aktarimda ayri yuklenen ayni firmalar: kaynak ad => hedef ad (kullanici 21 Ekim 2026). */
    private const SAME = [
        'GÖKAĞAÇ ELEKTRONİK' => 'GÖKAĞAÇ ELEKTRİK',
        'ELİT' => 'ELİT ENERJİ',
        'BEKEL ELEKTRİK RAYCHEM' => 'BEKEL ENERJİ',
        'FORM ELEKTRİK PANO' => 'FORM ELEKTRONİK',
        'VAROL HIRDAVAT' => 'VAROL ELEKTRİK',
        'ANKA ELEKTRİK PANO' => 'ANKA MÜHENDİSLİK',
        'ÇÖZÜM HIRDAVAT' => 'ÇÖZÜM TEKNİK',
    ];

    private const REASON = 'Tedarikçi listesinde aynı firma (D-232)';

    /** @var array{same: int, joined: int, renamed: int, notes: int} */
    private array $totals = ['same' => 0, 'joined' => 0, 'renamed' => 0, 'notes' => 0];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B58')) {
            $this->command?->warn('B58 uygulanmamis; tedarikci birlestirmeleri sonraki kuruluma kaldi.');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        foreach (self::SAME as $source => $target) {
            $this->row('same:'.TurkishFold::fold($source), fn (): mixed => $this->safely($source, fn (): mixed => $this->mergeSame($source, $target)));
        }

        foreach (PurchaseSuppliers20261021Seeder::data()['suppliers'] as $firm) {
            if ($firm['party'] !== null) {
                $this->row('join:'.TurkishFold::fold($firm['key']), fn (): mixed => $this->safely($firm['key'], fn (): mixed => $this->join($firm)));

                continue;
            }

            $this->row('names:'.TurkishFold::fold($firm['key']), fn (): mixed => $this->safely($firm['key'], fn (): mixed => $this->names($firm)));
        }

        $this->command?->info(sprintf(
            'Tedarikci duzeltmesi 21.10: %d ayni firma birlesti, %d tedarikci Taraflar firmasina birlesti, %d kisa / uzun ad, %d not yazildi.',
            $this->totals['same'], $this->totals['joined'], $this->totals['renamed'], $this->totals['notes'],
        ));
    }

    /**
     * Yazmadan: ilk aktarimin kayitlarindan hangileri birlesecek.
     *
     * @return array{same: list<string>, joins: list<string>}
     */
    public function preview(): array
    {
        $out = ['same' => [], 'joins' => []];

        if (! SchemaReadiness::hasBatch('B58')) {
            return $out;
        }

        foreach (self::SAME as $source => $target) {
            $from = $this->supplier($source, true);
            $to = $this->supplier($target);

            if ($from instanceof Party && $to instanceof Party) {
                $out['same'][] = sprintf('#%d %s -> #%d %s', $from->getKey(), $from->display_name, $to->getKey(), $to->display_name);
            }
        }

        foreach (PurchaseSuppliers20261021Seeder::data()['suppliers'] as $firm) {
            $from = $firm['party'] !== null ? $this->supplier($firm['key'], true) : null;
            $to = $from instanceof Party ? $this->taraflar($firm['party']['match']) : null;

            if ($from instanceof Party && $to instanceof Party && (int) $from->getKey() !== (int) $to->getKey()) {
                $out['joins'][] = sprintf('#%d %s -> #%d %s', $from->getKey(), $from->display_name, $to->getKey(), $to->display_name);
            }
        }

        return $out;
    }

    /** Ilk aktarimda ayri yuklenen ayni firmanin kaydi hedefe birlesir. */
    private function mergeSame(string $sourceName, string $targetName): mixed
    {
        $source = $this->supplier($sourceName, true);

        if (! $source instanceof Party) {
            return true;
        }

        $target = $this->supplier($targetName);

        if (! $target instanceof Party) {
            $this->command?->warn(sprintf('"%s" tedarikci listesinde yok; "%s" birlestirilmedi.', $targetName, $sourceName));

            return null;
        }

        $this->mergeInto($source, $target);
        $this->totals['same']++;

        return $target;
    }

    /**
     * Taraflar'da da olan firma: ilk aktarimin yalniz tedarikci kaydi Taraflar
     * kaydina birlesir, ad ve not yazilir.
     *
     * @param  array{key: string, long: string, short: string|null, areas: list<string>, note: string|null, party: array{match: string, long: string, short: string|null}}  $firm
     */
    private function join(array $firm): mixed
    {
        $source = $this->supplier($firm['key'], true);

        if (! $source instanceof Party) {
            return true;
        }

        $target = $this->taraflar($firm['party']['match']);

        if (! $target instanceof Party) {
            $this->command?->warn(sprintf('"%s" Taraflar\'da tek kayitla bulunamadi; "%s" yalniz tedarikci kaldi.', $firm['party']['match'], $firm['key']));

            return true;
        }

        if ((int) $source->getKey() === (int) $target->getKey()) {
            return true;
        }

        $this->mergeInto($source, $target);
        $this->totals['joined']++;

        $target = $this->fresh($target);

        if (PurchaseSuppliers20261021Seeder::renameable($target, $firm['party'])) {
            $this->rename($target, $firm['party']['long'], $firm['party']['short']);
        }

        $this->note($target, $firm['note']);

        return $target;
    }

    /**
     * Yalniz tedarikci olan firmanin kisa / uzun adi ve notu.
     *
     * @param  array{key: string, long: string, short: string|null, note: string|null}  $firm
     */
    private function names(array $firm): mixed
    {
        $party = $this->supplier($firm['key']) ?? $this->supplier($firm['short'] ?? $firm['long']);

        if (! $party instanceof Party) {
            return true;
        }

        $profile = $party->organizationProfile;
        $known = array_map(static fn (?string $name): string => TurkishFold::fold((string) $name), [$firm['key'], $firm['long'], $firm['short']]);

        if ($profile !== null
            && blank($profile->trade_name)
            && in_array(TurkishFold::fold((string) $profile->legal_name), $known, true)
            && ($profile->legal_name !== $firm['long'] || $firm['short'] !== null)) {
            $this->rename($party, $firm['long'], $firm['short']);
        }

        $this->note($party, $firm['note']);

        return $party;
    }

    /**
     * Hedefin tedarikci kaydi yoksa acilir (Taraflar firmasi: attachExisting,
     * notuyla); kaynagin hizmet alanlari hedefe eklenir, kaynagin tedarikci
     * kaydi arsive alinir ve kaynakta kalir (birlesen kaynak Taraflar'da da
     * gorunmez), sonra taraf hedefe birlesir.
     */
    private function mergeInto(Party $source, Party $target): void
    {
        SeedGuard::allowingUpdates(function () use ($source, $target): void {
            app(TransactionRunner::class)->run(function () use ($source, $target): void {
                $from = PurchaseSupplier::query()->where('party_id', $source->getKey())->first();

                if ($from instanceof PurchaseSupplier) {
                    $areas = $from->serviceAreas()->pluck('purchase_service_areas.id')->all();
                    $to = PurchaseSupplier::query()->where('party_id', $target->getKey())->first();

                    if ($to instanceof PurchaseSupplier) {
                        $to->serviceAreas()->syncWithoutDetaching($areas);
                    } else {
                        app(PurchaseSupplierService::class)->attachExisting($target, [
                            'note' => $from->getAttribute('note'),
                            'service_area_ids' => $areas,
                        ]);
                    }

                    if (! $from->isArchived()) {
                        app(PurchaseSupplierService::class)->archive($from);
                    }
                }

                app(PartyMergeService::class)->merge($source, $target, self::REASON);
            });
        });
    }

    private function rename(Party $party, string $long, ?string $short): void
    {
        SeedGuard::allowingUpdates(static fn (): mixed => app(PartyService::class)->update($party, [
            'organization_profile' => ['legal_name' => $long, 'trade_name' => $short],
        ]));
        $this->totals['renamed']++;
    }

    /** Not yalniz bossa ya da yeni not eskisiyle basliyorsa (ek bilgi) yazilir. */
    private function note(Party $party, ?string $note): void
    {
        $membership = PurchaseSupplier::query()->where('party_id', $party->getKey())->first();
        $current = trim((string) $membership?->getAttribute('note'));

        if (! $membership instanceof PurchaseSupplier || blank($note) || $current === $note || ($current !== '' && ! str_starts_with((string) $note, $current))) {
            return;
        }

        SeedGuard::allowingUpdates(static fn (): mixed => $membership->forceFill(['note' => $note])->save());
        $this->totals['notes']++;
    }

    /** Tedarikci listesindeki (birlesmemis) firma; gorunen ad katlanmis haliyle. */
    private function supplier(string $name, bool $purchaseOnly = false): ?Party
    {
        return Party::query()
            ->withoutGlobalScope(HidePurchaseOnlyPartiesScope::class)
            ->with('organizationProfile')
            ->where('status', '!=', PartyStatus::Merged->value)
            ->whereHas('purchaseSupplier', static fn ($query) => $purchaseOnly ? $query->where('purchase_only', true) : $query)
            ->get()
            ->first(static fn (Party $party): bool => TurkishFold::fold((string) $party->display_name) === TurkishFold::fold($name));
    }

    /** Taraflar'da gorunen tek kurulus (gorunen, uzun ya da kisa ad); birden fazlaysa null. */
    private function taraflar(string $name): ?Party
    {
        $key = TurkishFold::fold($name);
        $matches = Party::query()
            ->with('organizationProfile')
            ->where('party_kind', PartyKind::Organization->value)
            ->where('status', '!=', PartyStatus::Merged->value)
            ->get()
            ->filter(static fn (Party $party): bool => in_array($key, array_map(
                static fn (?string $value): string => TurkishFold::fold((string) $value),
                [$party->display_name, $party->organizationProfile?->legal_name, $party->organizationProfile?->trade_name],
            ), true));

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function fresh(Party $party): Party
    {
        /** @var Party $fresh */
        $fresh = Party::query()->withoutGlobalScope(HidePurchaseOnlyPartiesScope::class)->with('organizationProfile')->findOrFail($party->getKey());

        return $fresh;
    }

    /** Hata satiri durdurmaz; satir arsive dusmez, sonraki kurulumda yeniden denenir. */
    private function safely(string $label, \Closure $write): mixed
    {
        try {
            return $write();
        } catch (Throwable $exception) {
            $this->command?->warn(sprintf('Tedarikci duzeltmesi "%s" yazilamadi (sonra yeniden denenecek): %s', $label, $exception->getMessage()));

            return null;
        }
    }
}
