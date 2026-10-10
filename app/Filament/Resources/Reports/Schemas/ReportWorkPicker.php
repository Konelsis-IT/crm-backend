<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Schemas;

use App\Enums\Platform\Feature;
use App\Exceptions\AbstractException;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Personnel\Personnel;
use App\Models\Report\Report;
use App\Query\Report\ReportSuggestionQueries;
use App\Reports\ReportTemplate;
use App\Services\Platform\FeatureFlags;
use App\Services\Report\ReportSuggestions;
use App\Services\Report\WorkItemService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Rapor formunda is panosundan is ekleme (D-179, 8 Ekim 2026 kullanici
 * istegi: "daha onceden olusan bir is o raporda secilip eklenemiyor ... Is
 * onerisi sistemi de gelsin rapor olusturulurken, tek tikla da rapora
 * ekletilsin").
 *
 * - picker(): "Panodan is ekle" arama kutusu. Yazarin kendi kartlari ve ondan
 *   beklenen isler; donemin isleri, acik isler, diger isler gruplari. Secilen
 *   kart Gunu kapat ile ayni satir olarak is kalemlerine eklenir ve kartin
 *   kimligini tasir; raporda olan kart secilemez (soluk).
 * - suggestions(): "Oneriler" listesi. Donemin rapora girmemis kartlari ve is
 *   panosu onerileri (yazarin donemdeki hareketleri); her satirda "Ekle".
 *   Hareket onerisi eklenince is panosunda karta doner (Kart yap ile ayni) ve
 *   o kart rapora eklenir. Eklenmis olan "Eklendi" olarak isaretlenir.
 *
 * Ikisi de yalniz is panosu kullanan taslaklarda (gunluk / haftalik / aylik
 * calisma) ve kendi ozellik anahtariyla gorunur.
 */
final class ReportWorkPicker
{
    /**
     * Pano bolumune eklenen bilesenler (secim kutusu + oneriler).
     *
     * @param  Closure(Get): ?ReportTemplate  $template
     * @return list<Component|Select>
     */
    public static function components(Closure $template): array
    {
        return [self::picker($template), self::suggestions($template)];
    }

    public static function pickerEnabled(): bool
    {
        return FeatureFlags::enabled(Feature::Work) && FeatureFlags::enabled(Feature::ReportPickWorkItems);
    }

    public static function suggestionsEnabled(): bool
    {
        return FeatureFlags::enabled(Feature::Work) && FeatureFlags::enabled(Feature::ReportWorkSuggestions);
    }

    /**
     * @param  Closure(Get): ?ReportTemplate  $template
     */
    private static function picker(Closure $template): Select
    {
        $options = static function (Get $get, ?Model $record, string $search = '') use ($template): array {
            $current = $template($get);

            if ($current === null) {
                return [];
            }

            $suggestions = app(ReportSuggestions::class);

            return $suggestions->pickOptions(self::authorId($record), $suggestions->period($current, $get('period_start')), $search);
        };

        return Select::make('pick_work_item')
            ->label(__('report.pick.label'))
            ->placeholder(__('report.pick.placeholder'))
            ->helperText(__('report.pick.help'))
            ->options(fn (Get $get, ?Model $record): array => $options($get, $record))
            ->getSearchResultsUsing(fn (string $search, Get $get, ?Model $record): array => $options($get, $record, $search))
            ->searchable()
            ->optionsLimit(ReportSuggestionQueries::PICK_LIMIT)
            ->noSearchResultsMessage(__('report.pick.empty'))
            ->native(false)
            ->live()
            ->dehydrated(false)
            ->disableOptionWhen(fn (mixed $value, Get $get): bool => in_array((int) $value, self::linkedIds($get('items')), true))
            ->afterStateUpdated(function (mixed $state, Get $get, Set $set, ?Model $record): void {
                if (! filled($state)) {
                    return;
                }

                $set('pick_work_item', null);
                $row = app(ReportSuggestions::class)->pickedRow(self::authorId($record), (int) $state);

                self::appendWithNotice($row, $get, $set);
            })
            ->visible(fn (Get $get): bool => self::pickerEnabled() && self::usesBoard($template($get)))
            ->columnSpan(FieldGrid::WIDE);
    }

    /**
     * @param  Closure(Get): ?ReportTemplate  $template
     */
    private static function suggestions(Closure $template): Section
    {
        $rows = static function (Get $get, ?Model $record) use ($template): array {
            $current = $template($get);

            if ($current === null || ! self::suggestionsEnabled()) {
                return [];
            }

            $suggestions = app(ReportSuggestions::class);
            $viewer = auth()->user();
            $linked = self::linkedIds($get('items'));
            $rows = array_map(
                static fn (array $row): array => [...$row, 'added' => $row['kind'] === 'card' && in_array($row['id'], $linked, true)],
                $suggestions->boardSuggestions(
                    $current,
                    self::authorId($record),
                    $suggestions->period($current, $get('period_start')),
                    $viewer instanceof Personnel ? $viewer : null,
                ),
            );

            // Bekleyen oneriler once, eklenenler sonda.
            usort($rows, static fn (array $a, array $b): int => (int) $a['added'] <=> (int) $b['added']);

            return $rows;
        };

        return Section::make(fn (Get $get, ?Model $record): string => __('report.suggest.title', [
            'count' => count(array_filter($rows($get, $record), static fn (array $row): bool => ! $row['added'])),
        ]))
            ->description(__('report.suggest.help'))
            ->icon(Heroicon::OutlinedLightBulb)
            ->compact()
            ->collapsible()
            // Bekleyen oneri yoksa kapali gelir; eklenenler yine acilip gorulur.
            ->collapsed(fn (Get $get, ?Model $record): bool => array_filter($rows($get, $record), static fn (array $row): bool => ! $row['added']) === [])
            ->visible(fn (Get $get, ?Model $record): bool => $rows($get, $record) !== [])
            ->components(fn (Get $get, ?Model $record): array => array_map(
                static fn (array $row): Flex => self::suggestionRow($row),
                $rows($get, $record),
            ))
            ->columnSpan(FieldGrid::HALF);
    }

    /**
     * Tek oneri satiri: ad, kucuk gri ayrinti ve "Ekle" (ya da "Eklendi").
     *
     * @param  array{key: string, kind: string, id: int, title: string, meta: string|null, added: bool}  $row
     */
    private static function suggestionRow(array $row): Flex
    {
        $end = $row['added']
            ? Text::make(__('report.suggest.added'))->badge()->color('success')->grow(false)
            : Actions::make([
                Action::make('addSuggestion')
                    ->label(__('report.suggest.add'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->color(ActionColors::CREATE)
                    ->size(Size::Small)
                    ->action(function (Get $get, Set $set, ?Model $record) use ($row): void {
                        self::addSuggestion($row, $get, $set, $record);
                    }),
            ])->key('report-suggestion-add-'.md5($row['key']))->grow(false);

        return Flex::make([
            Group::make([
                Text::make($row['title'])->weight(FontWeight::Medium),
                ...(filled($row['meta']) ? [Text::make((string) $row['meta'])->color('gray')->size(TextSize::ExtraSmall)] : []),
            ]),
            $end,
        ])
            ->verticallyAlignCenter()
            ->key('report-suggestion-'.md5($row['key']));
    }

    /**
     * Oneriyi rapora ekler. Hareket onerisi once is panosunda karta doner
     * (WorkItemService::fromSuggestion, yalniz yazar kendisi); kart onerisi
     * dogrudan eklenir. Raporda olan kart ikinci kez eklenmez.
     *
     * @param  array{kind: string, id: int}  $row
     */
    private static function addSuggestion(array $row, Get $get, Set $set, ?Model $record): void
    {
        $suggestions = app(ReportSuggestions::class);
        $authorId = self::authorId($record);
        $cardId = (int) $row['id'];

        if ($row['kind'] === 'activity') {
            if ((int) auth()->id() !== $authorId) {
                return;
            }

            try {
                $cardId = (int) app(WorkItemService::class)->fromSuggestion((int) $row['id'], [])->getKey();
            } catch (AbstractException $exception) {
                DomainNotifications::failure($exception);

                return;
            }

            $suggestions->forget();
        }

        self::appendWithNotice($suggestions->pickedRow($authorId, $cardId), $get, $set);
    }

    /**
     * @param  array<string, mixed>|null  $row
     */
    private static function appendWithNotice(?array $row, Get $get, Set $set): void
    {
        if ($row === null) {
            Notification::make()->title(__('report.pick.not_found'))->danger()->send();

            return;
        }

        $items = is_array($get('items')) ? $get('items') : [];

        if (in_array((int) ($row['work_item_id'] ?? 0), self::linkedIds($items), true)) {
            Notification::make()->title(__('report.pick.exists'))->warning()->send();

            return;
        }

        $items[(string) Str::uuid()] = $row;
        $set('items', $items);

        Notification::make()->title(__('report.pick.added', ['title' => Str::limit((string) ($row['title'] ?? ''), 80)]))->success()->send();
    }

    /**
     * Is kalemlerinde bagli kart kimlikleri.
     *
     * @return list<int>
     */
    public static function linkedIds(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $ids = [];

        foreach ($items as $row) {
            if (is_array($row) && filled($row['work_item_id'] ?? null)) {
                $ids[] = (int) $row['work_item_id'];
            }
        }

        return $ids;
    }

    private static function usesBoard(?ReportTemplate $template): bool
    {
        return $template !== null && app(ReportSuggestions::class)->usesBoard($template);
    }

    /** Yeni raporda yazan, duzenlemede raporun yazari. */
    private static function authorId(?Model $record): int
    {
        return $record instanceof Report ? (int) $record->author_personnel_id : (int) auth()->id();
    }
}
