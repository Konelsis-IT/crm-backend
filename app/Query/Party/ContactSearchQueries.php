<?php

declare(strict_types=1);

namespace App\Query\Party;

use App\Models\Party\ContactRelationship;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Genel aramada kisiler (D-167, 6 Ekim 2026 kullanici istegi: "Genel aramada
 * gorusulen kisi, taraf detayindaki kisi bilgisi vs. genel aramada cikmadi,
 * onlar da ciksin"). Taraflarin "Iletisim ve kisiler" satirlari aranir:
 * kisi adi, bagli kisi kaydinin adi, kisinin telefon / e-posta degerleri.
 * Gorusme notu ve plani "gorusulen kisi"yi ayni satirdan secer; bu yuzden
 * gorusulen kisiler de buradan bulunur, son gorusme tarihiyle birlikte.
 *
 * Arsivdeki taraflarin kisileri gelmez (D-156); arsivdeki notlar son
 * gorusme tarihine katilmaz.
 */
final class ContactSearchQueries
{
    public const LIMIT = 20;

    /** Aranan ifadeden en fazla bu kadar kelime kullanilir. */
    private const MAX_WORDS = 5;

    /**
     * Her kelime kisinin adinda, bagli kisi kaydinin adinda ya da kisinin bir
     * iletisim degerinde gecmelidir. Sonuclarda kurum, bagli kisi ve kanallar
     * yuklu gelir; B28 varsa `last_noted_on` son gorusme tarihidir.
     *
     * @return Collection<int, ContactRelationship>
     */
    public function search(string $term, int $limit = self::LIMIT): Collection
    {
        $words = $this->words($term);

        // Kisi adi ve kisiye ait kanallar B27 ile geldi.
        if ($words === [] || ! SchemaReadiness::hasBatch('B27')) {
            return new Collection;
        }

        $query = ContactRelationship::query()
            ->with(['organization', 'contact', 'communicationPoints'])
            ->whereHas('organization', fn (Builder $party): Builder => $party->whereNull('archived_at'));

        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '\\%_').'%';

            $query->where(fn (Builder $inner): Builder => $inner
                ->where('contact_name', 'like', $like)
                ->orWhereHas('contact', fn (Builder $person): Builder => $person->where('display_name', 'like', $like))
                ->orWhereHas('communicationPoints', fn (Builder $points): Builder => $points
                    ->where('value', 'like', $like)
                    ->orWhere('normalized_value', 'like', $like)));
        }

        if (SchemaReadiness::hasBatch('B28')) {
            $query->withMax(['meetingNotes as last_noted_on' => fn (Builder $notes): Builder => $notes->notArchived()], 'noted_on');
        }

        return $query
            ->orderBy('contact_name')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return list<string>
     */
    private function words(string $term): array
    {
        $parts = preg_split('/\s+/u', trim($term)) ?: [];

        return array_slice(array_values(array_filter($parts, static fn (string $part): bool => $part !== '')), 0, self::MAX_WORDS);
    }
}
