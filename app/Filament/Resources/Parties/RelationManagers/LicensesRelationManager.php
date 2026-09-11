<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties\RelationManagers;

use App\Enums\Party\PartyCredentialStatus;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Party\PartyLicense;
use App\Services\Party\PartyLicenseService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LicensesRelationManager extends RelationManager
{
    protected static string $relationship = 'licenses';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedIdentification;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('party_license.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('party_license.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('license_type')
                            ->label(__('party_license.fields.license_type'))
                            ->required()
                            ->maxLength(32),
                        TextInput::make('license_no')
                            ->label(__('party_license.fields.license_no'))
                            ->required()
                            ->maxLength(64),
                        TextInput::make('issuer')
                            ->label(__('party_license.fields.issuer'))
                            ->maxLength(255),
                        DatePicker::make('issued_on')
                            ->label(__('party_license.fields.issued_on'))
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('valid_until')
                            ->label(__('party_license.fields.valid_until'))
                            ->displayFormat('d.m.Y'),
                        Select::make('document_revision_id')
                            ->label(__('party_license.fields.document_revision'))
                            ->relationship('documentRevision', 'title')
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Document\DocumentRevision $record): string => $record->document->document_no.' Rev.'.$record->revision_code)
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('status')
                            ->label(__('party_license.fields.status'))
                            ->options(PartyCredentialStatus::class)
                            ->default(PartyCredentialStatus::Valid->value)
                            ->required()
                            ->native(false),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('party_license.label'))
            ->heading(__('party_license.relation.title'))
            ->recordTitleAttribute('license_no')
            ->columns([
                TextColumn::make('license_type')
                    ->label(__('party_license.fields.license_type')),
                TextColumn::make('license_no')
                    ->label(__('party_license.fields.license_no'))
                    ->searchable(),
                TextColumn::make('issuer')
                    ->label(__('party_license.fields.issuer'))
                    ->placeholder('-'),
                TextColumn::make('valid_until')
                    ->label(__('party_license.fields.valid_until'))
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('party_license.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['party_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(PartyLicenseService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (PartyLicense $record, array $data): Model {
                        try {
                            return app(PartyLicenseService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (PartyLicense $record): bool {
                        try {
                            return app(PartyLicenseService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('valid_until')
            ->emptyStateHeading(__('party_license.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedIdentification);
    }
}
