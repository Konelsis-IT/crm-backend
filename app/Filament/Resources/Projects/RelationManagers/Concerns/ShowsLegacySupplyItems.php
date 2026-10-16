<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers\Concerns;

use App\Filament\Support\RowDetail;
use App\Support\Projects\ProjectPurchasing;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Eski tedarik kalemleri, salt okunur (D-196). Proje sayfasi satin alma
 * kayitlarina baglaninca (ProjectPurchasing) eski project_supply_items
 * kayitlari kaybolmaz: Satin alma / Lojistik / Yazilim tablolarinin altinda
 * kapali "Eski tedarik kayıtları" bolumunde ayni sutunlarla gorunur. Ekleme,
 * duzenleme ve durum degistirme yoktur; satira tiklamak ayrinti penceresini
 * acar (RowDetail). Feature::SupplyItems kapaliysa gorunmez.
 */
trait ShowsLegacySupplyItems
{
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return ProjectPurchasing::legacyEnabled() && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->headerActions([])
            ->recordActions([RowDetail::action()])
            ->toolbarActions([]);
    }
}
