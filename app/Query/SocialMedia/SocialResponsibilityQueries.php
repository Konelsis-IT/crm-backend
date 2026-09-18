<?php

declare(strict_types=1);

namespace App\Query\SocialMedia;

use App\Enums\Personnel\PersonnelStatus;
use App\Enums\Personnel\PositionStatus;
use App\Enums\Shared\ActiveStatus;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\Position;
use App\Models\Personnel\PositionAssignment;
use App\Models\SocialMedia\SocialContent;
use App\Models\SocialMedia\SocialResponsiblePosition;
use App\Policies\SocialContentPolicy;
use App\Services\Audit\ActorContext;
use App\Services\Authorization\PermissionKey;
use App\Services\Authorization\RoleResolver;
use App\Services\Platform\SchemaReadiness;
use App\Services\SocialMedia\SocialClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Sosyal medya sorumlulugu ve onayci sorgulari (B31, D-106).
 *
 * Sorumlu personel GOREV ile belirlenir: `social_responsible_positions`
 * tablosundaki AKTIF satirlarin pozisyonlarindan birini BUGUN tutan AKTIF
 * personeldir. Pozisyon tutma tarih duyarlidir (ApproverResolver::position()
 * ile ayni pencere: valid_from <= bugun VE (valid_until bos YA DA >= bugun)).
 * Boylece sorumlu gorev Ayarlar'dan degistiginde yetki, rol matrisi yeniden
 * yazilmadan kendiliginden guncellenir.
 *
 * Sinif AppServiceProvider'da `scoped` baglidir: istek / is basina tek ornek.
 * Politika ayni istekte onlarca kez isResponsible() sorar (her kart, her
 * yetenek); sonuc bellekte tutulur ve veritabanina bir kez gidilir. Ayni istek
 * icinde sorumlu gorevler degistirilirse flush() cagrilir.
 */
final class SocialResponsibilityQueries
{
    /** @var list<int>|null */
    private ?array $responsibleIds = null;

    private ?Collection $responsiblePeople = null;

    public function __construct(private readonly ActorContext $actor) {}

    /** Bu personel su an sosyal medyadan sorumlu mu? */
    public function isResponsible(int $personnelId): bool
    {
        return $personnelId > 0 && in_array($personnelId, $this->responsibleIds(), true);
    }

    /**
     * Sorumlu (aktif) personelin kimlikleri.
     *
     * @return list<int>
     */
    public function responsibleIds(): array
    {
        return $this->responsibleIds ??= $this->resolveResponsibleIds();
    }

