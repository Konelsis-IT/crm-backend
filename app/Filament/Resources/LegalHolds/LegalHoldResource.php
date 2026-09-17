<?php

declare(strict_types=1);

namespace App\Filament\Resources\LegalHolds;

use App\Enums\Document\LegalHoldStatus;
use App\Filament\Clusters\Documents;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\LegalHolds\Pages\CreateLegalHold;
use App\Filament\Resources\LegalHolds\Pages\EditLegalHold;
use App\Filament\Resources\LegalHolds\Pages\ListLegalHolds;
use App\Filament\Resources\LegalHolds\Pages\ViewLegalHold;
use App\Filament\Resources\LegalHolds\RelationManagers\DocumentsRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Document\LegalHold;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LegalHoldResource extends Resource
{
    protected static ?string $model = LegalHold::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $cluster = Documents::class;

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('legal_hold.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('legal_hold.plural');
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
            Section::make(__('legal_hold.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextInput::make('code')
                        ->label(__('legal_hold.fields.code'))
                        ->required()
                        ->alphaDash()
                        ->maxLength(32)
                        ->disabledOn('edit')
                        ->dehydratedWhenHidden(false),
                    TextInput::make('name')
                        ->label(__('legal_hold.fields.name'))
                        ->required()
                        ->maxLength(255),
                    Select::make('personnel_id')
                        ->label(__('legal_hold.fields.requester'))
                        ->relationship('requester', 'full_name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('approved_by_personnel_id')
                        ->label(__('legal_hold.fields.approver'))
                        ->relationship('approver', 'full_name')
                        ->searchable()
                        ->preload()
                        ->native(false),
                    Select::make('status')
                        ->label(__('legal_hold.fields.status'))
                        ->options(LegalHoldStatus::class)
                        ->default(LegalHoldStatus::Draft->value)
                        ->required()
                        ->native(false),
                    DatePicker::make('starts_at')
                        ->label(__('legal_hold.fields.starts_at'))
                        ->displayFormat('d.m.Y')
                        ->required(),
                    DatePicker::make('released_at')
                        ->label(__('legal_hold.fields.released_at'))
                        ->displayFormat('d.m.Y'),
                    Textarea::make('reason')
                        ->label(__('legal_hold.fields.reason'))
                        ->required()
                        ->columnSpanFull(),
                    Textarea::make('release_reason')
                        ->label(__('legal_hold.fields.release_reason'))
                        ->columnSpanFull(),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('legal_hold.fields.code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('legal_hold.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('legal_hold.fields.status'))
                    ->badge(),
                TextColumn::make('requester.full_name')
                    ->label(__('legal_hold.fields.requester'))
                    ->placeholder('-'),
                TextColumn::make('starts_at')
                    ->label(__('legal_hold.fields.starts_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('released_at')
                    ->label(__('legal_hold.fields.released_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('legal_hold.fields.status'))
                    ->options(LegalHoldStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('code');
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegalHolds::route('/'),
            'create' => CreateLegalHold::route('/create'),
            'view' => ViewLegalHold::route('/{record}'),
            'edit' => EditLegalHold::route('/{record}/edit'),
        ];
    }
}
