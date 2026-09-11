<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\Tables;

use App\Enums\Personnel\PersonnelStatus;
use App\Filament\Resources\Personnel\Actions\PersonnelStatusActions;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Support\BrandIcons;
use App\Filament\Support\PersonnelAvatar;
use App\Models\Personnel\Personnel;
use App\Services\Platform\SchemaReadiness;
use App\Support\ContactLinks;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PersonnelTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['orgUnit', 'competencies', 'roles']))
            ->columns(self::listColumns())
            ->filters([
                SelectFilter::make('status')
                    ->label(__('personnel.fields.status'))
                    ->options(PersonnelStatus::class),
                SelectFilter::make('orgUnit')
                    ->label(__('personnel.fields.department'))
                    ->relationship('orgUnit', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('competencies')
                    ->label(__('personnel.fields.competencies'))
                    ->relationship('competencies', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    self::whatsappAction(),
                    self::chatAction(),
                    self::callAction(),
                    self::mailAction(),
                ])
                    ->label(__('personnel.actions.contact'))
                    ->tooltip(__('personnel.actions.contact'))
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->dropdown(),
                PersonnelStatusActions::group(),
            ])
            ->toolbarActions([])
            ->defaultSort('full_name');
    }


    /**
     * Klasik satir gorunumu.
     *
     * @return array<int, mixed>
     */
    private static function listColumns(): array
    {
        return [
            ImageColumn::make('photo_path')
                ->label(__('personnel.fields.photo'))
                ->disk('public')
                ->circular()
                ->defaultImageUrl(fn (Personnel $record): string => PersonnelAvatar::url($record)),
            TextColumn::make('full_name')
                ->label(__('personnel.fields.full_name'))
                ->searchable()
                ->sortable()
                ->weight('medium')
                ->description(fn (Personnel $record): ?string => $record->job_title),
            TextColumn::make('orgUnit.name')
                ->label(__('personnel.fields.department'))
                ->badge()
                ->color('gray')
                ->sortable()
                ->placeholder('-'),
            TextColumn::make('competencies.name')
                ->label(__('personnel.fields.competencies'))
                ->badge()
                ->placeholder('-')
                ->limitList(3)
                ->expandableLimitedList(),
            TextColumn::make('phone')
                ->label(__('personnel.fields.phone'))
                ->icon(Heroicon::OutlinedPhone)
                ->url(fn (Personnel $record): ?string => ContactLinks::tel($record->phone))
                ->placeholder('-'),
            TextColumn::make('email')
                ->label(__('personnel.fields.email'))
                ->icon(Heroicon::OutlinedEnvelope)
                ->url(fn (Personnel $record): ?string => ContactLinks::mailto($record->email))
                ->searchable()
                ->toggleable(),
            TextColumn::make('status')
                ->label(__('personnel.fields.status'))
                ->badge()
                ->sortable(),
            TextColumn::make('roles.name')
                ->label(__('role.fields.roles'))
                ->badge()
                ->color('warning')
                ->placeholder('-')
                ->visible(fn (): bool => SchemaReadiness::hasBatch('B05')),
            TextColumn::make('last_login_at')
                ->label(__('personnel.fields.last_login_at'))
                ->icon(Heroicon::OutlinedClock)
                ->dateTime('d.m.Y H:i')
                ->sortable()
                ->placeholder('-')
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }


    /** WhatsApp sohbetini acar. */
    private static function whatsappAction(): Action
    {
        return Action::make('whatsapp')
            ->label(__('personnel.actions.whatsapp'))
            ->icon(BrandIcons::whatsapp())
            ->color('success')
            ->visible(fn (Personnel $record): bool => ContactLinks::whatsapp($record->phone) !== null)
            ->url(fn (Personnel $record): string => (string) ContactLinks::whatsapp($record->phone), shouldOpenInNewTab: true);
    }

    /** Kurum ici sohbeti (React) bu kisiyle acar; kendi satirinda gorunmez. */
    private static function chatAction(): Action
    {
        return Action::make('start_chat')
            ->label(__('personnel.actions.start_chat'))
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('primary')
            ->visible(fn (Personnel $record): bool => ! $record->is(Auth::user()))
            ->url(fn (Personnel $record): string => PersonnelResource::getUrl('index', ['sohbet_kisi' => $record->getKey()]));
    }

    /** Telefon uygulamasinda aramayi baslatir. */
    private static function callAction(): Action
    {
        return Action::make('call')
            ->label(__('personnel.actions.call'))
            ->icon(Heroicon::OutlinedPhone)
            ->visible(fn (Personnel $record): bool => ContactLinks::tel($record->phone) !== null)
            ->url(fn (Personnel $record): string => (string) ContactLinks::tel($record->phone));
    }

    /** Varsayilan e-posta uygulamasinda yeni ileti acar. */
    private static function mailAction(): Action
    {
        return Action::make('mail')
            ->label(__('personnel.actions.mail'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->url(fn (Personnel $record): string => (string) ContactLinks::mailto($record->email));
    }
}
