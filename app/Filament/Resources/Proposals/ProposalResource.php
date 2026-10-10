<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals;

use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\ProposalStatus;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Proposals\Pages\CreateProposal;
use App\Filament\Resources\Proposals\Pages\EditProposal;
use App\Filament\Resources\Proposals\Pages\ListProposals;
use App\Filament\Resources\Proposals\Pages\NewProposalVersion;
use App\Filament\Resources\Proposals\Pages\ViewProposal;
use App\Filament\Resources\Reports\RelationManagers\SubjectReportsRelationManager;
use App\Filament\Resources\Proposals\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Proposals\RelationManagers\MeetingNotesRelationManager;
use App\Filament\Resources\Proposals\RelationManagers\VersionsRelationManager;
use App\Filament\Support\DraftSupport;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\RecordLinks;
use App\Models\Acquisition\Proposal;
use App\Enums\Platform\Feature;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ProposalResource extends Resource
{
    protected static ?string $model = Proposal::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Acquisition;

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'proposal_no';

    public static function getModelLabel(): string
    {
        return __('proposal.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('proposal.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::Proposals)
            && SchemaReadiness::hasBatch('B16')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        // B29: teklif durumu (verilecek / verilen / onaylandi / kacan firsat).
        $b29 = fn (): bool => SchemaReadiness::hasBatch('B29');

        return $schema->columns(1)->components([
            Section::make(__('proposal.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('business_case_id')
                            ->label(__('proposal.fields.business_case'))
                            ->relationship('businessCase', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->disabledOn('edit')
                            ->dehydratedWhenHidden(false),
                        TextInput::make('title')
                            ->label(__('proposal.fields.title'))
                            ->required()
                            ->maxLength(255),
                        Select::make('owner_employee_id')
                            ->label(__('proposal.fields.owner'))
                            ->relationship('owner', 'full_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('offer_status')
                            ->label(__('proposal.fields.offer_status'))
                            ->options(OfferStatus::class)
                            ->default('to_be_submitted')
                            ->native(false)
                            ->visible($b29)
                            // D-182: duzenlemede Teklif durumu baslik dugmesinden degisir.
                            ->hiddenOn('edit')
                            ->dehydrated($b29),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        $b29 = fn (): bool => SchemaReadiness::hasBatch('B29');

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['businessCase.codes', ...($b29() ? ['businessCase.scopes'] : [])]))
            ->columns([
                TextColumn::make('proposal_no')
                    ->label(__('proposal.fields.proposal_no'))
                    ->searchable()
                    ->sortable(),
                // Bagli potansiyel isin kodu (D-132): teklif hangi POTIS'e ait, listede gorunur.
                TextColumn::make('case_code')
                    ->label(__('proposal.fields.case_code'))
                    ->state(fn (Proposal $record): ?string => $record->businessCase?->caseCode()?->formatted_code)
                    ->badge()
                    ->color('warning')
                    ->icon(fn (Proposal $record) => $record->businessCase !== null ? RecordLinks::iconFor($record->businessCase) : null)
                    ->url(fn (Proposal $record): ?string => $record->businessCase !== null ? RecordLinks::detailUrl($record->businessCase) : null)
                    ->placeholder('-'),
                TextColumn::make('title')
                    ->label(__('proposal.fields.title'))
                    ->limit(40)
                    // B43: taslak teklif basligin altinda yazar; D-162: simge ve amber satir.
                    ->description(fn (Proposal $record): ?string => DraftSupport::titleDescription($record))
                    ->icon(fn (Proposal $record): ?Heroicon => DraftSupport::titleIcon($record))
                    ->iconColor('warning')
                    ->tooltip(fn (Proposal $record): ?string => DraftSupport::titleTooltip($record))
                    ->searchable(),
                TextColumn::make('businessCase.title')
                    ->label(__('proposal.fields.business_case'))
                    ->limit(30),
                TextColumn::make('status')
                    ->label(__('proposal.fields.status'))
                    ->badge(),
                TextColumn::make('offer_status')
                    ->label(__('proposal.fields.offer_status'))
                    ->badge()
                    ->placeholder('-')
                    ->visible($b29),
                // Proje tipi potansiyel iste secilir; Potansiyel Isler listesindeki
                // sutunun aynisi: tipin rengi ve simgesiyle rozet (D-163).
                TextColumn::make('businessCase.scopes.scope_type')
                    ->label(__('business_case.fields.scope_types'))
                    ->badge()
                    ->placeholder('-')
                    ->visible($b29),
                // D-181: "Secili" sutunu kaldirildi; teklif her zaman en son
                // surumunden devam eder, secili teklif kavrami yok.
                TextColumn::make('currentVersion.version_no')
                    ->label(__('proposal.fields.current_version'))
                    ->placeholder('-'),
                TextColumn::make('owner.full_name')
                    ->label(__('proposal.fields.owner')),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('proposal.fields.status'))
                    ->options(ProposalStatus::class),
                SelectFilter::make('offer_status')
                    ->label(__('proposal.fields.offer_status'))
                    ->options(OfferStatus::class)
                    ->visible($b29),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->recordClasses(fn (Proposal $record): ?string => DraftSupport::rowClass($record))
            // Taslak teklife tiklayinca teklif adimindan duzenleme acilir (B43).
            ->recordUrl(fn (Proposal $record): string => DraftSupport::enabled() && (bool) $record->getAttribute('is_draft') && Gate::allows('update', $record)
                ? self::getUrl('edit', ['record' => $record])
                : self::getUrl('view', ['record' => $record]))
            ->defaultSort('proposal_no', 'desc');
    }

    public static function getRelations(): array
    {
        // Surumler, dokumanlar ve raporlar teklif sayfasinin alt listelerindedir (22 Eylul 2026).
        // D-158: B43 ile surumler sekme degil, sayfanin "Surumler" penceresidir.
        return [
            ...(SchemaReadiness::hasBatch('B43') ? [] : [VersionsRelationManager::class]),
            // Gorusme notlari (B41, D-137): bu teklifin konusuldugu gorusmeler.
            MeetingNotesRelationManager::class,
            DocumentsRelationManager::class,
            ...(SchemaReadiness::hasBatch('B10A') ? [SubjectReportsRelationManager::class] : []),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProposals::route('/'),
            'create' => CreateProposal::route('/create'),
            'view' => ViewProposal::route('/{record}'),
            'edit' => EditProposal::route('/{record}/edit'),
            // D-186: "Yeni teklif surumu" (duzenleme ekraniyla ayni form, kaydedince surum N+1).
            'new-version' => NewProposalVersion::route('/{record}/new-version'),
        ];
    }
}
