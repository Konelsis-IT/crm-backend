<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalPolicyVersions\RelationManagers;

use App\Filament\Support\RowDetail;
use App\Enums\Approval\DecisionRule;
use App\Enums\Approval\ResolverType;
use App\Enums\Project\TeamRole;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Approval\ApprovalPolicyVersion;
use App\Models\Approval\ApprovalStep;
use App\Query\Approval\ApprovalQueries;
use App\Query\Personnel\PersonnelQueries;
use App\Services\Approval\ApprovalStepService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Surumun onay adimlari: sira, onayci cozumleme kurali, karar kurali,
 * atlanabilirlik, vekalet ve SLA. Yalniz taslak surumde duzenlenir.
 */
class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedQueueList;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('approval_step.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('approval_step.sections.identity'))
                ->icon(Heroicon::OutlinedTag)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextInput::make('step_code')
                        ->label(__('approval_step.fields.step_code'))
                        ->helperText(__('approval_step.help.step_code'))
                        ->required()
                        ->alphaDash()
                        ->maxLength(32),
                    TextInput::make('sequence_no')
                        ->label(__('approval_step.fields.sequence_no'))
                        ->helperText(__('approval_step.help.sequence_no'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(99),
                    Select::make('decision_rule')
                        ->label(__('approval_step.fields.decision_rule'))
                        ->helperText(__('approval_step.help.decision_rule'))
                        ->options(DecisionRule::class)
                        ->default(DecisionRule::AnyOne->value)
                        ->required()
                        ->native(false),
                    TextInput::make('name_tr')
                        ->label(__('approval_step.fields.name_tr'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('name_en')
                        ->label(__('approval_step.fields.name_en'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('sla_minutes')
                        ->label(__('approval_step.fields.sla_minutes'))
                        ->helperText(__('approval_step.help.sla_minutes'))
                        ->numeric()
                        ->minValue(1)
                        ->suffix('dk'),
                ])),
            Section::make(__('approval_step.sections.resolver'))
                ->description(__('approval_step.help.resolver'))
                ->icon(Heroicon::OutlinedUserGroup)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('resolver_type')
                        ->label(__('approval_step.fields.resolver_type'))
                        ->options(ResolverType::class)
                        ->required()
                        ->native(false)
                        ->live(),
                    Select::make('resolver_target_id')
                        ->label(fn (Get $get): string => $get('resolver_type') === ResolverType::Position->value
                            ? __('approval_step.fields.target_position')
                            : __('approval_step.fields.target_personnel'))
                        ->options(fn (Get $get): array => $get('resolver_type') === ResolverType::Position->value
                            ? app(PersonnelQueries::class)->positionOptions()
                            : app(PersonnelQueries::class)->personnelOptions())
                        ->searchable()
                        ->native(false)
                        ->visible(fn (Get $get): bool => in_array($get('resolver_type'), [ResolverType::Personnel->value, ResolverType::Position->value], true))
                        ->required(fn (Get $get): bool => in_array($get('resolver_type'), [ResolverType::Personnel->value, ResolverType::Position->value], true)),
                    Select::make('role_code')
                        ->label(__('approval_step.fields.role_code'))
                        ->options(fn (Get $get): array => match ($get('resolver_type')) {
                            ResolverType::RbacRole->value => app(ApprovalQueries::class)->roleOptions(),
                            ResolverType::ProjectRole->value => TeamRole::options(),
                            default => [],
                        })
                        ->searchable()
                        ->native(false)
                        ->visible(fn (Get $get): bool => in_array($get('resolver_type'), [ResolverType::RbacRole->value, ResolverType::ProjectRole->value], true))
                        ->required(fn (Get $get): bool => in_array($get('resolver_type'), [ResolverType::RbacRole->value, ResolverType::ProjectRole->value], true)),
                    Toggle::make('is_optional')
                        ->label(__('approval_step.fields.is_optional'))
                        ->helperText(__('approval_step.help.is_optional'))
                        ->default(false),
                    Toggle::make('allows_delegation')
                        ->label(__('approval_step.fields.allows_delegation'))
                        ->helperText(__('approval_step.help.allows_delegation'))
                        ->default(true),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        $isDraft = fn (): bool => $this->getOwnerRecord() instanceof ApprovalPolicyVersion && $this->getOwnerRecord()->isDraft();

        return $table
            ->modelLabel(__('approval_step.label'))
            ->heading(__('approval_step.relation.title'))
            ->description(__('approval_step.help.relation'))
            ->recordTitleAttribute('step_code')
            ->columns([
                TextColumn::make('sequence_no')
                    ->label(__('approval_step.fields.sequence_no'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('step_code')
                    ->label(__('approval_step.fields.step_code'))
                    ->weight('semibold'),
                TextColumn::make('name_tr')
                    ->label(__('approval_step.fields.name_tr')),
                TextColumn::make('resolver_type')
                    ->label(__('approval_step.fields.resolver_type'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('role_code')
                    ->label(__('approval_step.fields.role_code'))
                    ->placeholder('-'),
                TextColumn::make('decision_rule')
                    ->label(__('approval_step.fields.decision_rule'))
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_optional')
                    ->label(__('approval_step.fields.is_optional'))
                    ->boolean(),
                IconColumn::make('allows_delegation')
                    ->label(__('approval_step.fields.allows_delegation'))
                    ->boolean(),
                TextColumn::make('sla_minutes')
                    ->label(__('approval_step.fields.sla_minutes'))
                    ->placeholder('-')
                    ->suffix(' dk'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible($isDraft)
                    ->using(function (array $data): Model {
                        $data['approval_policy_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ApprovalStepService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                // Satira tiklamak ayrinti penceresini acar (D-125); dugme gorunmez.
                RowDetail::action(),
                EditAction::make()
                    ->visible($isDraft)
                    ->using(function (ApprovalStep $record, array $data): Model {
                        try {
                            return app(ApprovalStepService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->visible($isDraft)
                    ->using(function (ApprovalStep $record): bool {
                        try {
                            return app(ApprovalStepService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('sequence_no')
            ->emptyStateHeading(__('approval_step.relation.empty'))
            ->emptyStateDescription(__('approval_step.help.empty'))
            ->emptyStateIcon(Heroicon::OutlinedQueueList);
    }
}
