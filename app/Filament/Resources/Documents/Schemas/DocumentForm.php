<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Schemas;

use App\Enums\Document\DocumentStatus;
use App\Enums\Document\RevisionPurpose;
use App\Filament\Support\DocumentWorkspace;
use App\Filament\Support\FieldGrid;
use App\Services\Platform\SchemaReadiness;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Dokuman formu (D-75): bilgiler basliklar altinda gruplanir — Kimlik,
 * Sahiplik ve siniflandirma, Baglam; olusturmada ayrica "Belgenin asli"
 * (dosya yukle ya da sistemde yaz) bolumu vardir, ilk revizyon oradan dogar.
 */
final class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('document.sections.identity'))
                ->description(__('document.help.identity'))
                ->icon(Heroicon::OutlinedIdentification)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextInput::make('title')
                        ->label(__('document.fields.title'))
                        ->helperText(__('document.help.title'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Select::make('document_type_id')
                        ->label(__('document.fields.document_type'))
                        ->helperText(__('document.help.document_type'))
                        ->relationship('documentType', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('default_language')
                        ->label(__('document.fields.default_language'))
                        ->options(['tr' => 'Türkçe', 'en' => 'English'])
                        ->default('tr')
                        ->required()
                        ->native(false),
                    Textarea::make('description')
                        ->label(__('document.fields.description'))
                        ->helperText(__('document.help.description'))
                        ->rows(3)
                        ->columnSpanFull(),
                ])),
            Section::make(__('document.sections.ownership'))
                ->description(__('document.help.ownership'))
                ->icon(Heroicon::OutlinedShieldCheck)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('owner_personnel_id')
                        ->label(__('document.fields.owner'))
                        ->helperText(__('document.help.owner'))
                        ->relationship('owner', 'full_name')
                        ->default(fn (): ?int => auth()->id() !== null ? (int) auth()->id() : null)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('owner_org_unit_id')
                        ->label(__('document.fields.owner_org_unit'))
                        ->relationship('ownerOrgUnit', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false),
                    Select::make('classification_id')
                        ->label(__('document.fields.classification'))
                        ->relationship('classification', 'name_tr')
                        ->helperText(__('document.help.classification'))
                        ->searchable()
                        ->preload()
                        ->native(false),
                    Select::make('retention_policy_id')
                        ->label(__('document.fields.retention_policy'))
                        ->relationship('retentionPolicy', 'name_tr')
                        ->helperText(__('document.help.retention_policy'))
                        ->searchable()
                        ->preload()
                        ->native(false),
                    Toggle::make('is_controlled')
                        ->label(__('document.fields.is_controlled'))
                        ->helperText(__('document.help.is_controlled'))
                        ->default(true),
                    Select::make('status')
                        ->label(__('document.fields.status'))
                        ->options(DocumentStatus::class)
                        ->default(DocumentStatus::Draft->value)
                        ->required()
                        ->native(false)
                        ->visibleOn('edit'),
                ])),
            Section::make(__('document.sections.context'))
                ->description(__('document.help.context'))
                ->icon(Heroicon::OutlinedLink)
                ->columns(FieldGrid::COLUMNS)
                ->visible(fn (): bool => SchemaReadiness::hasBatch('B17'))
                ->components(FieldGrid::fields([
                    Select::make('project_id')
                        ->label(__('document.fields.project'))
                        ->relationship('project', 'name')
                        ->helperText(__('document.help.project'))
                        ->searchable()
                        ->preload()
                        ->native(false),
                ])),
            Section::make(__('document.sections.initial_content'))
                ->description(__('document.help.initial_content'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->columns(FieldGrid::COLUMNS)
                ->visibleOn('create')
                ->components(FieldGrid::fields([
                    ...DocumentWorkspace::revisionContentFields(true),
                    Select::make('revision_purpose')
                        ->label(__('document_revision.fields.purpose'))
                        ->options(RevisionPurpose::class)
                        ->default(RevisionPurpose::ForReview->value)
                        ->required()
                        ->native(false),
                    Select::make('revision_language')
                        ->label(__('document_revision.fields.language'))
                        ->options(['tr' => 'Türkçe', 'en' => 'English'])
                        ->default('tr')
                        ->required()
                        ->native(false),
                    Textarea::make('revision_change_summary')
                        ->label(__('document_revision.fields.change_summary'))
                        ->helperText(__('document.help.first_revision_note'))
                        ->rows(2)
                        ->columnSpanFull(),
                ])),
            Hidden::make('row_version')->hiddenOn('create'),
        ]);
    }
}
