<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Acquisition\Contract;

/** Sozlesmeler tablosu (ContractResource) sutunlari. */
class ContractExporter extends KonelsisExporter
{
    protected static ?string $model = Contract::class;

    public static function fileLabel(): string
    {
        return __('contract.plural');
    }

    public static function getColumns(): array
    {
        return [
            self::text('contract_no', __('contract.fields.contract_no')),
            self::text('businessCase.title', __('contract.fields.business_case')),
            self::text('contract_type', __('contract.fields.contract_type')),
            self::text('customerParty.display_name', __('contract.fields.customer_party')),
            self::text('status', __('contract.fields.status')),
            self::text('currentVersion.version_no', __('contract.fields.current_version')),
            self::date('signed_on', __('contract.fields.signed_on')),
        ];
    }
}
