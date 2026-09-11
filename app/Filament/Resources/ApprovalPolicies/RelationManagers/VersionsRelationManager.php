<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalPolicies\RelationManagers;

use App\Enums\Approval\PolicyVersionStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\ApprovalPolicyVersions\ApprovalPolicyVersionResource;
use App\Filament\Resources\ApprovalPolicyVersions\Schemas\PolicyVersionForm;
use App\Filament\Support\DomainNotifications;
use App\Models\Approval\ApprovalPolicyVersion;
use App\Services\Approval\ApprovalPolicyVersionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Politikanin surumleri: yeni surum taslak acilir, adimlari eklenir, sonra
 * yayimlanir. Yayimli surum degistirilemez; degisiklik yeni surumle yapilir.
 */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedDocumentDuplicate;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('approval_policy_version.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return PolicyVersionForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('approval_policy_version.relation.title'))
            ->description(__('approval_policy_version.help.relation'))
            ->recordTitleAttribute('version_no')
            ->columns([
                TextColumn::make('version_no')
                    ->label(__('approval_policy_version.fields.version_no'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('approval_policy_version.fields.status'))
                    ->badge(),
                TextColumn::make('mode')
                    ->label(__('approval_policy_version.fields.mode'))
                    ->badge()
                    ->color('info'),
                IconColumn::make('requires_maker_checker')
                    ->label(__('approval_policy_version.fields.requires_maker_checker'))
                    ->boolean(),
                TextColumn::make('sla_minutes')
                    ->label(__('approval_policy_version.fields.sla_minutes'))
                    ->placeholder('-')
                    ->suffix(' dk'),
                TextColumn::make('steps_count')
                    ->label(__('approval_policy_version.fields.steps_count'))
                    ->counts('steps')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('publisher.full_name')
                    ->label(__('approval_policy_version.fields.publisher'))
                    ->placeholder('-'),
                TextColumn::make('published_at')
                    ->label(__('approval_policy_version.fields.published_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('approval_policy_version.actions.create'))
                    ->using(function (array $data): Model {
                        $data['approval_policy_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ApprovalPolicyVersionService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('approval_policy_version.actions.open_steps'))
                    ->icon(Heroicon::OutlinedQueueList)
                    ->url(fn (ApprovalPolicyVersion $record): string => ApprovalPolicyVersionResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->visible(fn (ApprovalPolicyVersion $record): bool => $record->isDraft())
                    ->using(function (ApprovalPolicyVersion $record, array $data): Model {
                        try {
                            return app(ApprovalPolicyVersionService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                Action::make('publish')
                    ->label(__('approval_policy_version.actions.publish'))
                    ->color('success')
                    ->icon(Heroicon::OutlinedRocketLaunch)
                    ->requiresConfirmation()
                    ->modalDescription(__('approval_policy_version.help.publish'))
                    ->visible(fn (ApprovalPolicyVersion $record): bool => $record->status === PolicyVersionStatus::Draft)
                    ->action(function (ApprovalPolicyVersion $record): void {
                        try {
                            app(ApprovalPolicyVersionService::class)->publish($record);
                            DomainNotifications::success(__('approval_policy_version.messages.published'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('version_no', 'desc')
            ->emptyStateHeading(__('approval_policy_version.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentDuplicate);
    }
}
