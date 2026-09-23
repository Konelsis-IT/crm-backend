<?php

declare(strict_types=1);

namespace App\Filament\Resources\Positions\Schemas;

use App\Models\Personnel\Position;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Pozisyon detayi (D-116, 23 Eylul 2026 kullanici istegi: "Ayni durum
 * pozisyonlar icinde gecerlidir"): kadro bilgileri; bu kadroya atanan
 * personel sekmede gelir.
 */
final class PositionInfolist
{
    public static function make(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('position.sections.main'))
                ->icon(Heroicon::OutlinedIdentification)
                ->columns(['default' => 1, 'md' => 3])
                ->components([
                    TextEntry::make('title')->label(__('position.fields.title'))->weight('semibold'),
                    TextEntry::make('code')->label(__('position.fields.code'))->badge()->color('gray'),
                    TextEntry::make('orgUnit.name')->label(__('position.fields.org_unit'))->icon(Heroicon::OutlinedBuildingOffice2)->placeholder('–'),
                    TextEntry::make('grade')->label(__('position.fields.grade'))->placeholder('–'),
                    TextEntry::make('managerial_level')->label(__('position.fields.managerial_level')),
                    TextEntry::make('headcount')->label(__('position.fields.headcount')),
                    TextEntry::make('assignee_count')
                        ->label(__('position.fields.assignee_count'))
                        ->state(fn (Position $record): int => $record->assignments()->whereNull('valid_until')->count())
                        ->badge(),
                    TextEntry::make('status')->label(__('position.fields.status'))->badge(),
                ]),
        ]);
    }
}
