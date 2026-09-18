<?php

declare(strict_types=1);

namespace App\Services\SocialMedia;

use App\Enums\Shared\ActiveStatus;
use App\Enums\SocialMedia\SocialPlatform;
use App\Enums\SocialMedia\SocialWatchKind;
use App\Models\SocialMedia\SocialWatchAccount;
use App\Models\SocialMedia\SocialWatchLink;
use App\Services\AbstractService;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Izlenen hesap servisi (B31, D-106): rakip firma, rakip yonetici, resmi kurum.
 *
 * `links` anahtari hesabin platform baglantilarini TAM liste olarak tasir
 * (E6): listede olmayan platformun baglanti satiri kaldirilir, olan guncellenir,
 * yeni gelen eklenir. `links` anahtari hic gonderilmezse baglantilara dokunulmaz.
 * Baglanti satiri bir kavsak kaydidir; hesabin kendisi silinmez, pasife alinir.
 *
 * Baglanti degisiklikleri hesabin hareket kaydina `platformlar` ozeti olarak
 * yazilir; ayri bir kayit turu acilmaz.
 */
final class SocialWatchAccountService extends AbstractService
{
    protected string $model = SocialWatchAccount::class;

    protected string $orderBy = 'sort_order';

    /** @var list<string> */
    protected array $with = ['links'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $links = $this->normalizeLinks($data['links'] ?? []);
        $data = $this->normalize($data, null);

        return $this->transactions->run(function () use ($data, $links): Model {
            if (! array_key_exists('sort_order', $data)) {
                $data['sort_order'] = $this->nextSortOrder((string) $data['kind']);
            }

            /** @var SocialWatchAccount $account */
            $account = $this->newModel()->newInstance();
            $account->fill($this->prepare($data, null));
            $account->save();

            $this->syncLinks($account, $links);

            $this->recordActivity($account, 'created', array_filter([
                'name' => $account->name,
                'kind' => $account->kind?->getLabel(),
                'subtitle' => $account->subtitle,
                'platformlar' => $this->platformLabels(array_keys($links)),
            ], fn ($value): bool => $value !== null && $value !== ''));

            return $account->load('links');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        $links = array_key_exists('links', $data) ? $this->normalizeLinks($data['links'] ?? []) : null;

        return $this->transactions->run(function () use ($record, $data, $links): Model {
            /** @var SocialWatchAccount $account */
            $account = $this->lockForUpdate($record);

            $account->fill($this->prepare($this->normalize($data, $account), $account));
            $changes = $this->labelled($this->saveWithoutVersion($account));

            if ($links !== null) {
                $linkChanges = $this->syncLinks($account, $links);

                if ($linkChanges !== []) {
                    $changes['platformlar'] = $linkChanges;
                }
            }

            if ($changes !== []) {
                $this->recordActivity($account, 'updated', $changes);
            }

            return $account->load('links');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        unset($data['row_version'], $data['links']);

        return $data;
    }

    /**
     * Gelen veriyi kolonlara indirger. Guncellemede gonderilmeyen alan oldugu
     * gibi kalir; `subtitle` ve `note` bos gonderilirse temizlenir.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, ?SocialWatchAccount $record): array
    {
        $clean = [];

        if (filled($data['kind'] ?? null)) {
            $kind = $data['kind'] instanceof SocialWatchKind ? $data['kind'] : SocialWatchKind::tryFrom((string) $data['kind']);

            if ($kind !== null) {
                $clean['kind'] = $kind->value;
            }
        }

        if ($record === null && ! isset($clean['kind'])) {
            $clean['kind'] = SocialWatchKind::CompetitorCompany->value;
        }

        if (array_key_exists('name', $data) || $record === null) {
            $clean['name'] = trim((string) ($data['name'] ?? ''));
        }

        if (array_key_exists('subtitle', $data)) {
            $subtitle = trim((string) ($data['subtitle'] ?? ''));
            $clean['subtitle'] = $subtitle === '' ? null : mb_substr($subtitle, 0, 160);
        }

        if (array_key_exists('note', $data)) {
            $note = trim((string) ($data['note'] ?? ''));
            $clean['note'] = $note === '' ? null : $note;
        }

        if (filled($data['status'] ?? null)) {
            $status = $data['status'] instanceof ActiveStatus ? $data['status'] : ActiveStatus::tryFrom((string) $data['status']);

            if ($status !== null) {
                $clean['status'] = $status->value;
            }
        } elseif ($record === null) {
            $clean['status'] = ActiveStatus::Active->value;
        }

        if (filled($data['sort_order'] ?? null)) {
            $clean['sort_order'] = max(0, min(65535, (int) $data['sort_order']));
        }

        return $clean;
    }

    /**
     * Baglanti listesini platform => adres haritasina indirger: taninmayan
     * platform ve bos adres atilir; ayni platform iki kez gelirse sonuncusu kalir.
     *
     * @return array<string, string>
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

            $clean[$platform->value] = mb_substr($url, 0, 500);
        }

        return $clean;
    }

    /**
     * Baglantilari verilen tam listeye esitler; degisiklik ozetini doner
     * (etiketlerle; adres yazilmaz).
     *
     * @param  array<string, string>  $links  platform => adres
     * @return array<string, string>
     */
    private function syncLinks(SocialWatchAccount $account, array $links): array
    {
        $added = [];
        $updated = [];
        $removed = [];

        /** @var array<string, SocialWatchLink> $existing */
        $existing = [];

        foreach ($account->links()->lockForUpdate()->get() as $row) {
            $existing[$row->platform instanceof SocialPlatform ? $row->platform->value : (string) $row->platform] = $row;
        }

        foreach ($links as $platform => $url) {
            $row = $existing[$platform] ?? null;

            if ($row === null) {
                $account->links()->create(['platform' => $platform, 'url' => $url]);
                $added[] = $platform;

                continue;
            }

            if ((string) $row->url !== $url) {
                $row->fill(['url' => $url])->save();
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

    /** Yeni hesap kendi turunun sonuna eklenir. */
    private function nextSortOrder(string $kind): int
    {
        return min(65535, ((int) SocialWatchAccount::query()->where('kind', $kind)->max('sort_order')) + 1);
    }

    /**
     * @param  array<string, array{onceki: mixed, yeni: mixed}>  $changes
     * @return array<string, mixed>
     */
    private function labelled(array $changes): array
    {
        foreach ($changes as $column => $pair) {
            $changes[$column] = [
                'onceki' => $this->display($pair['onceki'] ?? null),
                'yeni' => $this->display($pair['yeni'] ?? null),
            ];
        }

        return $changes;
    }

    /** Hareket kaydinda enum degeri yerine etiketi yazilir. */
    private function display(mixed $value): mixed
    {
        if ($value instanceof ActiveStatus || $value instanceof SocialWatchKind) {
            return $value->getLabel();
        }

        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
