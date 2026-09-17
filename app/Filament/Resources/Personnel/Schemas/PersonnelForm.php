<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\Schemas;

use App\Enums\Personnel\CompetencyLevel;
use App\Enums\Personnel\PersonnelStatus;
use App\Filament\Support\FieldGrid;
use App\Models\Personnel\Personnel;
use App\Query\Authorization\RoleQueries;
use App\Query\Personnel\PersonnelQueries;
use App\Services\Platform\SchemaReadiness;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class PersonnelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('personnel.sections.identity'))
                ->icon(Heroicon::OutlinedIdentification)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    FileUpload::make('photo_path')
                        ->label(__('personnel.fields.photo'))
                        ->image()
                        ->avatar()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('personel-fotograflari')
                        ->maxSize(4096)
                        ->columnSpanFull(),
                    TextInput::make('full_name')
                        ->label(__('personnel.fields.full_name'))
                        ->prefixIcon(Heroicon::OutlinedUser)
                        ->required()
                        ->maxLength(255),
                    TextInput::make('national_id')
                        ->label(__('personnel.fields.national_id'))
                        ->prefixIcon(Heroicon::OutlinedFingerPrint)
                        ->numeric()
                        ->length(11)
                        ->rule('digits:11')
                        ->helperText(__('personnel.help.national_id')),
                    TextInput::make('phone')
                        ->label(__('personnel.fields.phone'))
                        ->prefixIcon(Heroicon::OutlinedPhone)
                        ->tel()
                        ->maxLength(32)
                        ->helperText(__('personnel.help.phone')),
                    TextInput::make('email')
                        ->label(__('personnel.fields.email'))
                        ->prefixIcon(Heroicon::OutlinedEnvelope)
                        ->email()
                        ->required()
                        ->maxLength(320),
                ])),

            Section::make(__('personnel.sections.assignment'))
                ->icon(Heroicon::OutlinedBriefcase)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('org_unit_id')
                        ->label(__('personnel.fields.department'))
                        ->relationship('orgUnit', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->visible(fn (): bool => SchemaReadiness::hasBatch('B03'))
                        ->dehydrated(fn (): bool => SchemaReadiness::hasBatch('B03')),
                    Select::make('direct_manager_personnel_id')
                        ->label(__('personnel.fields.direct_manager'))
                        ->helperText(__('personnel.help.direct_manager'))
                        ->options(fn (?Personnel $record): array => app(PersonnelQueries::class)
                            ->managerOptions($record !== null ? (int) $record->getKey() : null))
                        ->searchable()
                        ->native(false)
                        ->visible(fn (): bool => SchemaReadiness::hasBatch('B03'))
                        ->dehydrated(fn (): bool => SchemaReadiness::hasBatch('B03'))
                        ->afterStateHydrated(function (Select $component, ?Personnel $record): void {
                            $component->state($record?->currentManager()?->getKey());
                        }),
                    TextInput::make('job_title')
                        ->label(__('personnel.fields.job_title'))
                        ->maxLength(255),
                    DatePicker::make('hired_on')
                        ->label(__('personnel.fields.hired_on'))
                        ->prefixIcon(Heroicon::OutlinedCalendarDays)
                        ->displayFormat('d.m.Y'),
                ])),

            Section::make(__('personnel.sections.competencies'))
                ->icon(Heroicon::OutlinedSparkles)
                ->description(__('personnel.help.competencies'))
                ->components([
                    Repeater::make('competencyRecords')
                        ->label(__('personnel.fields.competencies'))
                        ->hiddenLabel()
                        ->addActionLabel(__('personnel.actions.add_competency'))
                        ->columns(FieldGrid::COLUMNS)
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->schema(FieldGrid::fields([
                            Select::make('competency_id')
                                ->label(__('personnel.fields.competency'))
                                ->options(fn (): array => app(PersonnelQueries::class)->competencyOptions())
                                ->required()
                                ->searchable()
                                ->native(false)
                                ->distinct()
                                ->fixIndistinctState(),
                            Select::make('level')
                                ->label(__('personnel.fields.competency_level'))
                                ->options(CompetencyLevel::class)
                                ->default(CompetencyLevel::Intermediate->value)
                                ->required()
                                ->native(false),
                            TextInput::make('note')
                                ->label(__('personnel.fields.competency_note'))
                                ->maxLength(255),
                        ])),
                ]),

            Section::make(__('personnel.sections.account'))
                ->icon(Heroicon::OutlinedLockClosed)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('status')
                        ->label(__('personnel.fields.status'))
                        ->options(PersonnelStatus::class)
                        ->default(PersonnelStatus::Invited->value)
                        ->required()
                        ->native(false)
                        ->disabledOn('edit')
                        ->dehydratedWhenHidden(false)
                        ->helperText(__('personnel.help.status')),
                    Select::make('roles')
                        ->label(__('role.fields.roles'))
                        ->helperText(__('role.help.roles'))
                        ->options(fn (): array => app(RoleQueries::class)->roleOptions())
                        ->multiple()
                        ->searchable()
                        ->native(false)
                        ->afterStateHydrated(function (Select $component, ?Personnel $record): void {
                            $component->state($record?->roles->pluck('id')->all() ?? []);
                        }),
                    TextInput::make('password')
                        ->label(__('personnel.fields.password'))
                        ->prefixIcon(Heroicon::OutlinedKey)
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (mixed $state): bool => filled($state))
                        ->minLength(8)
                        ->maxLength(200)
                        ->helperText(__('personnel.help.password')),
                    Hidden::make('row_version')
                        ->hiddenOn('create'),
                ])),
        ]);
    }
}
