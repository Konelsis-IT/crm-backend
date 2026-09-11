<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalPolicyVersions\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\ApprovalPolicyVersions\ApprovalPolicyVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Approval\ApprovalPolicyVersionService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditApprovalPolicyVersion extends EditRecord
{
    protected static string $resource = ApprovalPolicyVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(ApprovalPolicyVersionService::class)->update($record, $data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
