<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\RelationManagers;

use App\Enums\Document\DocumentShareStatus;
use App\Exceptions\AbstractException;
use App\Filament\Support\DocumentWorkspace;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Document\DocumentShare;
use App\Services\Document\DocumentShareService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Dokumanin paylasim baglantilari (D-75): baglanti simdilik yetkisiz erisime
 * aciktir; etiket, indirme izni ve son kullanma tarihi verilir, iptal edilir.
 */
class SharesRelationManager extends RelationManager
{
    protected static string $relationship = 'shares';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedShare;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('document_share.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('document_share.sections.main'))
                ->description(__('document.help.share_public'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextInput::make('label')
                        ->label(__('document_share.fields.label'))
                        ->helperText(__('document_share.help.label'))
                        ->maxLength(120)
                        ->columnSpanFull(),
                    Toggle::make('allow_download')
                        ->label(__('document_share.fields.allow_download'))
                        ->default(true),
                    DatePicker::make('expires_at')
                        ->label(__('document_share.fields.expires_at'))
                        ->displayFormat('d.m.Y')
                        ->helperText(__('document_share.help.expires_at')),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('document_share.plural'))
            ->description(__('document_share.help.relation'))
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label')
                    ->label(__('document_share.fields.label'))
                    ->placeholder('-')
                    ->weight('semibold'),
                TextColumn::make('url')
                    ->label(__('document_share.fields.url'))
                    ->getStateUsing(fn (DocumentShare $record): string => DocumentWorkspace::shareUrl($record))
                    ->copyable()
                    ->copyMessage(__('document_share.messages.copied'))
                    ->icon(Heroicon::OutlinedClipboard)
                    ->limit(48)
                    ->url(fn (DocumentShare $record): string => DocumentWorkspace::shareUrl($record))
                    ->openUrlInNewTab(),
                TextColumn::make('status')
                    ->label(__('document_share.fields.status'))
                    ->badge(),
                IconColumn::make('allow_download')
                    ->label(__('document_share.fields.allow_download'))
                    ->boolean(),
                TextColumn::make('expires_at')
                    ->label(__('document_share.fields.expires_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder(__('document_share.values.no_expiry')),
                TextColumn::make('access_count')
                    ->label(__('document_share.fields.access_count'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('last_accessed_at')
                    ->label(__('document_share.fields.last_accessed_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
                TextColumn::make('createdBy.full_name')
                    ->label(__('document_share.fields.created_by'))
                    ->placeholder(__('activity.system')),
                TextColumn::make('created_at')
                    ->label(__('document_share.fields.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('document.actions.share'))
                    ->icon(Heroicon::OutlinedShare)
                    ->using(function (array $data): Model {
                        $data['document_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(DocumentShareService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('document.actions.open_share'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (DocumentShare $record): string => DocumentWorkspace::shareUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (DocumentShare $record): bool => $record->isOpen()),
                Action::make('revoke')
                    ->label(__('document_share.actions.revoke'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (DocumentShare $record): bool => $record->status === DocumentShareStatus::Active)
                    ->action(function (DocumentShare $record): void {
                        try {
                            app(DocumentShareService::class)->revoke($record);
                            DomainNotifications::success(__('document_share.messages.revoked'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('document_share.messages.empty'))
            ->emptyStateDescription(__('document_share.help.relation'))
            ->emptyStateIcon(Heroicon::OutlinedShare);
    }
}
