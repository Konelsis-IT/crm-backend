<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\RelationManagers;

use App\Enums\Report\ReportSubjectKind;
use App\Filament\Resources\Reports\Pages\CreateReport;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Personnel\Personnel;
use App\Models\Report\Report;
use App\Query\Report\ReportQueries;
use App\Reports\ReportTemplateRegistry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Ilgili kaydin (personel, proje, teklif, is dosyasi) altindaki raporlar
 * (D-86). Sahip modelin `reports()` iliskisi konu FK kolonuna baglidir.
 * "Rapor yaz" bu kayit on secili olarak rapor olusturma sayfasina goturur;
 * liste yalniz gorme yetkisi olan raporlari icerir.
 */
class SubjectReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'reports';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedDocumentChartBar;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('report.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return ReportResource::canAccess()
            && ReportSubjectKind::forModel($ownerRecord) !== ReportSubjectKind::None;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $kind = ReportSubjectKind::forModel($this->getOwnerRecord());

        return $table
            ->heading(__('report.plural'))
            ->description(__('report.relation.help'))
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
                TextColumn::make('author.full_name')->label(__('report.fields.author')),
                TextColumn::make('status')->label(__('report.fields.status'))->badge(),
                TextColumn::make('submitted_at')->label(__('report.fields.submitted_at'))->dateTime('d.m.Y H:i')->placeholder('-'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => app(ReportQueries::class)
                ->applyVisible($query->with(['author']), $user instanceof Personnel ? $user : null))
            ->headerActions([
                Action::make('write_report')
                    ->label(__('report.actions.create'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (): bool => $user instanceof Personnel
                        && $kind !== ReportSubjectKind::None
                        && array_filter(app(ReportTemplateRegistry::class)->forSubject($kind), fn ($template): bool => app(ReportQueries::class)->canAuthorTemplate($template, $user)) !== [])
                    ->url(fn (): string => ReportResource::getUrl('create', [
                        CreateReport::QUERY_SUBJECT_KIND => $kind->value,
                        CreateReport::QUERY_SUBJECT_ID => $this->getOwnerRecord()->getKey(),
                    ])),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('report.actions.open'))
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Report $record): string => ReportResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('report.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentChartBar);
    }
}
