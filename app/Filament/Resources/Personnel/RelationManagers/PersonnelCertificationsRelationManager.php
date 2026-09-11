<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\RelationManagers;

use App\Enums\Personnel\CertificationStatus;
use App\Exceptions\DuplicateRecordException;
use App\Exceptions\StaleRecordException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Personnel\PersonnelCertification;
use App\Services\Personnel\PersonnelCertificationService;
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
use Illuminate\Validation\ValidationException;

/**
 * Personelin aldigi sertifikalar.
 *
 * Durum (gecerli / suresi yaklasiyor / doldu) valid_until'dan otomatik
 * hesaplanir; yalniz "iptal edildi" elle secilir (PersonnelCertificationService).
 */
class PersonnelCertificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'personnelCertifications';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedShieldCheck;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('certification.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('certification.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('certification_id')
                        ->label(__('certification.label'))
                        ->relationship('certification', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    TextInput::make('certificate_no')
                        ->label(__('certification.fields.certificate_no'))
                        ->maxLength(64),
                    DatePicker::make('issued_on')
                        ->label(__('certification.fields.issued_on'))
                        ->required()
                        ->displayFormat('d.m.Y'),
                    DatePicker::make('valid_until')
                        ->label(__('certification.fields.valid_until'))
                        ->helperText(__('certification.help.valid_until'))
                        ->displayFormat('d.m.Y'),
                    Select::make('verified_by_personnel_id')
                        ->label(__('certification.fields.verified_by'))
                        ->relationship('verifier', 'full_name')
                        ->searchable()
                        ->preload()
                        ->native(false),
                    Select::make('status')
                        ->label(__('certification.fields.status'))
                        ->options(CertificationStatus::class)
                        ->default(CertificationStatus::Valid->value)
                        ->native(false),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('certification.label'))
            ->heading(__('certification.relation.title'))
            ->recordTitleAttribute('certificate_no')
            ->columns([
                TextColumn::make('certification.name')
                    ->label(__('certification.label'))
                    ->searchable(),
                TextColumn::make('certificate_no')
                    ->label(__('certification.fields.certificate_no'))
                    ->placeholder('-'),
                TextColumn::make('issued_on')
                    ->label(__('certification.fields.issued_on'))
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->label(__('certification.fields.valid_until'))
                    ->date('d.m.Y')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('certification.fields.status'))
                    ->badge(),
                TextColumn::make('verifier.full_name')
                    ->label(__('certification.fields.verified_by'))
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['personnel_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(PersonnelCertificationService::class)->create($data);
                        } catch (DuplicateRecordException) {
                            throw ValidationException::withMessages([
                                'data.issued_on' => __('certification.validation.duplicate'),
                            ]);
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (PersonnelCertification $record, array $data): Model {
                        try {
                            return app(PersonnelCertificationService::class)->update($record, $data);
                        } catch (StaleRecordException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        } catch (DuplicateRecordException) {
                            throw ValidationException::withMessages([
                                'data.issued_on' => __('certification.validation.duplicate'),
                            ]);
                        }
                    }),
                DeleteAction::make()
                    ->using(fn (PersonnelCertification $record): bool => app(PersonnelCertificationService::class)->delete($record)),
            ])
            ->toolbarActions([])
            ->defaultSort('issued_on', 'desc')
            ->emptyStateHeading(__('certification.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedShieldCheck);
    }
}
