<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProposalVersions\RelationManagers;

use App\Enums\Acquisition\DeviationStatus;
use App\Enums\Acquisition\DeviationType;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\Deviation;
use App\Services\Acquisition\DeviationService;
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

class DeviationsRelationManager extends RelationManager
{
    protected static string $relationship = 'deviations';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedExclamationTriangle;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('deviation.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('deviation.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('deviation_type')
                            ->label(__('deviation.fields.deviation_type'))
                            ->options(DeviationType::class)
                            ->default(DeviationType::Technical->value)
                            ->required()
                            ->native(false),
                        Select::make('compliance_item_id')
                            ->label(__('deviation.fields.compliance_item'))
                            ->relationship('complianceItem', 'requirement_code')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Textarea::make('description')
                            ->label(__('deviation.fields.description'))
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('justification')
                            ->label(__('deviation.fields.justification'))
                            ->columnSpanFull(),
                        Select::make('status')
                            ->label(__('deviation.fields.status'))
                            ->options(DeviationStatus::class)
                            ->default(DeviationStatus::Proposed->value)
                            ->required()
                            ->native(false),
                        TextInput::make('sort_order')
                            ->label(__('deviation.fields.sort_order'))
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
            ->modelLabel(__('deviation.label'))
            ->heading(__('deviation.relation.title'))
            ->recordTitleAttribute('deviation_type')
            ->columns([
                TextColumn::make('deviation_type')
                    ->label(__('deviation.fields.deviation_type'))
                    ->badge(),
                TextColumn::make('description')
                    ->label(__('deviation.fields.description'))
                    ->limit(50),
                TextColumn::make('complianceItem.requirement_code')
                    ->label(__('deviation.fields.compliance_item'))
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('deviation.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['proposal_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(DeviationService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (Deviation $record, array $data): Model {
                        try {
                            return app(DeviationService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (Deviation $record): bool {
                        try {
                            return app(DeviationService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('deviation.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedExclamationTriangle);
    }
}
