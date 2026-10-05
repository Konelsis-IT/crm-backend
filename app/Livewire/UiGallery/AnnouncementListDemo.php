<?php

declare(strict_types=1);

namespace App\Livewire\UiGallery;

use App\Enums\Platform\Feature;
use App\Filament\Pages\UiListGallery;
use App\Query\Ui\AnnouncementGalleryQueries;
use App\Services\Platform\FeatureFlags;
use Filament\Actions\Action;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * UI Deneme > Liste denemeleri (D-139): duyuru listesinin tablo tabanli
 * denemeleri. Pano bileseniyle ayni kart (baslik + aciklama) icinde, dizi
 * verili tablo; hangi deneme oldugu $variant ile gelir. Panodaki gibi yarim
 * genislikte gosterilir. Pano widget'lari arasinda kesfedilmesin diye
 * app/Filament/Widgets disindadir.
 */
class AnnouncementListDemo extends TableWidget
{
    public const INBOX = 'inbox';

    public const PANEL = 'panel';

    public const CARDS = 'cards';

    public const GROUPED = 'grouped';

    public const STRIPE = 'stripe';

    public string $variant = self::INBOX;

    protected int | string | array $columnSpan = 'full';

    public function mount(): void
    {
        abort_unless(FeatureFlags::enabled(Feature::UiGallery) && UiListGallery::canAccess(), 403);
    }

    public function table(Table $table): Table
    {
        $table = $table
            ->heading(__('announcement.widget.heading'))
            ->description(__('announcement.widget.description'))
            ->records(fn (): array => app(AnnouncementGalleryQueries::class)->items()['items'])
            ->paginated(false)
            ->emptyStateHeading(__('announcement.widget.empty'))
            ->emptyStateIcon(Heroicon::OutlinedMegaphone)
            // "open" adli eylem satir tiklamasiyla calisir, dugmesi gizlidir (D-125).
            ->recordActions([$this->readAction()])
            ->recordAction('open');

        return match ($this->variant) {
            self::PANEL => $this->panel($table),
            self::CARDS => $this->cards($table),
            self::GROUPED => $this->grouped($table),
            self::STRIPE => $this->stripe($table),
            default => $this->inbox($table),
        };
    }

    /** 1: baslik + iki satir metin genis alanda; kunye tek satir; tarih sagda. */
    private function inbox(Table $table): Table
    {
        return $table->columns([
            Split::make([
                Stack::make([
                    $this->title(),
                    TextColumn::make('body')
                        ->color('gray')
                        ->wrap()
                        ->lineClamp(2),
                    TextColumn::make('meta')
                        ->size(TextSize::ExtraSmall)
                        ->color('gray'),
                ])->space(1),
                TextColumn::make('since')
                    ->size(TextSize::ExtraSmall)
                    ->color('gray')
                    ->tooltip(fn (array $record): string => $record['date'])
                    ->grow(false)
                    ->alignEnd(),
            ]),
        ]);
    }

    /** 2: satirda baslik + kunye; metin oka basinca ayni yerde acilir. */
    private function panel(Table $table): Table
    {
        return $table->columns([
            Split::make([
                Stack::make([
                    $this->title(),
                    TextColumn::make('meta_full')
                        ->size(TextSize::ExtraSmall)
                        ->color('gray'),
                ])->space(1),
            ]),
            Panel::make([
                TextColumn::make('body')->wrap(),
            ])->collapsible(),
        ]);
    }

    /** 3: esit boy kartlar. */
    private function cards(Table $table): Table
    {
        return $table
            ->contentGrid(['md' => 1, '2xl' => 2])
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('priority_label')
                            ->badge()
                            ->color(fn (array $record): string => $record['tone'])
                            ->icon(fn (array $record): Heroicon => $record['priority']->getIcon()),
                        TextColumn::make('since')
                            ->size(TextSize::ExtraSmall)
                            ->color('gray')
                            ->alignEnd(),
                    ]),
                    TextColumn::make('title')
                        ->weight(FontWeight::Bold)
                        ->wrap(),
                    TextColumn::make('body')
                        ->color('gray')
                        ->wrap()
                        ->lineClamp(3),
                    TextColumn::make('meta')
                        ->size(TextSize::ExtraSmall)
                        ->color('gray')
                        ->icon(Heroicon::OutlinedUserCircle),
                ])->space(2),
            ]);
    }

    /** 4: gun basliklari altinda saat + baslik + tek satir metin. */
    private function grouped(Table $table): Table
    {
        return $table
            ->groups([
                Group::make('day_key')
                    ->label(__('ui_gallery.lists.day'))
                    ->getTitleFromRecordUsing(fn (array $record): string => $record['day_label'])
                    ->titlePrefixedWithLabel(false),
            ])
            ->defaultGroup('day_key')
            ->groupingSettingsHidden()
            ->columns([
                Split::make([
                    TextColumn::make('time')
                        ->color('gray')
                        ->size(TextSize::Small)
                        ->grow(false),
                    Stack::make([
                        $this->title(),
                        TextColumn::make('body')
                            ->color('gray')
                            ->wrap()
                            ->lineClamp(1),
                    ]),
                ]),
            ]);
    }

    /** 5: onem soldaki renkli seritte; satirda baslik + kunye; metin sagdan acilan panelde. */
    private function stripe(Table $table): Table
    {
        return $table
            ->recordClasses(fn (array $record): string => 'kc-tone-'.$record['tone'])
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('title')
                            ->weight(FontWeight::Medium)
                            ->wrap(),
                        TextColumn::make('meta')
                            ->size(TextSize::ExtraSmall)
                            ->color('gray'),
                    ]),
                    TextColumn::make('day_short')
                        ->size(TextSize::ExtraSmall)
                        ->color('gray')
                        ->tooltip(fn (array $record): string => $record['date'])
                        ->grow(false)
                        ->alignEnd(),
                ]),
            ]);
    }

    private function title(): TextColumn
    {
        return TextColumn::make('title')
            ->weight(FontWeight::SemiBold)
            ->wrap()
            ->icon(fn (array $record): Heroicon => $record['priority']->getIcon())
            ->iconColor(fn (array $record): string => $record['tone']);
    }

    /** Tam metin: seritli denemede sagdan acilan panel, digerlerinde pencere. */
    private function readAction(): Action
    {
        return Action::make('open')
            ->label(__('announcement.actions.read'))
            ->icon(Heroicon::OutlinedEye)
            ->modalHeading(fn (array $record): string => $record['title'])
            ->modalDescription(fn (array $record): string => $record['meta_full'])
            ->modalIcon(fn (array $record): Heroicon => $record['priority']->getIcon())
            ->modalIconColor(fn (array $record): string => $record['tone'])
            ->modalWidth(Width::TwoExtraLarge)
            ->slideOver($this->variant === self::STRIPE)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('announcement.actions.close'))
            ->schema(fn (array $record): array => [
                Text::make($record['body']),
            ]);
    }
}
