<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\RelationManagers;

use App\Filament\Resources\Reports\ReportResource;
use App\Models\Personnel\Personnel;
use App\Models\Report\Report;
use App\Query\Report\ReportQueries;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Personel karti > Yazdigi raporlar (23 Eylul 2026 kullanici bildirimi:
 * kisi rapor yazdi ama kartinda gorunmuyordu). "Raporlar" sekmesi kisi
 * HAKKINDA yazilanlari gosterir; bu sekme kisinin KENDI yazdiklaridir.
 *
 * Liste yalniz bakanin gorme yetkisi olan raporlari icerir (gizli raporlar
 * ReportQueries::applyVisible ile suzulur).
 */
class AuthoredReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'authoredReports';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedPencilSquare;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('report.relation.authored');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return SchemaReadiness::hasBatch('B10A') && ReportResource::canAccess();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $viewer = auth()->user();
        $viewer = $viewer instanceof Personnel ? $viewer : null;

        return $table
            ->heading(__('report.relation.authored'))
            ->description(__('report.relation.authored_help'))
            ->modifyQueryUsing(fn (Builder $query): Builder => app(ReportQueries::class)->applyVisible($query, $viewer))
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Report $record): string => ReportResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('report_no')->label(__('report.fields.report_no'))->badge()->color('gray'),
                TextColumn::make('title')->label(__('report.fields.title'))->weight('semibold')->wrap(),
                TextColumn::make('template_code')
                    ->label(__('report.fields.template'))
                    ->formatStateUsing(fn (Report $record): string => $record->templateName()),
                TextColumn::make('period')
                    ->label(__('report.fields.period'))
                    ->state(fn (Report $record): ?string => $record->periodLabel())
                    ->placeholder('-'),
                TextColumn::make('subject')
                    ->label(__('report.fields.subject'))
                    ->state(fn (Report $record): ?string => $record->subjectLabel())
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('status')->label(__('report.fields.status'))->badge(),
                TextColumn::make('submitted_at')
                    ->label(__('report.fields.submitted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('reviewer.full_name')
                    ->label(__('report.fields.reviewer'))
                    ->placeholder(__('report.values.no_reviewer'))
                    ->toggleable(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading(__('report.relation.authored_empty'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentChartBar);
    }
}
