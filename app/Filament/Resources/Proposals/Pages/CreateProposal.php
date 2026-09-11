<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\Pages;

use App\Filament\Resources\Proposals\ProposalResource;
use App\Services\Acquisition\ProposalService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProposal extends CreateRecord
{
    protected static string $resource = ProposalResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ProposalService::class)->create($data);
    }
}
