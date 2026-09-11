<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenderNoticeVersions\RelationManagers;

use App\Enums\Acquisition\TenderComplianceState;
use App\Enums\Acquisition\TenderRequirementType;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\TenderRequirement;
use App\Services\Acquisition\TenderRequirementService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RequirementsRelationManager extends RelationManager
{
    protected static string $relationship = 'requirements';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedListBullet;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('tender_requirement.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('tender_requirement.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('requirement_code')
                            ->label(__('tender_requirement.fields.requirement_code'))
                            ->required()
                            ->maxLength(32),
                        Select::make('requirement_type')
                            ->label(__('tender_requirement.fields.requirement_type'))
                            ->options(TenderRequirementType::class)
                            ->default(TenderRequirementType::Technical->value)
                            ->required()
                            ->native(false),
                        Textarea::make('description')
                            ->label(__('tender_requirement.fields.description'))
                            ->required()
                            ->columnSpanFull(),
                        Toggle::make('is_mandatory')
                            ->label(__('tender_requirement.fields.is_mandatory'))
                            ->default(true),
                        Select::make('compliance_state')
                            ->label(__('tender_requirement.fields.compliance_state'))
                            ->options(TenderComplianceState::class)
                            ->default(TenderComplianceState::Unknown->value)
                            ->required()
                            ->native(false),
                        Select::make('evaluated_by_personnel_id')
                            ->label(__('tender_requirement.fields.evaluated_by'))
                            ->relationship('evaluator', 'full_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        TextInput::make('sort_order')
                            ->label(__('tender_requirement.fields.sort_order'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('tender_requirement.label'))
            ->heading(__('tender_requirement.relation.title'))
            ->recordTitleAttribute('requirement_code')
            ->columns([
                TextColumn::make('requirement_code')
                    ->label(__('tender_requirement.fields.requirement_code')),
                TextColumn::make('requirement_type')
                    ->label(__('tender_requirement.fields.requirement_type'))
                    ->badge(),
                TextColumn::make('description')
                    ->label(__('tender_requirement.fields.description'))
                    ->limit(50),
                IconColumn::make('is_mandatory')
                    ->label(__('tender_requirement.fields.is_mandatory'))
                    ->boolean(),
                TextColumn::make('compliance_state')
                    ->label(__('tender_requirement.fields.compliance_state'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['tender_notice_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(TenderRequirementService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (TenderRequirement $record, array $data): Model {
                        try {
                            return app(TenderRequirementService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (TenderRequirement $record): bool {
                        try {
                            return app(TenderRequirementService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('tender_requirement.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedListBullet);
    }
}
