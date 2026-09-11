<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProposalVersions\Pages;

use App\Filament\Resources\ProposalVersions\ProposalVersionResource;
use Filament\Resources\Pages\ListRecords;

class ListProposalVersions extends ListRecords
{
    protected static string $resource = ProposalVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
