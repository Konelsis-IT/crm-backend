<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Enums\Acquisition\ProjectScopeType;
use App\Exceptions\Party\ActivityAreaParentInvalidException;
use App\Models\Party\ActivityArea;
use App\Models\Party\Party;
use App\Models\Party\PartyActivityArea;
use App\Services\AbstractService;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Taraf faaliyet satirlari (B33, D-107): Proje tipi + Faaliyet alani + Alt
 * faaliyet alani.
 *
 * sync() formdaki TAM listeyi yazar: listede olmayan satir kaldirilir, yeni
 * satir eklenir, ayni satir dokunulmadan kalir (hareket kaydi yalniz gercek
 * degisiklik icin dusulur). ensure() yalniz eksik satirlari ekler; aktarim
 * (MarketMapSeeder) bunu kullanir. Ayni satir iki kez girilirse bir kez
 * yazilir. Alt faaliyet alani secilen ana alanin altinda olmalidir.
 */
final class PartyActivityAreaService extends AbstractService
{
    protected string $model = PartyActivityArea::class;

    /** @var list<string> */
    protected array $with = ['activityArea', 'subActivityArea'];

    /**
     * @param  array<int|string, mixed>  $rows
     */
    public function sync(Party $party, array $rows): void
    {
        $this->transactions->run(function () use ($party, $rows): void {
            $wanted = $this->normalizeRows($rows);
            $existing = $this->existing($party);

            foreach ($existing as $key => $row) {
                if (! isset($wanted[$key])) {
                    $this->delete($row);
                }
            }

            foreach ($wanted as $key => $row) {
                if (! isset($existing[$key])) {
                    $this->create(['party_id' => $party->getKey(), ...$row]);
                }
            }
        });

        $party->unsetRelation('activityAreas');
    }

    /**
     * Eksik satirlari ekler, var olanlara dokunmaz; eklenen satir sayisini doner.
     *
     * @param  array<int|string, mixed>  $rows
     */
    public function ensure(Party $party, array $rows): int
    {
        $added = $this->transactions->run(function () use ($party, $rows): int {
            $existing = $this->existing($party);
            $count = 0;

            foreach ($this->normalizeRows($rows) as $key => $row) {
                if (! isset($existing[$key])) {
                    $this->create(['party_id' => $party->getKey(), ...$row]);
                    $count++;
                }
            }

            return $count;
        });

        $party->unsetRelation('activityAreas');

        return $added;
    }

    /**
     * Hareket ozeti: ham kimlik yerine okunur satir.
     *
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        return $record instanceof PartyActivityArea
            ? ['faaliyet' => $record->loadMissing(['activityArea', 'subActivityArea'])->summary()]
            : [];
    }

    /**
     * @return array<string, PartyActivityArea>
     */
    private function existing(Party $party): array
    {
        $rows = [];

        foreach ($party->activityAreas()->lockForUpdate()->get() as $row) {
            $type = $row->project_type instanceof ProjectScopeType ? $row->project_type->value : null;
            $rows[$this->key($type, (int) $row->activity_area_id, $row->sub_activity_area_id !== null ? (int) $row->sub_activity_area_id : null)] = $row;
        }

        return $rows;
    }

    /**
     * Form / aktarim satirlarini dogrular ve anahtarlar. Ana faaliyet alani
     * bos satir atlanir; alt alan ana alana ait degilse istisna firlatilir.
     *
     * @param  array<int|string, mixed>  $rows
     * @return array<string, array{project_type: ?string, activity_area_id: int, sub_activity_area_id: ?int}>
     */
    private function normalizeRows(array $rows): array
    {
        $areas = ActivityArea::query()->get(['id', 'parent_id'])->keyBy('id');
        $clean = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $areaId = (int) self::scalar($row['activity_area_id'] ?? null);
            $subId = filled($row['sub_activity_area_id'] ?? null) ? (int) self::scalar($row['sub_activity_area_id']) : null;
            $type = ProjectScopeType::tryFrom((string) self::scalar($row['project_type'] ?? null))?->value;

            if ($areaId <= 0) {
                continue;
            }

            $area = $areas->get($areaId);
            $sub = $subId !== null ? $areas->get($subId) : null;

            if ($area === null || $area->parent_id !== null || ($subId !== null && ($sub === null || (int) $sub->parent_id !== $areaId))) {
                throw ActivityAreaParentInvalidException::make();
            }

            $clean[$this->key($type, $areaId, $subId)] = [
                'project_type' => $type,
                'activity_area_id' => $areaId,
                'sub_activity_area_id' => $subId,
            ];
        }

        return $clean;
    }

    private function key(?string $type, int $areaId, ?int $subId): string
    {
        return ($type ?? '*').'|'.$areaId.'|'.($subId ?? '*');
    }

    private static function scalar(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
