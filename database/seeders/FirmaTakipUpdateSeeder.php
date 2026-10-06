<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Party\Address;
use App\Models\Party\CommunicationPoint;
use App\Models\Party\Party;
use App\Services\Party\AddressService;
use App\Services\Party\CommunicationPointService;
use App\Services\Party\PartyService;
use App\Services\Platform\SchemaReadiness;
use Database\Seeders\Support\ProtectedSeeder;
use Illuminate\Support\Str;

/**
 * Firma takip listesi 21 Eylul 2026 surumunun var olan kayitlardaki
 * duzeltmeleri (kullanici onayi, 21 Eylul 2026). RealPartySeeder yalniz
 * eksikleri ekler; degisen ve silinen bilgiler burada uygulanir:
 *
 *  - Ahlat Enerji: merkez Egesa'nin adresine tasindi; ofis adresi guncellenir.
 *  - Cengiz Enerji: listede yanlislikla duran web sitesi (www.bienseramik.com.tr)
 *    silinir.
 *  - Egesa: EPC firmasi, rakip firma olarak isaretlenir (B33).
 *
 * Sonunda MeetingNotePersonnelSeeder personeli bos yeni notlara Ersin Ozdemir
 * yazar (21 Eylul 2026 kullanici talimati). Idempotent; RealPartySeeder'in
 * sonunda calisir, yeni kurulumda da zarari yoktur.
 *
 * Duzeltme seeder'i; uygulandi. D-165 ile var olan veriyi degistiremez,
 * yeniden calistirilinca seed korumasi durdurur. Her duzeltme sabit
 * anahtarla (fix:ahlat-address, fix:cengiz-enerji-wrong-website,
 * fix:egesa-competitor) bir kez islenir; zaten uygulanmissa yalniz arsive
 * duser, firma yoksa arsive dusmez.
 */
class FirmaTakipUpdateSeeder extends ProtectedSeeder
{
    private const AHLAT = 'AHLAT ENERJİ ÜRETİM ANONİM ŞİRKETİ';

    private const EGESA = 'EGESA ELEKTRİK İNŞAAT ENERJİ ÜRETİM ANONİM ŞİRKETİ';

    private const CENGIZ_ENERJI = 'CENGİZ ENERJİ SAN. VE TİC. A.Ş.';

    private const WRONG_WEBSITE = 'www.bienseramik.com.tr';

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B16')) {
            return;
        }

        $this->row('fix:ahlat-address', fn (): ?bool => $this->moveAhlatAddress());
        $this->row('fix:cengiz-enerji-wrong-website', fn (): ?bool => $this->dropWrongWebsite());

        if (SchemaReadiness::hasBatch('B33')) {
            $this->row('fix:egesa-competitor', fn (): ?bool => $this->markEgesaCompetitor());
        }

        if (SchemaReadiness::hasBatch('B28')) {
            $this->call(MeetingNotePersonnelSeeder::class);
        }
    }

    /** true: islendi (ya da zaten uygulanmis); null: firma/adres yok, sonra yeniden denenir. */
    private function moveAhlatAddress(): ?bool
    {
        $ahlat = $this->find(self::AHLAT);
        /** @var Address|null $target */
        $target = $this->find(self::EGESA)?->addresses()->orderByDesc('is_primary')->first();
        /** @var Address|null $current */
        $current = $ahlat?->addresses()->orderByDesc('is_primary')->first();

        if ($current === null || $target === null) {
            return null;
        }

        if ($current->line1 === $target->line1) {
            return true;
        }

        app(AddressService::class)->update($current, [
            'line1' => $target->line1,
            'district' => $target->district,
            'city' => $target->city,
        ]);
        $this->command?->info('Ahlat Enerji: adres Egesa\'nin adresiyle guncellendi.');

        return true;
    }

    private function dropWrongWebsite(): ?bool
    {
        $party = $this->find(self::CENGIZ_ENERJI);

        if ($party === null) {
            return null;
        }

        $points = $party->communicationPoints()
            ->where('channel_type', 'website')
            ->get()
            ->filter(fn (CommunicationPoint $point): bool => $this->normalize((string) $point->value) === self::WRONG_WEBSITE);

        foreach ($points as $point) {
            app(CommunicationPointService::class)->delete($point);
            $this->command?->info('Cengiz Enerji: yanlis web sitesi silindi.');
        }

        return true;
    }

    private function markEgesaCompetitor(): ?bool
    {
        $party = $this->find(self::EGESA);

        if ($party === null) {
            return null;
        }

        if ($party->is_competitor) {
            return true;
        }

        app(PartyService::class)->update($party, ['is_competitor' => true]);
        $this->command?->info('Egesa: rakip firma olarak isaretlendi.');

        return true;
    }

    private function find(string $name): ?Party
    {
        /** @var Party|null $party */
        $party = Party::query()
            ->where('party_kind', 'organization')
            ->where('normalized_name', $this->normalize($name))
            ->first();

        return $party;
    }

    /** Uygulamanin normalized_name kurali: kucuk harf, tek bosluk. */
    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->squish()->value();
    }
}