    /**
     * Sorumlu personel kayitlari (ada gore sirali; birim bilgisi yuklu).
     *
     * @return Collection<int, Personnel>
     */
    public function responsiblePeople(): Collection
    {
        if ($this->responsiblePeople !== null) {
            return $this->responsiblePeople;
        }

        $ids = $this->responsibleIds();

        if ($ids === []) {
            return $this->responsiblePeople = new Collection;
        }

        return $this->responsiblePeople = Personnel::query()
            ->with('orgUnit')
            ->whereIn('id', $ids)
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Acil onay bildiriminin alicilari: `Approve:SocialContent` iznine sahip,
     * ulasilabilir personel. Gelistirici rolu (teknik hesaplar), islemi yapan
     * kisi ve icerigi olusturan kisi listeye girmez. Izin henuz uretilmemisse
     * ya da kimse kalmadiysa Yonetici rolu sahiplerine dusulur.
     *
     * Hicbir kosulda hata firlatmaz: izin / rol satiri yoksa (shield:generate
     * oncesi) ya da sorgu basarisiz olursa bos liste doner.
     *
     * @return Collection<int, Personnel>
     */
    public function approvers(SocialContent $c): Collection
    {
        if (! SchemaReadiness::hasBatch('B05')) {
            return new Collection;
        }

        $excluded = $this->excludedFromApprovers($c);

        $byPermission = $this->guarded(fn (): Collection => $this->approverBase($excluded)
            ->permission(PermissionKey::for(SocialContentPolicy::class, 'approve'))
            ->get());

        if ($byPermission->isNotEmpty()) {
            return $this->distinct($byPermission);
        }

        $managers = $this->guarded(fn (): Collection => $this->approverBase($excluded)
            ->role(RoleResolver::MANAGER)
            ->get());

        return $this->distinct($managers);
    }

    /**
     * Sorumlu gorev secim listesi (Ayarlar): acik pozisyonlar ve - kapanmis
     * olsa bile - halen secili olanlar. Etiket "Gorev (uzun tire) Departman" bicimindedir;
     * kod ya da kimlik gosterilmez.
     *
     * @return list<array{id: int, label: string, selected: bool}>
     */
    public function positionOptions(): array
    {
        if (! SchemaReadiness::hasBatch('B31')) {
            return [];
        }

        $selected = $this->activePositionIds();

        $positions = Position::query()
            ->with('orgUnit')
            ->where(function (Builder $query) use ($selected): void {
                $query->where('status', PositionStatus::Active->value);

                if ($selected !== []) {
                    $query->orWhereIn('id', $selected);
                }
            })
            ->orderBy('title')
            ->orderBy('id')
            ->get();

        $options = [];

        foreach ($positions as $position) {
            $id = (int) $position->getKey();
            $title = trim((string) $position->title);
            $unit = trim((string) ($position->orgUnit?->name ?? ''));

            $options[] = [
                'id' => $id,
                'label' => $unit === '' ? $title : $title.' — '.$unit,
                'selected' => in_array($id, $selected, true),
            ];
        }

        return $options;
    }

    /**
     * Bellekteki sonuclari bosaltir. Sorumlu gorevler ya da pozisyon atamalari
     * ayni istek icinde degistiginde cagrilir.
     */
    public function flush(): void
    {
        $this->responsibleIds = null;
        $this->responsiblePeople = null;
    }

    /**
     * @return list<int>
     */
    private function resolveResponsibleIds(): array
    {
        if (! SchemaReadiness::hasBatch('B31')) {
            return [];
        }

        $positionIds = $this->activePositionIds();

        if ($positionIds === []) {
            return [];
        }

        $today = SocialClock::todayString();

        $holderIds = PositionAssignment::query()
            ->whereIn('position_id', $positionIds)
            ->where('valid_from', '<=', $today)
            ->where(fn (Builder $query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $today))
            ->pluck('personnel_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($holderIds === []) {
            return [];
        }

        return Personnel::query()
            ->whereIn('id', $holderIds)
            ->where('status', PersonnelStatus::Active->value)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Sorumlu olarak isaretli (aktif satir) pozisyon kimlikleri.
     *
     * @return list<int>
     */
    private function activePositionIds(): array
    {
        return SocialResponsiblePosition::query()
            ->where('status', ActiveStatus::Active->value)
            ->pluck('position_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Onayci adaylarinin ortak tabani: ulasilabilir, Gelistirici olmayan ve
     * haric tutulanlar disindaki personel.
     *
     * @param  list<int>  $excluded
     */
    private function approverBase(array $excluded): Builder
    {
        return Personnel::query()
            ->reachable()
            ->whereDoesntHave('roles', fn (Builder $roles) => $roles->where('name', RoleResolver::DEVELOPER))
            ->when($excluded !== [], fn (Builder $query) => $query->whereNotIn('id', $excluded))
            ->orderBy('full_name');
    }

    /**
     * Bildirim almayacaklar: islemi yapan kisi ve icerigi olusturan kisi.
     *
     * @return list<int>
     */
    private function excludedFromApprovers(SocialContent $c): array
    {
        $ids = [];

        $actorId = $this->actor->personnelId();

        if ($actorId !== null) {
            $ids[] = $actorId;
        }

        $creatorId = $c->getAttribute('created_by_personnel_id');

        if ($creatorId !== null) {
            $ids[] = (int) $creatorId;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Izin / rol satiri yoksa Spatie istisna firlatir (PermissionDoesNotExist,
     * RoleDoesNotExist); bildirim alicisi ararken bu bir hata degil "kimse yok"
     * demektir.
     *
     * @param  callable(): Collection<int, Personnel>  $callback
     * @return Collection<int, Personnel>
     */
    private function guarded(callable $callback): Collection
    {
        try {
            return $callback();
        } catch (Throwable) {
            return new Collection;
        }
    }

    /**
     * @param  Collection<int, Personnel>  $people
     * @return Collection<int, Personnel>
     */
    private function distinct(Collection $people): Collection
    {
        return $people->unique(fn (Personnel $personnel): int => (int) $personnel->getKey())->values();
    }
}
