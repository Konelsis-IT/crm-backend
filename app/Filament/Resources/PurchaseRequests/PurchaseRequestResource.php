<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests;

use App\Enums\Platform\Feature;
use App\Enums\Procurement\UsageArea;
use App\Filament\NavigationGroup;
use App\Filament\Support\CodeLine;
use App\Filament\Resources\PurchaseRequests\Pages\CreatePurchaseRequest;
use App\Filament\Resources\PurchaseRequests\Pages\EditPurchaseRequest;
use App\Filament\Resources\PurchaseRequests\Pages\ListPurchaseRequests;
use App\Filament\Resources\PurchaseRequests\Pages\ViewPurchaseRequest;
use App\Filament\Resources\PurchaseRequests\RelationManagers\LinesRelationManager;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseRequest;
use App\Query\Procurement\PurchaseRequestQueries;
use App\Query\Procurement\PurchasingQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Operasyon - Satin alma > Satin alma talepleri (D-192, B53; kullanici:
 * "Birden fazla urun talebi olabilir. O yuzden kalemli bir yapi olmasi
 * lazim"). Talebin basligi (kod SAT-YYYY-NNNN, talep eden, departman,
 * kullanim yeri, proje, termin, acil, gerekce) ve kalemleri; her kalem
 * Satin alinacaklar'a duser, sayfada kalemin teklifi ve siparisi gorunur.
 *
 * Gorunurluk satin alinacaklarla aynidir (PurchaseRequestQueries::visibleTo).
 * B53 uygulanmadan ve "Kalemli satin alma talebi" ozelligi kapaliyken ekran
 * hic gorunmez; o durumda "Satin alma talebi ac" eski tek kalemli formu acar.
 * Silme yok, arsiv var (D-156).
 */
class PurchaseRequestResource extends Resource
{
    protected static ?string $model = PurchaseRequest::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::OperationsPurchasing;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'request_no';

    protected static bool $isGloballySearchable = false;

