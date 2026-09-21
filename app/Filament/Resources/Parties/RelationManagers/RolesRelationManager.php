<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties\RelationManagers;

use App\Enums\Party\PartyRoleCode;
use App\Enums\Party\PartyRoleStatus;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Party\PartyRole;
use App\Services\Party\PartyRoleService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RolesRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedTag;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('party_role.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        // Pencere tek bolumludur; bolum basligi geri getirildi (16 Eylul 2026
        // kullanici karari: duz pencere begenilmedi). Yillik degerlendirme
        // penceresiyle ayni sarmalayici kullanilir. Tarihler gun bazlidir ve
        // tedarik kalemi penceresiyle ayni boyut/tiptedir.
        return $schema->columns(1)->components([
            Section::make(__('party_role.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('role_code')
                            ->label(__('party_role.fields.role_code'))
                            ->options(PartyRoleCode::availableOptions())
                            ->default(PartyRoleCode::Customer->value)
                            ->required()
                            ->native(false),
                        Select::make('status')
                            ->label(__('party_role.fields.status'))
                            ->options(PartyRoleStatus::class)
                            ->default(PartyRoleStatus::Active->value)
                            ->required()
                            ->native(false),
                        Select::make('approved_by_personnel_id')
                            ->label(__('party_role.fields.approved_by'))
                            ->relationship('approver', 'full_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        DatePicker::make('valid_from')
                            ->label(__('party_role.fields.valid_from'))
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('valid_until')
                            ->label(__('party_role.fields.valid_until'))
                            ->displayFormat('d.m.Y'),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('party_role.label'))
            ->heading(__('party_role.relation.title'))
            ->recordTitleAttribute('role_code')
            ->columns([
                TextColumn::make('role_code')
                    ->label(__('party_role.fields.role_code'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('party_role.fields.status'))
                    ->badge(),
                TextColumn::make('valid_from')
                    ->label(__('party_role.fields.valid_from'))
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->label(__('party_role.fields.valid_until'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
                TextColumn::make('approver.full_name')
                    ->label(__('party_role.fields.approver'))
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['party_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(PartyRoleService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (PartyRole $record, array $data): Model {
                        try {
                            return app(PartyRoleService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (PartyRole $record): bool {
                        try {
                            return app(PartyRoleService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('valid_from', 'desc')
            ->emptyStateHeading(__('party_role.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedTag);
    }
}
