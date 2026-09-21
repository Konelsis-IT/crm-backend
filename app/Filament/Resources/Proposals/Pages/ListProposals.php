<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\Pages;

use App\Filament\Exports\ProposalExporter;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Support\ExportActions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProposals extends ListRecords
{
    protected static string $resource = ProposalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportActions::table(ProposalExporter::class),
            CreateAction::make(),
        ];
    }
}
