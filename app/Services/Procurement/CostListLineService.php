<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\Procurement\CostListLine;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Maliyet listesi satirlari (B52, D-189). Satirlar aktarimla acilir; sonradan
 * yalniz katalog eslesmesi degisir (CostListService::resolveLine).
 */
final class CostListLineService extends AbstractService
{
    protected string $orderBy = 'sort_order';

    /**
     * Aktarimin satirlarini tek tek hareket kaydi yazmadan ekler: aktarimin
     * kendisi (cost_list.created, dosya ve kalem sayisiyla) Personel
     * Hareketleri'ne yazilir; 380 satir icin 380 hareket yazilmaz.
     *
     * @param  array<string, mixed>  $data
     */
    public function createQuietly(array $data): CostListLine
    {
        $line = new CostListLine;
        $line->fill($this->prepare($data, null));
        $line->save();

        return $line;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        unset($data['row_version']);

        return array_intersect_key($data, array_flip((new CostListLine)->getFillable()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        return ['kalem' => Str::limit((string) $record->getAttribute('description'), 120), 'satir' => $record->getAttribute('excel_row')];
    }
}
