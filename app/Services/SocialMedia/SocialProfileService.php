<?php

declare(strict_types=1);

namespace App\Services\SocialMedia;

use App\Enums\Shared\ActiveStatus;
use App\Enums\SocialMedia\SocialPlatform;
use App\Enums\SocialMedia\SocialProfileKind;
use App\Models\SocialMedia\SocialProfile;
use App\Models\SocialMedia\SocialProfileLink;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Kendi sosyal medya hesaplarimizin servisi (B31, D-106).
 *
 * Hesaplar SocialProfileSeeder ile gelir; arayuzden yalniz tanitim yazisi
 * (`bio`) ve platform baglantilari duzenlenir. Kod, ad, tur, sahip ve durum
 * bu servisle DEGISTIRILMEZ: update() onlari veriden ayiklar.
 *
 * `links` anahtari baglantilari TAM liste olarak tasir (E6): listede olmayan
 * platformun satiri kaldirilir, olan guncellenir, yeni gelen eklenir. Anahtar
 * hic gonderilmezse baglantilara dokunulmaz. Baglanti degisiklikleri hesabin
 * hareket kaydina `platformlar` ozeti olarak yazilir (adres yazilmaz).
 */
final class SocialProfileService extends AbstractService
{
    protected string $model = SocialProfile::class;

    protected string $orderBy = 'sort_order';

    /** @var list<string> */
    protected array $with = ['links', 'owner'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        $links = array_key_exists('links', $data) ? $this->normalizeLinks($data['links'] ?? []) : null;

        return $this->transactions->run(function () use ($record, $data, $links): Model {
            /** @var SocialProfile $profile */
            $profile = $this->lockForUpdate($record);
            $changes = [];

            if (array_key_exists('bio', $data)) {
                $bio = trim((string) ($data['bio'] ?? ''));
                $profile->fill(['bio' => $bio === '' ? null : $bio]);

                // Harekete yazinin tamami degil kisaltilmis hali yazilir.
                foreach ($this->saveWithoutVersion($profile) as $column => $pair) {
                    $changes[$column] = [
                        'onceki' => $this->excerpt($pair['onceki'] ?? null),
                        'yeni' => $this->excerpt($pair['yeni'] ?? null),
                    ];
                }
            }

            if ($links !== null) {
                $linkChanges = $this->syncLinks($profile, $links);

                if ($linkChanges !== []) {
                    $changes['platformlar'] = $linkChanges;
                }
            }

            if ($changes !== []) {
                $this->recordActivity($profile, 'updated', ['name' => $profile->name] + $changes);
            }

            return $profile->load(['links', 'owner.orgUnit']);
        });
    }

    /**
     * Hesap acilisi (SocialProfileSeeder) icin hareket ozeti: kod ve ham enum
     * degeri yerine ad ve etiketler yazilir.
     *
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        $kind = $record->getAttribute('kind');
        $status = $record->getAttribute('status');

        return array_filter([
            'name' => $record->getAttribute('name'),
            'kind' => $kind instanceof SocialProfileKind ? $kind->getLabel() : null,
            'status' => $status instanceof ActiveStatus ? $status->getLabel() : null,
        ], fn ($value): bool => $value !== null);
    }

    private function excerpt(mixed $value): ?string
    {
        return $value === null ? null : Str::limit(Str::squish((string) $value), 120);
    }

    /**
     * Baglanti listesini platform => [adres, kullanici adi] haritasina indirger:
     * taninmayan platform ve bos adres atilir; ayni platform iki kez gelirse
     * sonuncusu kalir. Kullanici adinin basindaki @ isareti saklanmaz.
     *
     * @return array<string, array{url: string, handle: ?string}>
     */
    private function normalizeLinks(mixed $links): array
    {
        $clean = [];

        foreach (is_array($links) ? $links : [] as $link) {
            if (! is_array($link)) {
                continue;
            }

            $platform = SocialPlatform::tryFrom((string) ($link['platform'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));

            if ($platform === null || $url === '') {
                continue;
            }

            $handle = ltrim(trim((string) ($link['handle'] ?? '')), '@');

            $clean[$platform->value] = [
                'url' => mb_substr($url, 0, 500),
                'handle' => $handle === '' ? null : mb_substr($handle, 0, 120),
            ];
        }

        return $clean;
    }

    /**
     * Baglantilari verilen tam listeye esitler; degisiklik ozetini doner.
     *
     * @param  array<string, array{url: string, handle: ?string}>  $links
     * @return array<string, string>
     */
    private function syncLinks(SocialProfile $profile, array $links): array
    {
        $added = [];
        $updated = [];
        $removed = [];

        /** @var array<string, SocialProfileLink> $existing */
        $existing = [];

        foreach ($profile->links()->lockForUpdate()->get() as $row) {
            $existing[$row->platform instanceof SocialPlatform ? $row->platform->value : (string) $row->platform] = $row;
        }

        foreach ($links as $platform => $link) {
            $row = $existing[$platform] ?? null;

            if ($row === null) {
                $profile->links()->create(['platform' => $platform, 'url' => $link['url'], 'handle' => $link['handle']]);
                $added[] = $platform;

                continue;
            }

            $row->fill(['url' => $link['url'], 'handle' => $link['handle']]);

            if ($row->isDirty()) {
                $row->save();
                $updated[] = $platform;
            }
        }

        foreach ($existing as $platform => $row) {
            if (! array_key_exists($platform, $links)) {
                $row->delete();
                $removed[] = $platform;
            }
        }

        return array_filter([
            'eklenen' => $this->platformLabels($added),
            'guncellenen' => $this->platformLabels($updated),
            'cikarilan' => $this->platformLabels($removed),
        ], fn (string $labels): bool => $labels !== '');
    }

    /**
     * Platform etiketleri (sabit sirada, virgulle). Hareket ozetinde okunur
     * metin olsun diye dizi degil metin doner.
     *
     * @param  list<string>  $platforms
     */
    private function platformLabels(array $platforms): string
    {
        $labels = [];

        foreach (SocialPlatform::cases() as $platform) {
            if (in_array($platform->value, $platforms, true)) {
                $labels[] = (string) $platform->getLabel();
            }
        }

        return implode(', ', $labels);
    }
}
