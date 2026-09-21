<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Concerns\InteractsWithCardView;
use App\Filament\Contracts\HasCardView;
use App\Filament\Exports\DocumentExporter;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Support\CardGallery;
use App\Filament\Support\ExportActions;
use App\Models\Document\Document;
use App\Query\Ui\RecordCardQueries;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Dokuman listesi: varsayilan gorunum kapakli kayit karti (D-79); simge
 * dugmesiyle klasik tabloya gecilir. Kart aramasi numara, baslik, tip ve
 * sahip uzerinden calisir.
 */
class ListDocumentRecords extends ListRecords implements HasCardView
{
    use InteractsWithCardView;

    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->cardViewToggleAction(),
            ExportActions::table(DocumentExporter::class),
            CreateAction::make(),
        ];
    }

    protected function cardPaginator(): LengthAwarePaginator
    {
        return app(RecordCardQueries::class)->documents(
            $this->cardSearchTerm(),
            $this->cardPerPageValue(),
            $this->cardPageValue(),
        );
    }

    protected function cardSearchScope(Builder $query, ?string $search): Builder
    {
        return app(RecordCardQueries::class)->searchDocuments($query, $search);
    }

    protected function cardFor(Model $record): Component
    {
        /** @var Document $record */
        return app(CardGallery::class)->documentCard($record, CardGallery::VARIANT_COVER);
    }
}
