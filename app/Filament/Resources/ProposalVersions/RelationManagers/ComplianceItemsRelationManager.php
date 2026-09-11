<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProposalVersions\RelationManagers;

use App\Enums\Acquisition\ComplianceState;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\ComplianceItem;
use App\Services\Acquisition\ComplianceItemService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ComplianceItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'complianceItems';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('compliance_item.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('compliance_item.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('requirement_code')
                            ->label(__('compliance_item.fields.requirement_code'))
                            ->required()
                            ->maxLength(32),
                        Select::make('tender_requirement_id')
                            ->label(__('compliance_item.fields.tender_requirement'))
                            ->relationship('tenderRequirement', 'requirement_code')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Textarea::make('description')
                            ->label(__('compliance_item.fields.description'))
                            ->required()
                            ->columnSpanFull(),
                        Select::make('compliance_state')
                            ->label(__('compliance_item.fields.compliance_state'))
                            ->options(ComplianceState::class)
                            ->default(ComplianceState::Comply->value)
                            ->required()
                            ->native(false),
                        Textarea::make('note')
                            ->label(__('compliance_item.fields.note'))
                            ->columnSpanFull(),
                        TextInput::make('sort_order')
                            ->label(__('compliance_item.fields.sort_order'))
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
            ->modelLabel(__('compliance_item.label'))
            ->heading(__('compliance_item.relation.title'))
            ->recordTitleAttribute('requirement_code')
            ->columns([
                TextColumn::make('requirement_code')
                    ->label(__('compliance_item.fields.requirement_code')),
                TextColumn::make('description')
                    ->label(__('compliance_item.fields.description'))
                    ->limit(50),
                TextColumn::make('compliance_state')
                    ->label(__('compliance_item.fields.compliance_state'))
                    ->badge(),
                TextColumn::make('note')
                    ->label(__('compliance_item.fields.note'))
                    ->limit(30)
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['proposal_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ComplianceItemService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ComplianceItem $record, array $data): Model {
                        try {
                            return app(ComplianceItemService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (ComplianceItem $record): bool {
                        try {
                            return app(ComplianceItemService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('compliance_item.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck);
    }
}
