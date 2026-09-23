<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\RelationManagers;

use App\Filament\Pages\Work\ControlMatrix;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Personnel\Personnel;
use App\Models\Report\Report;
use App\Models\Report\WorkItem;
use App\Query\Report\ControlMatrixQueries;
use App\Reports\Work\ControlSectionCatalog;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Personel karti > Haftalik kontrol sekmesi (B36, D-115): kontrol
 * matrisinin kisi uzerindeki sayisal ozeti ve gunluk kontrol kayitlari.
 * D-116: yalniz ust yonetim gorur; kisinin kendisi gormez.
 */
class ControlReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'controlReports';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('work_item.control_tab.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return SchemaReadiness::hasBatch('B36')
            && $user instanceof Personnel
            && $ownerRecord instanceof Personnel
            && Gate::forUser($user)->allows('viewControl', [WorkItem::class, $ownerRecord]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        /** @var Personnel $owner */
        $owner = $this->getOwnerRecord();
        $summary = app(ControlMatrixQueries::class)->personnelSummary($owner);
        $catalog = app(ControlSectionCatalog::class);

        return $table
            ->heading(__('work_item.control_tab.heading'))
            ->description(__('work_item.control_tab.summary', [
                'ok' => $summary['ok'],
                'total' => $summary['total'],
                'weeks' => implode(', ', array_map(fn (array $week): string => $week['ok'].'/'.$week['total'], $summary['weeks'])),
            ]).($summary['repeating'] !== null
                ? ' · '.__('work_item.control_tab.repeating', ['label' => $summary['repeating']['label'], 'weeks' => $summary['repeating']['weeks']])
                : ''))
            ->defaultSort('period_start', 'desc')
            ->recordUrl(fn (Report $record): string => ReportResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('period_start')
                    ->label(__('work_item.control_tab.columns.week'))
                    ->formatStateUsing(fn (Report $record): string => (string) $record->periodLabel())
                    ->sortable(),
                TextColumn::make('section')
                    ->label(__('work_item.control_tab.columns.section'))
                    ->state(fn (Report $record): ?string => $record->payload['section_label'] ?? null),
                TextColumn::make('score')
                    ->label(__('work_item.control_tab.columns.score'))
                    ->state(function (Report $record): string {
                        $marks = (array) ($record->payload['marks'] ?? []);
                        $checked = count(array_filter($marks, fn ($mark): bool => in_array($mark, ['ok', 'bad'], true)));
                        $ok = count(array_filter($marks, fn ($mark): bool => $mark === 'ok'));

                        return $ok.' / '.$checked;
                    })
                    ->badge()
                    ->color(fn (Report $record): string => in_array('bad', (array) ($record->payload['marks'] ?? []), true) ? 'warning' : 'success'),
                TextColumn::make('failed')
                    ->label(__('work_item.control_tab.columns.failed'))
                    ->state(fn (Report $record): array => array_values(array_map(
                        fn (string $criterion): string => $catalog->criterionLabel($criterion),
                        array_keys(array_filter((array) ($record->payload['marks'] ?? []), fn ($mark): bool => $mark === 'bad')),
                    )))
                    ->badge()
                    ->color('danger')
                    ->placeholder('–'),
                TextColumn::make('note')
                    ->label(__('work_item.control_tab.columns.note'))
                    ->state(fn (Report $record): ?string => $record->payload['note'] ?? null)
                    ->wrap()
                    ->placeholder('–'),
                TextColumn::make('author.full_name')
                    ->label(__('work_item.control_tab.columns.controller'))
                    ->placeholder('–'),
            ])
            ->headerActions([
                Action::make('matrix')
                    ->label(__('work_item.actions.open_matrix'))
                    ->icon(Heroicon::OutlinedTableCells)
                    ->color('gray')
                    ->visible(fn (): bool => ControlMatrix::canAccess())
                    ->url(fn (): string => ControlMatrix::getUrl()),
            ])
            ->emptyStateHeading(__('work_item.control_tab.empty'))
            ->emptyStateIcon(Heroicon::OutlinedTableCells);
    }
}
