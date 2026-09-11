<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\Pages;

use App\Filament\Concerns\InteractsWithCardView;
use App\Filament\Contracts\HasCardView;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Support\CardGallery;
use App\Models\Personnel\Personnel;
use App\Query\Ui\RecordCardQueries;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Personel listesi: varsayilan gorunum kapakli kayit karti (D-79); simge
 * dugmesiyle klasik tabloya gecilir. Kart aramasi ad, e-posta, telefon,
 * gorev ve birim uzerinden calisir.
 */
class ListPersonnelRecords extends ListRecords implements HasCardView
{
    use InteractsWithCardView;

    protected static string $resource = PersonnelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->cardViewToggleAction(),
            CreateAction::make(),
        ];
    }

    protected function cardPaginator(): LengthAwarePaginator
    {
        return app(RecordCardQueries::class)->personnel(
            $this->cardSearchTerm(),
            $this->cardPerPageValue(),
            $this->cardPageValue(),
        );
    }

    protected function cardFor(Model $record): Component
    {
        /** @var Personnel $record */
        return app(CardGallery::class)->personnelCard($record, CardGallery::VARIANT_COVER);
    }
}
