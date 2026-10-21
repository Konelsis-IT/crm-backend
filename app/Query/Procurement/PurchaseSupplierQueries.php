<?php

declare(strict_types=1);

namespace App\Query\Procurement;

use App\Enums\Party\PartyStatus;
use App\Enums\Platform\Feature;
use App\Models\Party\Party;
use App\Models\Procurement\PurchaseServiceArea;
use App\Models\Scopes\HidePurchaseOnlyPartiesScope;
use App\Query\Party\PartyQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Support\TurkishFold;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satin alma tedarikci listesinin okumalari (D-232, B58). Tedarikci bir taraf
 * kaydidir; yalniz tedarikci olanlar Taraflar tarafinda gizlidir
 * (HidePurchaseOnlyPartiesScope), burada gizli kapsam asilir.
 */
final class PurchaseSupplierQueries
{
    /**
     * Ad karsilastirmasinda atilan kelimeler: hukuki ekler ve sektor sozcukleri
     * (belgeden tedarikci bulma, partyIdByTaxOrName).
     */
    private const IGNORED_WORDS = [
        'a', 's', 'as', 'ltd', 'sti', 'san', 'tic', 've', 'sanayi', 'ticaret', 'limited', 'sirketi', 'anonim', 'ins', 'ith', 'ihr', 'muh',
        'elektrik', 'elektronik', 'enerji', 'trafo', 'solar', 'kablo', 'muhendislik', 'otomasyon', 'lojistik', 'nakliyat', 'nakliye',
        'pano', 'jenerator', 'hirdavat', 'civata', 'boru', 'makina', 'metal', 'sistem', 'sistemleri', 'teknik', 'teknoloji', 'teknolojileri',
    ];

    public function __construct(private readonly PartyQueries $parties) {}

    /** Tedarikci listesi kurulu ve acik mi (B58 + ozellik)? */
    public static function enabled(): bool
    {
        return SchemaReadiness::hasBatch('B58') && FeatureFlags::enabled(Feature::PurchaseSuppliers);
    }

    /**
     * Tedarikci listesindeki firmalar (gizli kapsam asilir); arsiv suzgeci
     * ekranda. Baska firmaya birlestirilen kayit listede yoktur.
     */
    public function base(): Builder
    {
        return Party::query()
            ->withoutGlobalScope(HidePurchaseOnlyPartiesScope::class)
            ->where('parties.status', '<>', PartyStatus::Merged->value)
            ->whereHas('purchaseSupplier');
    }

    public function withListRelations(Builder $query): Builder
    {
        return $query->with(['organizationProfile', 'purchaseSupplier.serviceAreas'])
            ->withCount('contacts');
    }

    /** Arsiv suzgeci tedarikci kaydinda: active | archived | all. */
    public function archiveScope(Builder $query, string $mode): Builder
    {
        return match ($mode) {
            'archived' => $query->whereHas('purchaseSupplier', fn (Builder $supplier): Builder => $supplier->whereNotNull('archived_at')),
            'all' => $query,
            default => $query->whereHas('purchaseSupplier', fn (Builder $supplier): Builder => $supplier->whereNull('archived_at')),
        };
    }

    /** @param  list<int|string>  $areaIds */
    public function inAreas(Builder $query, array $areaIds): Builder
    {
        $ids = array_values(array_filter(array_map('intval', $areaIds)));

        return $ids === [] ? $query : $query->whereHas('purchaseSupplier.serviceAreas', fn (Builder $areas): Builder => $areas->whereIn('purchase_service_areas.id', $ids));
    }

    /** "Taraflarda da" (ayni firma onayli) ya da yalniz tedarikci. */
    public function shared(Builder $query, bool $shared): Builder
    {
        return $query->whereHas('purchaseSupplier', fn (Builder $supplier): Builder => $supplier->where('purchase_only', ! $shared));
    }

    /**
     * Satin alma teklifi / siparis tedarikci secimi (kullanici 21 Ekim 2026:
     * tedarikci "yeni tedarikci listesinden" secilir): arsivde olmayanlar.
     *
     * @return array<int, string>
     */
    public function options(string $search = '', int $limit = 50): array
    {
        $query = $this->base()
            ->whereNull('parties.archived_at')
            ->whereHas('purchaseSupplier', fn (Builder $supplier): Builder => $supplier->whereNull('archived_at'));

        if (trim($search) !== '') {
            $this->parties->searchByName($query, $search);
        }

        return $query->orderBy('display_name')
            ->limit($limit)
            ->get(['parties.id', 'parties.display_name'])
            ->mapWithKeys(static fn (Party $party): array => [(int) $party->getKey() => (string) $party->getAttribute('display_name')])
            ->all();
    }

    /** Gizli kapsamdaki firmanin da adi (secim etiketi). */
    public function label(mixed $id): ?string
    {
        if (blank($id)) {
            return null;
        }

        $name = Party::query()->withoutGlobalScope(HidePurchaseOnlyPartiesScope::class)->whereKey((int) $id)->value('display_name');

        return $name === null ? null : (string) $name;
    }

    /**
     * Konelsis AI teklif okuma (D-192): tedarikci listesinde once vergi numarasi,
     * sonra ayni ad (kisa / uzun ad dahil).
     */
    public function partyIdByTaxOrName(?string $taxNumber, ?string $name): ?int
    {
        $digits = preg_replace('/\D+/', '', (string) $taxNumber) ?? '';
        $query = fn (): Builder => $this->base()
            ->whereNull('parties.archived_at');

        if (strlen($digits) >= 10) {
            $id = $query()->whereHas('organizationProfile', fn (Builder $profile): Builder => $profile
                ->whereRaw("REPLACE(REPLACE(REPLACE(tax_number, ' ', ''), '-', ''), '.', '') = ?", [$digits]))
                ->orderBy('parties.id')
                ->value('parties.id');

            if ($id !== null) {
                return (int) $id;
            }
        }

        $key = self::nameKey((string) $name);

        if ($key === '') {
            return null;
        }

        foreach ($query()->with('organizationProfile')->get(['parties.id', 'parties.display_name']) as $party) {
            foreach ($this->names($party) as $candidate) {
                if (self::nameKey($candidate) === $key) {
                    return (int) $party->getKey();
                }
            }
        }

        return null;
    }

    /** @return array<int, string> Hizmet alanlari (arsivde olmayanlar), siraya gore. */
    public function areaOptions(): array
    {
        return PurchaseServiceArea::query()
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /** Karsilastirma anahtari: katlanmis, parantez / noktalama / ek ve sektor sozcukleri atilmis ad. */
    public static function nameKey(string $name): string
    {
        $text = TurkishFold::fold($name);
        $text = preg_replace('/\(.*?\)/u', ' ', $text) ?? $text;
        $text = preg_replace('/[^a-z0-9]+/u', ' ', $text) ?? $text;
        $words = array_values(array_filter(
            explode(' ', $text),
            static fn (string $word): bool => $word !== '' && ! in_array($word, self::IGNORED_WORDS, true),
        ));

        return implode(' ', $words);
    }

    /** @return list<string> Firmanin adlari (gorunen, kisa, uzun). */
    private function names(Party $party): array
    {
        $profile = $party->organizationProfile;

        return array_values(array_unique(array_filter([
            (string) $party->getAttribute('display_name'),
            $profile !== null ? (string) $profile->getAttribute('trade_name') : '',
            $profile !== null ? (string) $profile->getAttribute('legal_name') : '',
        ], static fn (string $name): bool => trim($name) !== '')));
    }
}
