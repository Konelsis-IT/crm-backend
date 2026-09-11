<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalPolicyVersions\Pages;

use App\Filament\Resources\ApprovalPolicyVersions\ApprovalPolicyVersionResource;
use Filament\Resources\Pages\ListRecords;

class ListApprovalPolicyVersions extends ListRecords
{
    protected static string $resource = ApprovalPolicyVersionResource::class;
}
