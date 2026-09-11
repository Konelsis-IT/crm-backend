<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalPolicies\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\ApprovalPolicies\ApprovalPolicyResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Approval\ApprovalPolicyService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditApprovalPolicy extends EditRecord
{
    protected static string $resource = ApprovalPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(ApprovalPolicyService::class)->update($record, $data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
