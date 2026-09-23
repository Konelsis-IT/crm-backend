<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkItems\Schemas;

use App\Enums\Report\WorkItemLinkKind;
use App\Enums\Report\WorkItemStatus;
use App\Enums\Report\WorkWaitingKind;
use App\Filament\Support\FieldGrid;
use App\Models\Personnel\Personnel;
use App\Query\Personnel\PersonnelQueries;
use App\Query\Report\WorkItemQueries;
use App\Services\Platform\SchemaReadiness;
use App\Reports\Work\WorkCategoryCatalog;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Kart formu (Isler > Yeni is / Duzenle; B36, D-115). Panodaki "Yeni kalem"
 * penceresiyle ayni alanlar: isin adi, tarih ve saat (kurum saati), sorumlu,
 * departman, proje, ana is, kategori (sorumlunun departman seti), durum,
 * kritik, termin, kimden bekleniyor, bagli kayit, harcanan saat, not.
 * Kayit WorkItemService'ten gecer (gecis kurali, sure kovalari, hareket).
 */
final class WorkItemForm
{
    public static function make(Schema $schema): Schema
    {
        // Bolumler alt alta tam genislikte durur; 12 sutunluk izgara bolumun
        // ICINDE kullanilir (23 Eylul 2026: duzenleme sayfasi sikismis
        // sutunlar halinde cikiyordu).
        return $schema->columns(1)->components(FieldGrid::group([
            TextInput::make('title')
                ->label(__('work_item.fields.title'))
                ->required()
                ->maxLength(200)
                ->columnSpanFull(),
            Select::make('personnel_id')
                ->label(__('work_item.fields.personnel'))
                ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                ->default(fn () => auth()->id())
                ->required()
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('category_code', null))
                ->native(false)
                ->columnSpan(FieldGrid::NORMAL),
            Select::make('org_unit_id')
                ->label(__('work_item.fields.org_unit'))
                ->helperText(__('work_item.help.org_unit'))
                ->options(fn (): array => array_map(fn (array $unit): string => $unit['name'], app(WorkItemQueries::class)->units()))
                ->placeholder(__('work_item.values.unit_of_personnel'))
                ->native(false)
                ->columnSpan(FieldGrid::NORMAL),
            Select::make('project_id')
                ->label(__('work_item.fields.project'))
                ->options(fn (): array => app(WorkItemQueries::class)->projectNames())
                ->searchable()
                ->placeholder(__('work_item.values.no_project'))
                ->native(false)
                ->columnSpan(FieldGrid::NORMAL),
            Select::make('parent_id')
                ->label(__('work_item.fields.parent'))
                ->helperText(__('work_item.help.parent'))
                ->options(fn (Get $get, ?\App\Models\Report\WorkItem $record): array => app(WorkItemQueries::class)->parentOptions(
                    (int) ($get('personnel_id') ?: auth()->id()),
                    $record?->getKey() !== null ? (int) $record->getKey() : null,
                ))
                ->searchable()
                ->placeholder('–')
                ->native(false)
                ->columnSpan(FieldGrid::NORMAL),
            Select::make('category_code')
                ->label(__('work_item.fields.category'))
                ->options(fn (Get $get): array => app(WorkCategoryCatalog::class)->optionsFor(self::unitCodeOf($get('personnel_id'))))
                ->placeholder('–')
                ->native(false),
            ToggleButtons::make('status')
                ->label(__('work_item.fields.status'))
                ->options(WorkItemStatus::class)
                ->default(WorkItemStatus::Planned->value)
                ->inline()
                ->live()
                ->required()
                ->columnSpan(FieldGrid::FULL),
            Toggle::make('is_critical')
                ->label(__('work_item.fields.is_critical'))
                ->inline(false),
            DatePicker::make('work_on')
                ->label(__('work_item.fields.work_on'))
                ->helperText(__('work_item.help.work_on'))
                ->displayFormat('d.m.Y')
                ->default(fn (): string => WorkItemQueries::today()->toDateString())
                ->required(),
            TimePicker::make('work_time')
                ->label(__('work_item.fields.work_time'))
                ->seconds(false)
                ->default(fn (): string => now(\App\Support\DisplayTime::zone())->format('H:i')),
            DatePicker::make('due_on')
                ->label(__('work_item.fields.due_on'))
                ->displayFormat('d.m.Y'),
            TextInput::make('work_hours')
                ->label(__('work_item.fields.work_hours'))
                ->numeric()
                ->step(0.25)
                ->minValue(0)
                ->maxValue(999),
            Select::make('requester_kind')
                ->label(__('work_item.fields.requester_kind'))
                ->helperText(__('work_item.help.requester'))
                ->options(WorkWaitingKind::class)
                ->placeholder(__('work_item.values.requester_none'))
                ->live()
                ->native(false)
                ->visible(fn (): bool => SchemaReadiness::hasBatch('B37'))
                ->columnSpan(FieldGrid::NORMAL),
            Select::make('requester_personnel_id')
                ->label(__('work_item.fields.requester_personnel'))
                ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                ->searchable()
                ->native(false)
                ->visible(fn (Get $get): bool => SchemaReadiness::hasBatch('B37') && self::value($get('requester_kind')) === WorkWaitingKind::Personnel->value)
                ->columnSpan(FieldGrid::HALF),
            Select::make('requester_party_id')
                ->label(__('work_item.fields.requester_party'))
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => app(WorkItemQueries::class)->partyOptions($search))
                ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? (app(WorkItemQueries::class)->partyOptions('', (int) $value)[(int) $value] ?? null) : null)
                ->native(false)
                ->visible(fn (Get $get): bool => SchemaReadiness::hasBatch('B37') && self::value($get('requester_kind')) === WorkWaitingKind::Party->value)
                ->columnSpan(FieldGrid::HALF),
            TextInput::make('requester_text')
                ->label(__('work_item.fields.requester_text'))
                ->maxLength(200)
                ->visible(fn (Get $get): bool => SchemaReadiness::hasBatch('B37') && self::value($get('requester_kind')) === WorkWaitingKind::Text->value)
                ->columnSpan(FieldGrid::HALF),
            Select::make('waiting_kind')
                ->label(__('work_item.fields.waiting_kind'))
                ->helperText(__('work_item.help.waiting'))
                ->options(WorkWaitingKind::class)
                ->placeholder('–')
                ->live()
                ->required(fn (Get $get): bool => self::value($get('status')) === WorkItemStatus::Waiting->value)
                ->native(false)
                ->columnSpan(FieldGrid::NORMAL),
            Select::make('waiting_personnel_id')
                ->label(__('work_item.fields.waiting_personnel'))
                ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                ->searchable()
                ->native(false)
                ->visible(fn (Get $get): bool => self::value($get('waiting_kind')) === WorkWaitingKind::Personnel->value)
                ->required(fn (Get $get): bool => self::value($get('status')) === WorkItemStatus::Waiting->value && self::value($get('waiting_kind')) === WorkWaitingKind::Personnel->value)
                ->columnSpan(FieldGrid::HALF),
            Select::make('waiting_party_id')
                ->label(__('work_item.fields.waiting_party'))
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => app(WorkItemQueries::class)->partyOptions($search))
                ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? (app(WorkItemQueries::class)->partyOptions('', (int) $value)[(int) $value] ?? null) : null)
                ->native(false)
                ->visible(fn (Get $get): bool => self::value($get('waiting_kind')) === WorkWaitingKind::Party->value)
                ->columnSpan(FieldGrid::HALF),
            TextInput::make('waiting_text')
                ->label(__('work_item.fields.waiting_text'))
                ->maxLength(200)
                ->visible(fn (Get $get): bool => in_array(self::value($get('waiting_kind')), [WorkWaitingKind::Text->value, WorkWaitingKind::Party->value], true))
                ->required(fn (Get $get): bool => self::value($get('status')) === WorkItemStatus::Waiting->value && self::value($get('waiting_kind')) === WorkWaitingKind::Text->value)
                ->columnSpan(FieldGrid::HALF),
            Select::make('link_kind')
                ->label(__('work_item.fields.link_kind'))
                ->options(WorkItemLinkKind::linkOptions())
                ->placeholder('–')
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('link_id', null))
                ->native(false)
                ->columnSpan(FieldGrid::NORMAL),
            Select::make('link_id')
                ->label(__('work_item.fields.link'))
                ->searchable()
                ->getSearchResultsUsing(fn (string $search, Get $get): array => app(WorkItemQueries::class)->linkOptions(self::linkKind($get('link_kind')), $search))
                ->getOptionLabelUsing(fn ($value, Get $get): ?string => filled($value) ? (app(WorkItemQueries::class)->linkOptions(self::linkKind($get('link_kind')), '', (int) $value)[(int) $value] ?? null) : null)
                ->native(false)
                ->visible(fn (Get $get): bool => filled($get('link_kind')) && self::linkKind($get('link_kind')) !== WorkItemLinkKind::None)
                ->columnSpan(FieldGrid::HALF),
            Textarea::make('note')
                ->label(__('work_item.fields.note'))
                ->rows(3)
                ->maxLength(2000)
                ->columnSpanFull(),
            Hidden::make('row_version'),
        ], [
            'main' => [
                'label' => __('work_item.sections.main'),
                'icon' => Heroicon::OutlinedClipboardDocumentCheck,
                'fields' => ['title', 'personnel_id', 'org_unit_id', 'project_id', 'parent_id', 'category_code', 'status', 'is_critical', 'requester_kind', 'requester_personnel_id', 'requester_party_id', 'requester_text'],
            ],
            'dates' => [
                'label' => __('work_item.sections.dates'),
                'icon' => Heroicon::OutlinedCalendarDays,
                'fields' => ['work_on', 'work_time', 'due_on', 'work_hours'],
            ],
            'waiting' => [
                'label' => __('work_item.sections.waiting'),
                'icon' => Heroicon::OutlinedLink,
                'fields' => ['waiting_kind', 'waiting_personnel_id', 'waiting_party_id', 'waiting_text', 'link_kind', 'link_id'],
            ],
            'note' => [
                'label' => __('work_item.sections.note'),
                'icon' => Heroicon::OutlinedChatBubbleBottomCenterText,
                'fields' => ['note'],
            ],
        ]));
    }

    private static function value(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }

    private static function linkKind(mixed $value): WorkItemLinkKind
    {
        return WorkItemLinkKind::tryFrom(self::value($value)) ?? WorkItemLinkKind::None;
    }

    private static function unitCodeOf(mixed $personnelId): ?string
    {
        $id = filled($personnelId) ? (int) $personnelId : (int) auth()->id();
        $unit = app(WorkItemQueries::class)->people()[$id]['unit'] ?? null;

        return $unit !== null ? (app(WorkItemQueries::class)->units()[$unit]['code'] ?? null) : (auth()->user() instanceof Personnel ? auth()->user()->orgUnit?->code : null);
    }
}