    public static function getModelLabel(): string
    {
        return __('purchase_request.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('purchase_request.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('purchase_request.navigation');
    }

    /**
     * D-229 (20 Ekim 2026, kullanici: "Ilk etapta, urun ve satin alma
     * taleplerini yayina acacak sekilde yapalim"): talepler Satin alinacaklar
     * ekranindan bagimsiz acilir (kalemler yine purchase_needs satiridir).
     */
    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::PurchaseRequests)
            && SchemaReadiness::hasBatch('B53')
            && parent::canAccess();
    }

    /** Kisi yalniz gorebildigi talepleri gorur (satin alma ve ust yonetim hepsini). */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $queries = app(PurchaseRequestQueries::class);

        return $queries->withListRelations(
            $queries->visibleTo(parent::getEloquentQuery(), $user instanceof Personnel ? $user : null),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(PurchaseRequestForm::components(editing: $schema->getOperation() === 'edit'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 15 Ekim 2026: ilk satir talebin basligi (kalemlerinden), altinda SAT
                // kodu (CodeLine); arama kod, kalem adi ve gerekcede.
                CodeLine::under(
                    TextColumn::make('request_no')
                        ->label(__('purchase_request.fields.title'))
                        ->formatStateUsing(fn (mixed $state, PurchaseRequest $record): string => $record->displayTitle())
                        ->weight('medium')
                        ->wrap()
                        ->searchable(query: fn (Builder $query, string $search): Builder => app(PurchaseRequestQueries::class)->search($query, $search))
                        ->sortable(),
                    'request_no',
                ),
                TextColumn::make('requestedBy.full_name')
                    ->label(__('purchase_request.fields.requested_by'))
                    ->placeholder(__('activity.system'))
                    ->searchable(),
                TextColumn::make('orgUnit.name')
                    ->label(__('purchase_request.fields.org_unit'))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('usage_area')
                    ->label(__('purchase_request.fields.usage_area'))
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('project.name')
                    ->label(__('purchase_request.fields.project'))
                    ->formatStateUsing(fn (mixed $state, PurchaseRequest $record): string => (string) ($record->project?->display_name ?? $state))
                    ->limit(30)
                    ->placeholder('-'),
                TextColumn::make('lines_count')
                    ->label(__('purchase_request.fields.lines_count'))
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('needed_by')
                    ->label(__('purchase_request.fields.needed_by'))
                    ->date('d.m.Y')
                    ->color(fn (PurchaseRequest $record): ?string => self::isOverdue($record) ? 'danger' : null)
                    ->icon(fn (PurchaseRequest $record): ?Heroicon => self::isOverdue($record) ? Heroicon::OutlinedExclamationTriangle : null)
                    ->placeholder('-')
                    ->sortable(),
                IconColumn::make('is_urgent')
                    ->label(__('purchase_request.fields.is_urgent'))
                    ->icon(fn (mixed $state): ?Heroicon => $state ? Heroicon::Fire : null)
                    ->color('danger')
                    ->tooltip(fn (PurchaseRequest $record): ?string => $record->getAttribute('is_urgent') ? __('purchase_request.values.urgent') : null)
                    ->alignCenter(),
                TextColumn::make('status')
                    ->label(__('purchase_request.fields.status'))
                    ->badge(),
                TextColumn::make('created_at')
                    ->label(__('purchase_request.fields.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Arsiv (D-156): varsayilan yalniz aktif talepler.
                SelectFilter::make('archive')
                    ->label(__('purchase_request.filters.archive'))
                    ->options([
                        'active' => __('purchase_request.filters.archive_active'),
                        'archived' => __('purchase_request.filters.archive_archived'),
                        'all' => __('purchase_request.filters.archive_all'),
                    ])
                    ->default('active')
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->query(fn (Builder $query, array $data): Builder => app(PurchaseRequestQueries::class)->archiveScope($query, (string) ($data['value'] ?? 'active'))),
                SelectFilter::make('usage_area')
                    ->label(__('purchase_request.fields.usage_area'))
                    ->options(UsageArea::class)
                    ->native(false),
                Filter::make('urgent')
                    ->label(__('purchase_request.filters.urgent'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => app(PurchasingQueries::class)->urgentScope($query)),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (PurchaseRequest $record): bool => Gate::allows('update', $record)),
                PurchaseRequestActions::archive(),
                PurchaseRequestActions::restore(),
            ])
            ->emptyStateHeading(__('purchase_request.empty'))
            ->emptyStateIcon(Heroicon::OutlinedHandRaised)
            ->defaultSort('id', 'desc');
    }

    /** Ayrinti: talep (yarim) ve gerekce / notlar (yarim) yan yana (D-168); kalemler altta. */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'xl' => 2])->components([
                Section::make(__('purchase_request.sections.request'))
                    ->icon(Heroicon::OutlinedHandRaised)
                    ->columns(['default' => 1, 'md' => 2])
                    ->components([
                        TextEntry::make('request_no')
                            ->label(__('purchase_request.fields.request_no'))
                            ->weight('bold'),
                        TextEntry::make('status')
                            ->label(__('purchase_request.fields.status'))
                            ->badge(),
                        TextEntry::make('requestedBy.full_name')
                            ->label(__('purchase_request.fields.requested_by'))
                            ->placeholder(__('activity.system')),
                        TextEntry::make('orgUnit.name')
                            ->label(__('purchase_request.fields.org_unit'))
                            ->placeholder('-'),
                        TextEntry::make('usage_area')
                            ->label(__('purchase_request.fields.usage_area'))
                            ->badge()
                            ->placeholder('-'),
                        TextEntry::make('project.name')
                            ->label(__('purchase_request.fields.project'))
                            ->formatStateUsing(fn (mixed $state, PurchaseRequest $record): string => (string) ($record->project?->display_name ?? $state))
                            ->visible(fn (PurchaseRequest $record): bool => $record->getAttribute('project_id') !== null),
                        TextEntry::make('needed_by')
                            ->label(__('purchase_request.fields.needed_by'))
                            ->date('d.m.Y')
                            ->color(fn (PurchaseRequest $record): ?string => self::isOverdue($record) ? 'danger' : null)
                            ->icon(fn (PurchaseRequest $record): ?Heroicon => self::isOverdue($record) ? Heroicon::OutlinedExclamationTriangle : null)
                            ->placeholder('-'),
                        TextEntry::make('urgency')
                            ->label(__('purchase_request.fields.is_urgent'))
                            ->state(fn (PurchaseRequest $record): string => $record->getAttribute('is_urgent') ? __('purchase_request.values.urgent') : __('purchase_request.values.normal'))
                            ->badge()
                            ->color(fn (PurchaseRequest $record): string => $record->getAttribute('is_urgent') ? 'danger' : 'gray')
                            ->icon(fn (PurchaseRequest $record): ?Heroicon => $record->getAttribute('is_urgent') ? Heroicon::Fire : null),
                        TextEntry::make('created_at')
                            ->label(__('purchase_request.fields.created_at'))
                            ->dateTime('d.m.Y H:i'),
                        TextEntry::make('archived_at')
                            ->label(__('purchase_request.fields.archived_at'))
                            ->dateTime('d.m.Y H:i')
                            ->visible(fn (PurchaseRequest $record): bool => $record->isArchived()),
                    ]),
                Section::make(__('purchase_request.sections.reasons'))
                    ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                    ->columns(1)
                    ->components([
                        TextEntry::make('justification')
                            ->label(__('purchase_request.fields.justification'))
                            ->placeholder('-'),
                        TextEntry::make('existing_check')
                            ->label(__('purchase_request.fields.existing_check'))
                            ->placeholder('-'),
                        TextEntry::make('note')
                            ->label(__('purchase_request.fields.note'))
                            ->placeholder('-'),
                    ]),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseRequests::route('/'),
            'create' => CreatePurchaseRequest::route('/create'),
            'view' => ViewPurchaseRequest::route('/{record}'),
            'edit' => EditPurchaseRequest::route('/{record}/edit'),
        ];
    }

    /** Termin gecti ve talep kapanmadi. */
    public static function isOverdue(PurchaseRequest $record): bool
    {
        $neededBy = $record->getAttribute('needed_by');

        return $neededBy !== null
            && ! $record->statusEnum()->isClosed()
            && $neededBy->toDateString() < DisplayTime::today()->format('Y-m-d');
    }
}
