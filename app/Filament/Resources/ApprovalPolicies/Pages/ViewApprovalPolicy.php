<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalPolicies\Pages;

use App\Filament\Resources\ApprovalPolicies\ApprovalPolicyResource;
use App\Filament\Support\FieldGrid;
use App\Models\Approval\ApprovalPolicy;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewApprovalPolicy extends ViewRecord
{
    protected static string $resource = ApprovalPolicyResource::class;

    public function getTitle(): string
    {
        /** @var ApprovalPolicy $policy */
        $policy = $this->getRecord();

        return $policy->code.' · '.$policy->localizedName();
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('approval_policy.sections.main'))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextEntry::make('code')->label(__('approval_policy.fields.code'))->badge()->color('gray'),
                    TextEntry::make('subject_type')
                        ->label(__('approval_policy.fields.subject_type'))
                        ->formatStateUsing(fn (string $state): string => __('approval_policy.subject_types.'.$state))
                        ->badge()
                        ->color('info'),
                    TextEntry::make('status')->label(__('approval_policy.fields.status'))->badge(),
                    TextEntry::make('name_tr')->label(__('approval_policy.fields.name_tr')),
                    TextEntry::make('name_en')->label(__('approval_policy.fields.name_en')),
                    TextEntry::make('currentVersion.version_no')
                        ->label(__('approval_policy.fields.current_version'))
                        ->placeholder(__('approval_policy.help.no_version'))
                        ->badge()
                        ->color('success'),
                ])),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
