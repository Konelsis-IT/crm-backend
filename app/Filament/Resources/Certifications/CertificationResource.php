<?php

declare(strict_types=1);

namespace App\Filament\Resources\Certifications;

use App\Enums\Shared\ActiveStatus;
use App\Exceptions\StaleRecordException;
use App\Filament\Clusters\Settings;
use App\Filament\Resources\Certifications\Pages\ListCertifications;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Personnel\Certification;
use App\Services\Personnel\CertificationService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CertificationResource extends Resource
{
    protected static ?string $model = Certification::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('certification.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('certification.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('personnel.admin_ui')
            && SchemaReadiness::hasBatch('B13')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('certification.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextInput::make('code')
                        ->label(__('certification.fields.code'))
                        ->required()
                        ->alphaDash()
                        ->maxLength(32)
                        ->disabledOn('edit')
                        ->dehydratedWhenHidden(false),
                    TextInput::make('name')
                        ->label(__('certification.fields.name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('issuer')
                        ->label(__('certification.fields.issuer'))
                        ->maxLength(255),
                    // Kullanici karari (10 Eylul 2026): gecerlilik ay sayisi degil tarih olarak girilir (B13A).
                    DatePicker::make('valid_until')
                        ->label(__('certification.fields.valid_until'))
                        ->helperText(__('certification.help.validity_months'))
                        ->visible(fn (): bool => SchemaReadiness::hasBatch('B13A')),
                    TextInput::make('validity_months')
                        ->label(__('certification.fields.validity_months'))
                        ->helperText(__('certification.help.validity_months'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(600)
                        ->hidden(fn (): bool => SchemaReadiness::hasBatch('B13A')),
                    Toggle::make('is_field_mandatory')
                        ->label(__('certification.fields.is_field_mandatory')),
                    Select::make('status')
                        ->label(__('certification.fields.status'))
                        ->options(ActiveStatus::class)
                        ->default(ActiveStatus::Active->value)
                        ->required()
                        ->native(false),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('certification.fields.code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('certification.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('issuer')
                    ->label(__('certification.fields.issuer'))
                    ->placeholder('-'),
                TextColumn::make('valid_until')
                    ->label(__('certification.fields.valid_until'))
                    ->date('d.m.Y')
                    ->placeholder('-')
                    ->visible(fn (): bool => SchemaReadiness::hasBatch('B13A')),
                TextColumn::make('validity_months')
                    ->label(__('certification.fields.validity_months'))
                    ->placeholder('-')
                    ->hidden(fn (): bool => SchemaReadiness::hasBatch('B13A')),
                IconColumn::make('is_field_mandatory')
                    ->label(__('certification.fields.is_field_mandatory'))
                    ->boolean(),
                TextColumn::make('personnel_count')
                    ->label(__('certification.fields.personnel_count'))
                    ->counts('personnelCertifications'),
                TextColumn::make('status')
                    ->label(__('certification.fields.status'))
                    ->badge(),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (Certification $record, array $data): Model {
                        try {
                            return app(CertificationService::class)->update($record, $data);
                        } catch (StaleRecordException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCertifications::route('/'),
        ];
    }
}
