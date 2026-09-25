<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Detay sayfasi olmayan satirin ayrinti penceresi (tablo kurallari D-125:
 * "direk ilgili satira tiklayinca detayina gidecek"). Pencere tablonun kendi
 * sutunlarindan kurulur - ayni etiket, ayni bicim, ayni rozet rengi - ve
 * salt okunurdur; boylece her alt tabloya ayri ayrinti semasi yazilmaz.
 *
 * Eylem "Goruntule" oldugu icin satirda dugme olarak gorunmez
 * (TableConventions); satira tiklamak pencereyi acar.
 */
final class RowDetail
{
    public static function action(): ViewAction
    {
        return ViewAction::make()
            ->modalWidth(Width::TwoExtraLarge)
            ->schema(fn (Schema $schema, HasTable $livewire): Schema => $schema
                ->columns(2)
                ->components(self::entries($livewire)));
    }

    /**
     * @return list<TextEntry>
     */
    private static function entries(HasTable $livewire): array
    {
        $entries = [];

        foreach ($livewire->getTable()->getColumns() as $column) {
            if (! $column instanceof TextColumn) {
                continue;
            }

            $entries[] = TextEntry::make($column->getName())
                ->label($column->getLabel())
                ->state(fn (Model $record): mixed => self::safe(fn (): mixed => $column->record($record)->getState()))
                ->formatStateUsing(fn (mixed $state, Model $record): mixed => self::safe(fn (): mixed => $column->record($record)->formatState($state)) ?? $state)
                ->badge($column->isBadge())
                ->color(fn (mixed $state, Model $record): mixed => self::safe(fn (): mixed => $column->record($record)->getColor($state)))
                ->placeholder(self::safe(fn (): mixed => $column->getPlaceholder()) ?? '-');
        }

        return $entries;
    }

    private static function safe(callable $resolve): mixed
    {
        try {
            return $resolve();
        } catch (Throwable) {
            return null;
        }
    }
}
