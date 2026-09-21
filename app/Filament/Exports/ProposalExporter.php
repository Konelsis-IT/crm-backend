<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Acquisition\Proposal;

/** Teklifler tablosu (ProposalResource) sutunlari. */
class ProposalExporter extends KonelsisExporter
{
    protected static ?string $model = Proposal::class;

    public static function fileLabel(): string
    {
        return __('proposal.plural');
    }

    public static function getColumns(): array
    {
        return [
            self::text('proposal_no', __('proposal.fields.proposal_no')),
            self::text('title', __('proposal.fields.title')),
            self::text('businessCase.title', __('proposal.fields.business_case')),
            self::text('status', __('proposal.fields.status')),
            self::text('offer_status', __('proposal.fields.offer_status')),
            self::boolean('is_selected', __('proposal.fields.is_selected')),
            self::text('currentVersion.version_no', __('proposal.fields.current_version')),
            self::text('owner.full_name', __('proposal.fields.owner')),
        ];
    }
}
