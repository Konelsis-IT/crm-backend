<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Tables;

use App\Enums\Document\DocumentStatus;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class DocumentTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_no')
                    ->label(__('document.fields.document_no'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('document.fields.title'))
                    ->searchable()
                    ->sortable()
                    ->limit(60),
                TextColumn::make('documentType.name')
                    ->label(__('document.fields.document_type'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('owner.full_name')
                    ->label(__('document.fields.owner'))
                    ->placeholder('-'),
                TextColumn::make('ownerOrgUnit.name')
                    ->label(__('document.fields.owner_org_unit'))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('project.name')
                    ->label(__('document.fields.project'))
                    ->placeholder('-')
                    ->toggleable()
                    ->visible(fn (): bool => SchemaReadiness::hasBatch('B17')),
                TextColumn::make('currentRevision.revision_code')
                    ->label(__('document.fields.current_revision'))
                    ->placeholder('-'),
                IconColumn::make('is_controlled')
                    ->label(__('document.fields.is_controlled'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('document.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('document.fields.status'))
                    ->options(DocumentStatus::class),
                SelectFilter::make('document_type_id')
                    ->label(__('document.fields.document_type'))
                    ->relationship('documentType', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('project_id')
                    ->label(__('document.fields.project'))
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => SchemaReadiness::hasBatch('B17')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('document_no', 'desc');
    }
}
