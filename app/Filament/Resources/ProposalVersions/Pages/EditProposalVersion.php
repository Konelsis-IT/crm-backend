<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProposalVersions\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\ProposalVersions\ProposalVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Acquisition\ProposalVersionService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditProposalVersion extends EditRecord
{
    protected static string $resource = ProposalVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(ProposalVersionService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
