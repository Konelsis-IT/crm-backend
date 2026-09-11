<?php

declare(strict_types=1);

namespace App\Filament\Resources\Delegations\Pages;

use App\Filament\Resources\Delegations\DelegationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDelegations extends ListRecords
{
    protected static string $resource = DelegationResource::class;

    public function getSubheading(): ?string
    {
        return __('delegation.help.list');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
