<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Tables;

use App\Enums\Report\ReportKind;
use App\Enums\Report\ReportStatus;
use App\Models\Report\Report;
use App\Reports\ReportTemplateRegistry;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/** Rapor listesi (D-86). */
final class ReportTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('report_no')
                    ->label(__('report.fields.report_no'))
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('title')
                    ->label(__('report.fields.title'))
                    ->searchable()
                    ->weight('semibold')
                    ->description(fn (Report $record): ?string => filled($record->summary) ? Str::limit((string) $record->summary, 90) : null)
                    ->wrap(),
                TextColumn::make('template_code')
                    ->label(__('report.fields.template'))
                    ->formatStateUsing(fn (Report $record): string => $record->templateName())
                    ->toggleable(),
                TextColumn::make('kind')
                    ->label(__('report.fields.kind'))
                    ->badge(),
                TextColumn::make('subject')
                    ->label(__('report.fields.subject'))
                    ->state(fn (Report $record): ?string => $record->subjectLabel())
                    ->placeholder('-')
                    ->icon(fn (Report $record): ?Heroicon => $record->subjectLabel() !== null ? $record->subject_kind->getIcon() : null)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('period')
                    ->label(__('report.fields.period'))
                    ->state(fn (Report $record): ?string => $record->periodLabel())
                    ->placeholder('-'),
                TextColumn::make('author.full_name')
                    ->label(__('report.fields.author'))
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('report.fields.status'))
                    ->badge(),
                TextColumn::make('reviewer.full_name')
                    ->label(__('report.fields.reviewer'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('submitted_at')
                    ->label(__('report.fields.submitted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('report.fields.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('report.fields.status'))
                    ->options(ReportStatus::class),
                SelectFilter::make('kind')
                    ->label(__('report.fields.kind'))
                    ->options(ReportKind::class),
                SelectFilter::make('template_code')
                    ->label(__('report.fields.template'))
                    ->options(fn (): array => app(ReportTemplateRegistry::class)->options()),
            ])
            ->recordActions([
                ViewAction::make()->label(__('report.actions.open')),
            ])
            ->toolbarActions([])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'author', 'reviewer', 'subjectPersonnel', 'subjectProject', 'subjectComponent', 'subjectProposal', 'subjectBusinessCase',
            ]))
            ->defaultSort('created_at', 'desc');
    }
}
