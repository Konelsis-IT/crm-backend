<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\RelationManagers;

use App\Filament\Support\RowDetail;
use App\Enums\Personnel\ReportingRelationType;
use App\Models\Personnel\ReportingRelationship;
use App\Query\Personnel\PersonnelQueries;
use App\Services\Personnel\ReportingRelationshipService;
use App\Support\DisplayTime;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Amir gecmisi: dogrudan amir (line, tek) ve ek amirler (islevsel / proje).
 * 24 Eylul 2026 kullanici karari: bir kisi birden fazla mudure baglidir;
 * raporlari ve isleri butun amirleri gorur.
 *
 * Dogrudan amir personel formundan degisir (SyncPersonnelManager); ek
 * amirler bu listeden eklenir ve kapatilir. Yazma islemleri
 * ReportingRelationshipService uzerinden gider.
 */
class ReportingHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'reportingRelationships';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedUserGroup;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('assignment.reporting.title');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('assignment.reporting.title'))
            ->description(__('assignment.reporting.help'))
            ->columns([
                TextColumn::make('manager.full_name')
                    ->label(__('assignment.reporting.manager'))
                    ->placeholder('-'),
                TextColumn::make('relation_type')
                    ->label(__('assignment.reporting.kind'))
                    ->badge(),
                TextColumn::make('valid_from')
                    ->label(__('assignment.fields.effective_from'))
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->label(__('assignment.fields.effective_to'))
                    ->date('d.m.Y')
                    ->placeholder(__('assignment.messages.ongoing')),
            ])
            // Butun amir turleri gorunur: dogrudan amir tektir, ek amirler
            // islevsel / proje iliskisiyle eklenir (24 Eylul 2026).
            ->headerActions([
                CreateAction::make()
                    ->label(__('assignment.reporting.add'))
                    ->modalHeading(__('assignment.reporting.add'))
                    ->schema(fn (Schema $schema): Schema => $schema->columns(1)->components([
                        Select::make('manager_personnel_id')
                            ->label(__('assignment.reporting.manager'))
                            ->options(fn (): array => app(PersonnelQueries::class)->managerOptions((int) $this->getOwnerRecord()->getKey()))
                            ->searchable()
                            ->required()
                            ->native(false),
                        Select::make('relation_type')
                            ->label(__('assignment.reporting.kind'))
                            ->options([
                                ReportingRelationType::Functional->value => ReportingRelationType::Functional->getLabel(),
                                ReportingRelationType::Project->value => ReportingRelationType::Project->getLabel(),
                            ])
                            ->default(ReportingRelationType::Functional->value)
                            ->helperText(__('assignment.reporting.kind_help'))
                            ->required()
                            ->native(false),
                        DatePicker::make('valid_from')
                            ->label(__('assignment.fields.effective_from'))
                            ->default(now(DisplayTime::zone())->format('Y-m-d'))
                            ->displayFormat('d.m.Y')
                            ->required(),
                    ]))
                    ->using(fn (array $data): Model => app(ReportingRelationshipService::class)->addManager(
                        (int) $this->getOwnerRecord()->getKey(),
                        (int) $data['manager_personnel_id'],
                        (string) $data['relation_type'],
                        $data['valid_from'] ?? null,
                    )),
            ])
            ->recordActions([
                // Satira tiklamak ayrinti penceresini acar (D-125); dugme gorunmez.
                RowDetail::action(),
                Action::make('close')
                    ->label(__('assignment.reporting.close'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->authorize('update')
                    ->visible(fn (ReportingRelationship $record): bool => $record->valid_until === null
                        && $record->relation_type !== ReportingRelationType::Line)
                    ->action(fn (ReportingRelationship $record) => app(ReportingRelationshipService::class)->close($record)),
            ])
            ->toolbarActions([])
            ->defaultSort('valid_from', 'desc')
            ->emptyStateHeading(__('assignment.reporting.empty'))
            ->emptyStateIcon(Heroicon::OutlinedUserGroup);
    }
}
