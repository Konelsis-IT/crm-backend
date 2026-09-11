<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\ProjectConversionForm;
use App\Models\Acquisition\Proposal;
use App\Services\Project\ProjectConversionService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ViewProposal extends ViewRecord
{
    protected static string $resource = ProposalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('convert_to_project')
                ->label(__('project.actions.convert'))
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->color('success')
                ->modalHeading(__('project.actions.convert'))
                ->modalDescription(__('project.help.convert_intro'))
                ->modalSubmitActionLabel(__('project.actions.convert'))
                ->visible(function (): bool {
                    /** @var Proposal $proposal */
                    $proposal = $this->getRecord();

                    return Gate::allows('update', $proposal)
                        && $proposal->businessCase?->project === null;
                })
                ->schema(fn (): array => ProjectConversionForm::components($this->getRecord()))
                ->action(function (array $data): void {
                    try {
                        $project = app(ProjectConversionService::class)->convertProposal($this->getRecord(), $data);
                        DomainNotifications::success(__('project.messages.converted', ['code' => $project->businessCode?->formatted_code ?? '-']));
                        $this->redirect(ProjectResource::getUrl('view', ['record' => $project]));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                }),
        ];
    }
}
