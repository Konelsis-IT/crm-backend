<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transmittals;

use App\Enums\Document\TransmittalPurpose;
use App\Enums\Document\TransmittalStatus;
use App\Filament\Clusters\Documents;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Transmittals\Pages\CreateTransmittal;
use App\Filament\Resources\Transmittals\Pages\EditTransmittal;
use App\Filament\Resources\Transmittals\Pages\ListTransmittals;
use App\Filament\Resources\Transmittals\Pages\ViewTransmittal;
use App\Filament\Resources\Transmittals\RelationManagers\ItemsRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Document\Transmittal;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransmittalResource extends Resource
{
    protected static ?string $model = Transmittal::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $cluster = Documents::class;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'transmittal_no';

    public static function getModelLabel(): string
    {
        return __('transmittal.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('transmittal.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('documents.admin_ui')
            && SchemaReadiness::hasBatch('B06')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('transmittal.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextInput::make('recipient_description')
                        ->label(__('transmittal.fields.recipient_description'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Select::make('project_id')
                        ->label(__('transmittal.fields.project'))
                        ->relationship('project', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->visible(fn (): bool => SchemaReadiness::hasBatch('B17')),
                    Select::make('recipient_party_id')
                        ->label(__('transmittal.fields.recipient_party'))
                        ->relationship('recipientParty', 'display_name')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->visible(fn (): bool => SchemaReadiness::hasBatch('B16')),
                    Select::make('purpose')
                        ->label(__('transmittal.fields.purpose'))
                        ->options(TransmittalPurpose::class)
                        ->default(TransmittalPurpose::ForInformation->value)
                        ->required()
                        ->native(false),
                    Select::make('status')
                        ->label(__('transmittal.fields.status'))
                        ->options(TransmittalStatus::class)
                        ->default(TransmittalStatus::Draft->value)
                        ->required()
                        ->native(false),
                    Select::make('issued_by_personnel_id')
                        ->label(__('transmittal.fields.issuer'))
                        ->relationship('issuer', 'full_name')
                        ->searchable()
                        ->preload()
                        ->native(false),
                    DateTimePicker::make('issued_at')
                        ->label(__('transmittal.fields.issued_at')),
                    Select::make('cover_document_revision_id')
                        ->label(__('transmittal.fields.cover_revision'))
                        ->relationship('coverRevision', 'revision_code')
                        ->searchable()
                        ->preload()
                        ->native(false),
                    TextInput::make('external_reference')
                        ->label(__('transmittal.fields.external_reference'))
                        ->maxLength(255),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transmittal_no')
                    ->label(__('transmittal.fields.transmittal_no'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('recipient_description')
                    ->label(__('transmittal.fields.recipient_description'))
                    ->searchable()
                    ->limit(50),
                TextColumn::make('purpose')
                    ->label(__('transmittal.fields.purpose'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('transmittal.fields.status'))
                    ->badge(),
                TextColumn::make('issuer.full_name')
                    ->label(__('transmittal.fields.issuer'))
                    ->placeholder('-'),
                TextColumn::make('issued_at')
                    ->label(__('transmittal.fields.issued_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('transmittal.fields.status'))
                    ->options(TransmittalStatus::class),
                SelectFilter::make('purpose')
                    ->label(__('transmittal.fields.purpose'))
                    ->options(TransmittalPurpose::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('transmittal_no', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransmittals::route('/'),
            'create' => CreateTransmittal::route('/create'),
            'view' => ViewTransmittal::route('/{record}'),
            'edit' => EditTransmittal::route('/{record}/edit'),
        ];
    }
}
