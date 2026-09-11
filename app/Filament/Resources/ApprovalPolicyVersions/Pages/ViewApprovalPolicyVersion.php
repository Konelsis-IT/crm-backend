<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalPolicyVersions\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\ApprovalPolicies\ApprovalPolicyResource;
use App\Filament\Resources\ApprovalPolicyVersions\ApprovalPolicyVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Approval\ApprovalPolicyVersion;
use App\Services\Approval\ApprovalPolicyVersionService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewApprovalPolicyVersion extends ViewRecord
{
    protected static string $resource = ApprovalPolicyVersionResource::class;

    public function getTitle(): string
    {
        /** @var ApprovalPolicyVersion $version */
        $version = $this->getRecord();

        return ($version->policy?->code ?? '-').' · v'.$version->version_no;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('approval_policy_version.sections.flow'))
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextEntry::make('policy.code')
                        ->label(__('approval_policy.label'))
                        ->badge()
                        ->color('gray')
                        ->url(fn (ApprovalPolicyVersion $record): ?string => $record->policy !== null ? ApprovalPolicyResource::getUrl('view', ['record' => $record->policy]) : null),
                    TextEntry::make('version_no')->label(__('approval_policy_version.fields.version_no'))->badge()->color('info'),
                    TextEntry::make('status')->label(__('approval_policy_version.fields.status'))->badge(),
                    TextEntry::make('mode')->label(__('approval_policy_version.fields.mode'))->badge()->color('primary'),
                    TextEntry::make('quorum_count')->label(__('approval_policy_version.fields.quorum_count'))->placeholder('-'),
                    IconEntry::make('requires_maker_checker')->label(__('approval_policy_version.fields.requires_maker_checker'))->boolean(),
                    IconEntry::make('reapproval_on_change')->label(__('approval_policy_version.fields.reapproval_on_change'))->boolean(),
                    TextEntry::make('sla_minutes')->label(__('approval_policy_version.fields.sla_minutes'))->placeholder('-')->suffix(' dk'),
                    TextEntry::make('risk_level')->label(__('approval_policy_version.fields.risk_level'))->badge()->placeholder('-'),
                    TextEntry::make('applies_min_amount')->label(__('approval_policy_version.fields.applies_min_amount'))->placeholder('-'),
                    TextEntry::make('applies_max_amount')->label(__('approval_policy_version.fields.applies_max_amount'))->placeholder('-'),
                    TextEntry::make('currency_code')->label(__('approval_policy_version.fields.currency_code'))->placeholder('-'),
                    TextEntry::make('publisher.full_name')->label(__('approval_policy_version.fields.publisher'))->placeholder('-'),
                    TextEntry::make('published_at')->label(__('approval_policy_version.fields.published_at'))->dateTime('d.m.Y H:i')->placeholder('-'),
                    TextEntry::make('change_summary')->label(__('approval_policy_version.fields.change_summary'))->placeholder('-')->columnSpan(2),
                ])),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn (): bool => $this->getRecord()->isDraft()),
            Action::make('publish')
                ->label(__('approval_policy_version.actions.publish'))
                ->color('success')
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->requiresConfirmation()
                ->modalDescription(__('approval_policy_version.help.publish'))
                ->visible(fn (): bool => $this->getRecord()->isDraft())
                ->action(function (): void {
                    try {
                        app(ApprovalPolicyVersionService::class)->publish($this->getRecord());
                        DomainNotifications::success(__('approval_policy_version.messages.published'));
                        $this->redirect(ApprovalPolicyVersionResource::getUrl('view', ['record' => $this->getRecord()]));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                }),
        ];
    }
}
