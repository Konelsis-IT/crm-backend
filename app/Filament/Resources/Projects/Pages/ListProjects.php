<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Concerns\InteractsWithCardView;
use App\Filament\Contracts\HasCardView;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\CardGallery;
use App\Models\Project\Project;
use App\Query\Ui\RecordCardQueries;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Proje listesi: varsayilan gorunum kapakli kayit karti (D-79); simge
 * dugmesiyle klasik tabloya gecilir. Kart aramasi ad, sehir, is kodu ve
 * proje yoneticisi uzerinden calisir.
 */
class ListProjects extends ListRecords implements HasCardView
{
    use InteractsWithCardView;

    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->cardViewToggleAction(),
            CreateAction::make()
                ->label(__('project.actions.create_direct'))
                ->icon(Heroicon::OutlinedRocketLaunch),
        ];
    }

    protected function cardPaginator(): LengthAwarePaginator
    {
        return app(RecordCardQueries::class)->projects(
            $this->cardSearchTerm(),
            $this->cardPerPageValue(),
            $this->cardPageValue(),
        );
    }

    protected function cardFor(Model $record): Component
    {
        /** @var Project $record */
        return app(CardGallery::class)->projectCard($record, CardGallery::VARIANT_COVER);
    }
}
