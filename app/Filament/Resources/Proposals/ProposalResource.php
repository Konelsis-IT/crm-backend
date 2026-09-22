<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals;

use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\ProposalStatus;
use App\Exceptions\AbstractException;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Proposals\Pages\CreateProposal;
use App\Filament\Resources\Proposals\Pages\EditProposal;
use App\Filament\Resources\Proposals\Pages\ListProposals;
use App\Filament\Resources\Proposals\Pages\ViewProposal;
use App\Filament\Resources\Reports\RelationManagers\SubjectReportsRelationManager;
use App\Filament\Resources\Proposals\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Proposals\RelationManagers\VersionsRelationManager;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\Proposal;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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
        return FeatureFlags::enabled('acquisition.admin_ui')
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
                            ->dehydrated($b29),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        $b29 = fn (): bool => SchemaReadiness::hasBatch('B29');

        return $table
            ->columns([
                TextColumn::make('proposal_no')
                    ->label(__('proposal.fields.proposal_no'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('proposal.fields.title'))
                    ->limit(40)
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
                IconColumn::make('is_selected')
                    ->label(__('proposal.fields.is_selected'))
                    ->boolean(),
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
                Action::make('select')
                    ->label(__('proposal.actions.select'))
                    ->color('success')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->requiresConfirmation()
                    ->visible(fn (Proposal $record): bool => ! $record->is_selected)
                    ->action(function (Proposal $record, array $data): void {
                        try {
                            app(\App\Services\Acquisition\ProposalService::class)->select($record);
                            DomainNotifications::success(__('proposal.messages.done'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('proposal_no', 'desc');
    }

    public static function getRelations(): array
    {
        // Surumler, dokumanlar ve raporlar teklif sayfasinin alt listelerindedir (22 Eylul 2026).
        return [
            VersionsRelationManager::class,
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
        ];
    }
}
