<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\RelationManagers;

use App\Enums\Document\DistributionKind;
use App\Filament\Support\FieldGrid;
use App\Models\Document\DocumentDistribution;
use App\Services\Audit\ActorContext;
use App\Services\Document\DocumentDistributionService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Revizyonlarin personele dagitim kayitlari.
 */
class DistributionsRelationManager extends RelationManager
{
    protected static string $relationship = 'distributions';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedPaperAirplane;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('document.relation.distributions.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('document.relation.distributions.title'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('document_revision_id')
                        ->label(__('document_revision.label'))
                        ->relationship(
                            'revision',
                            'revision_code',
                            modifyQueryUsing: fn ($query) => $query->where('document_id', $this->getOwnerRecord()->getKey()),
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('recipient_personnel_id')
                        ->label(__('document_distribution.fields.recipient'))
                        ->relationship('recipient', 'full_name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('distribution_kind')
                        ->label(__('document_distribution.fields.distribution_kind'))
                        ->options(DistributionKind::class)
                        ->default(DistributionKind::ForInformation->value)
                        ->required()
                        ->native(false),
                    Select::make('transmittal_id')
                        ->label(__('document_distribution.fields.transmittal'))
                        ->relationship('transmittal', 'transmittal_no')
                        ->searchable()
                        ->preload()
                        ->native(false),
                    Toggle::make('requires_acknowledgement')
                        ->label(__('document_distribution.fields.requires_acknowledgement')),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('document_distribution.label'))
            ->heading(__('document.relation.distributions.title'))
            ->recordTitleAttribute('distribution_kind')
            ->columns([
                TextColumn::make('revision.revision_code')
                    ->label(__('document_revision.label'))
                    ->placeholder('-'),
                TextColumn::make('recipient.full_name')
                    ->label(__('document_distribution.fields.recipient')),
                TextColumn::make('distribution_kind')
                    ->label(__('document_distribution.fields.distribution_kind'))
                    ->badge(),
                IconColumn::make('requires_acknowledgement')
                    ->label(__('document_distribution.fields.requires_acknowledgement'))
                    ->boolean(),
                TextColumn::make('sender.full_name')
                    ->label(__('document_distribution.fields.sender'))
                    ->placeholder('-'),
                TextColumn::make('distributed_at')
                    ->label(__('document_distribution.fields.distributed_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('transmittal.transmittal_no')
                    ->label(__('document_distribution.fields.transmittal'))
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['distributed_by_personnel_id'] = app(ActorContext::class)->personnelId();
                        $data['distributed_at'] = Carbon::now('UTC');

                        return app(DocumentDistributionService::class)->create($data);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (DocumentDistribution $record, array $data): Model => app(DocumentDistributionService::class)->update($record, $data)),
                DeleteAction::make()
                    ->using(fn (DocumentDistribution $record): bool => app(DocumentDistributionService::class)->delete($record)),
            ])
            ->toolbarActions([])
            ->defaultSort('distributed_at', 'desc')
            ->emptyStateHeading(__('document.relation.distributions.empty'))
            ->emptyStateIcon(Heroicon::OutlinedPaperAirplane);
    }
}
