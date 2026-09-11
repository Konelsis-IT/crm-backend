<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalPolicies\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\ApprovalPolicies\ApprovalPolicyResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Approval\ApprovalPolicyService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateApprovalPolicy extends CreateRecord
{
    protected static string $resource = ApprovalPolicyResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(ApprovalPolicyService::class)->create($data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
