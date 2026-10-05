<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Notification\AnnouncementPriority;
use App\Enums\Platform\Feature;
use App\Filament\Resources\Notifications\NotificationResource;
use App\Filament\Support\ActionColors;
use App\Models\Notification\Announcement;
use App\Query\Notification\AnnouncementQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use Filament\Actions\Action;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/**
 * Pano: son duyurular (D-82). Herkes gorur.
 *
 * D-146 (30 Eylul 2026 kullanici tasarimi): son 3 duyuru, her biri bir kart;
 * solda onem simgesi (Normal kirmizi mesale), baslik, metin ve altta tarih ·
 * gonderen · kitle. "Tümünü gör" Tüm bildirimler sayfasini acar (duyurular
 * zil bildirimi olarak oraya duser).
 *
 * D-150 (30 Eylul 2026 kullanici istegi: "uzadikca uzuyor ... 3 5 satir
 * ozeti yazip uzerine tiklandiginda detayli gorulebilmelidir"): kart yalniz
 * basligi (en cok 2 satir) ve metnin ozetini (en cok 4 satir) gosterir; karta
 * tiklayinca duyurunun tamami pencerede acilir, baglanti varsa pencerenin
 * altinda "Bağlantıyı aç". Kartlar tablo satiridir (satir tiklamasi, D-125).
 */
class AnnouncementsWidget extends TableWidget
{
    /** Genel bakistan duyuru gonderilince (Dashboard) kutu yeniden cizilir. */
    public const SENT_EVENT = 'announcement-sent';

    private const LIMIT = 3;

    protected static ?int $sort = 20;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return SchemaReadiness::hasBatch('B11A')
            && FeatureFlags::enabled(Feature::DashboardAnnouncements)
            && FeatureFlags::enabled(Feature::Announcements);
    }

    /**
     * Gonderen kendi duyurusunu sayfayi yenilemeden gorur (D-148); kartlar
     * her cizimde yeniden okunur.
     */
    #[On(self::SENT_EVENT)]
    public function refreshAnnouncements(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('announcement.widget.heading'))
            ->query(fn () => app(AnnouncementQueries::class)->recent(self::LIMIT))
            ->headerActions([
                Action::make('show_all')
                    ->label(__('announcement.widget.show_all'))
                    ->link()
                    ->color('primary')
                    ->visible(fn (): bool => NotificationResource::canAccess())
                    ->url(fn (): string => NotificationResource::getUrl('index')),
            ])
            ->contentGrid(['default' => 1])
            ->paginated(false)
            ->emptyStateHeading(__('announcement.widget.empty'))
            ->emptyStateIcon(Heroicon::OutlinedMegaphone)
            ->recordAction('open')
            ->columns([
                Split::make([
                    // Ekran okuyucu onem adini okur.
                    IconColumn::make('priority')
                        ->state(fn (Announcement $record): string => (string) $this->priority($record)->getLabel())
                        ->icon(fn (Announcement $record): Heroicon => $this->priority($record)->getIcon())
                        ->color(fn (Announcement $record): string => $this->tone($record))
                        ->extraAttributes(['class' => 'kc-tile-icon'])
                        ->grow(false),
                    Stack::make([
                        TextColumn::make('title')
                            ->weight(FontWeight::SemiBold)
                            ->lineClamp(2)
                            ->wrap(),
                        TextColumn::make('body')
                            ->size(TextSize::Small)
                            ->color('gray')
                            ->lineClamp(4)
                            ->wrap(),
                        TextColumn::make('meta')
                            ->state(fn (Announcement $record): string => $this->meta($record))
                            ->size(TextSize::ExtraSmall)
                            ->color('gray')
                            ->wrap(),
                    ])->space(1),
                ])->extraAttributes(['class' => 'kc-split-top']),
            ])
            ->recordActions([
                // Satir tiklamasiyla acilir; satirda dugme olarak gorunmez (D-125).
                Action::make('open')
                    ->label(__('announcement.actions.read'))
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (Announcement $record): string => (string) $record->title)
                    ->modalDescription(fn (Announcement $record): string => $this->meta($record))
                    ->modalIcon(fn (Announcement $record): Heroicon => $this->priority($record)->getIcon())
                    ->modalIconColor(fn (Announcement $record): string => $this->tone($record))
                    ->modalWidth(Width::TwoExtraLarge)
                    // Uzun duyuruda baslik ve Kapat kaydirirken gorunur kalir.
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->schema(fn (Announcement $record): array => [
                        Text::make(new HtmlString(nl2br(e((string) $record->body)))),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('announcement.actions.close'))
                    ->extraModalFooterActions(fn (Announcement $record): array => filled($record->action_url) ? [
                        Action::make('open_link')
                            ->label(__('announcement.widget.open_link'))
                            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                            ->color(ActionColors::VIEW)
                            ->url((string) $record->action_url),
                    ] : []),
            ]);
    }

    private function priority(Announcement $record): AnnouncementPriority
    {
        return $record->priority ?? AnnouncementPriority::Normal;
    }

    /** Normal kirmizi (panelin ana rengi), Onemli turuncu-sari, Acil kirmizi. */
    private function tone(Announcement $record): string
    {
        $priority = $this->priority($record);

        return $priority === AnnouncementPriority::Normal ? 'primary' : $priority->listTone();
    }

    /** Tarih · gonderen · kitle; gizli hesap arayuzde "Sistem" (D-120). */
    private function meta(Announcement $record): string
    {
        return implode(' · ', array_filter([
            DisplayTime::format($record->sent_at),
            $record->sender?->full_name ?? __('activity.system'),
            $record->audience_label,
        ]));
    }
}
