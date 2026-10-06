<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\Platform\FeatureFlags;
use App\Enums\Platform\Feature;
use App\Filament\Support\FormState;
use App\Enums\Notification\AnnouncementAudience;
use App\Enums\Notification\AnnouncementPriority;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Personnel\Personnel;
use App\Query\Authorization\RoleQueries;
use App\Query\Personnel\OrganizationQueries;
use App\Query\Personnel\PersonnelQueries;
use App\Filament\Widgets\AnnouncementsWidget;
use App\Filament\Widgets\DashboardStatsWidget;
use App\Filament\Widgets\MyAlertsWidget;
use App\Filament\Widgets\MyWorkItemsWidget;
use App\Filament\Widgets\UpcomingSocialContentsWidget;
use App\Services\Notification\AnnouncementService;
use App\Services\Notification\AudienceResolver;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * Genel bakis (D-82; ad D-119): "Duyuru gonder" eylemi (D-148'e kadar adi
 * "Bildirim gonder") — gonderenin izinli oldugu kitleye (ekibi, departman, rol,
 * secili kisiler, herkes) duyuru: Filament zili bildirimi ve Duyurular kutusu.
 * Sag ust kullanici menusundeki "Duyuru gonder" de buraya `?duyuru=gonder` ile
 * gelir ve pencereyi dogrudan acar.
 *
 * D-146 (30 Eylul 2026 kullanici tasarimi): sayfa 3/4 + 1/4 bolunur. Solda
 * "Genel bakış" basligi ile ayni satirda Bugün / Geciken / Yaklaşan sayilari
 * (Filament istatistik gorunumu), altinda "Görevlerim ve işlerim"; sagda
 * "Duyuru gönder", yaklasan tarihler, duyurular ve sosyal medya. Filament'in
 * sayfa basligi yerine baslik icerikte cizilir (sayilarla ayni hizada).
 */
class Dashboard extends BaseDashboard
{
    public const SEND_ACTION = 'sendNotification';

    public static function getNavigationLabel(): string
    {
        return __('app.dashboard.title');
    }

    public function getTitle(): string
    {
        return __('app.dashboard.title');
    }

    /** Baslik icerikte, sayilarla ayni satirda (D-146); Filament basligi cizilmez. */
    public function getHeading(): string
    {
        return '';
    }

    public function mount(): void
    {
        // ?duyuru=gonder (D-148); eski ?bildirim=gonder baglantilari da calisir.
        $wantsSend = request()->query('duyuru') === 'gonder' || request()->query('bildirim') === 'gonder';

        if ($wantsSend && $this->canSendNotification()) {
            $this->mountAction(self::SEND_ACTION);
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 4])->schema([
                Group::make([
                    Flex::make([
                        // Alt baslik ("Bugün ve yarın yapılacak işlerim") kaldirildi (D-149).
                        Group::make([
                            Text::make(__('app.dashboard.title'))->extraAttributes(['class' => 'kc-dash-title']),
                        ])->grow(false)->extraAttributes(['class' => 'kc-dash-heading']),
                        Group::make($this->getWidgetsSchemaComponents([DashboardStatsWidget::class])),
                    ])->from('md')->verticallyAlignCenter(),
                    ...$this->getWidgetsSchemaComponents([MyWorkItemsWidget::class]),
                ])->columnSpan(['lg' => 3]),
                Group::make([
                    // Dugme ozellikle kapanir; eylem kapanmaz: sag ust menudeki
                    // "Duyuru gonder" (?duyuru=gonder) pencereyi yine acar.
                    Actions::make([$this->sendNotificationAction()])
                        ->fullWidth()
                        ->visible(fn (): bool => FeatureFlags::enabled(Feature::DashboardSendNotification)),
                    ...$this->getWidgetsSchemaComponents([
                        MyAlertsWidget::class,
                        AnnouncementsWidget::class,
                        UpcomingSocialContentsWidget::class,
                    ]),
                ])->columnSpan(['lg' => 1]),
            ]),
        ]);
    }

    public function sendNotificationAction(): Action
    {
        // Turuncu dugme (D-147, 30 Eylul 2026 kullanici istegi; panelde kayitli 'orange').
        // Pencerenin "Gonder" dugmesi yesil, "Iptal" gul kirmizisi (D-148, ActionColors).
        return Action::make(self::SEND_ACTION)
                ->label(__('announcement.actions.send'))
                ->icon(Heroicon::OutlinedMegaphone)
                ->color('orange')
                ->visible(fn (): bool => $this->canSendNotification())
                ->modalHeading(__('announcement.actions.send'))
                ->modalDescription(__('announcement.help.send'))
                ->modalWidth(Width::ThreeExtraLarge)
                ->modalSubmitActionLabel(__('announcement.actions.submit'))
                ->schema(fn (Schema $schema): Schema => $schema
                    ->columns(FieldGrid::MODAL_COLUMNS)
                    ->components(FieldGrid::modal([
                        Select::make('audience_kind')
                            ->label(__('announcement.fields.audience_kind'))
                            ->options($this->audienceOptions())
                            ->required()
                            ->live()
                            ->native(false)
                            ->columnSpan(['default' => 1, 'md' => 2]),
                        Select::make('priority')
                            ->label(__('announcement.fields.priority'))
                            ->options(AnnouncementPriority::options())
                            ->default(AnnouncementPriority::Normal->value)
                            ->required()
                            ->native(false)
                            ->columnSpan(['default' => 1, 'md' => 2]),
                        Select::make('org_unit_id')
                            ->label(__('announcement.fields.department'))
                            ->options(fn (): array => app(OrganizationQueries::class)->orgUnitOptions())
                            ->searchable()
                            ->native(false)
                            ->required(fn (Get $get): bool => FormState::value($get('audience_kind')) === AnnouncementAudience::Department->value)
                            ->visible(fn (Get $get): bool => FormState::value($get('audience_kind')) === AnnouncementAudience::Department->value)
                            ->columnSpan(['default' => 1, 'md' => 2]),
                        Select::make('role_id')
                            ->label(__('announcement.fields.role'))
                            ->options(fn (): array => app(RoleQueries::class)->roleOptions())
                            ->searchable()
                            ->native(false)
                            ->required(fn (Get $get): bool => FormState::value($get('audience_kind')) === AnnouncementAudience::Role->value)
                            ->visible(fn (Get $get): bool => FormState::value($get('audience_kind')) === AnnouncementAudience::Role->value)
                            ->columnSpan(['default' => 1, 'md' => 2]),
                        Select::make('audience_ids')
                            ->label(__('announcement.fields.personnel'))
                            ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                            ->multiple()
                            ->searchable()
                            ->native(false)
                            ->required(fn (Get $get): bool => FormState::value($get('audience_kind')) === AnnouncementAudience::Personnel->value)
                            ->visible(fn (Get $get): bool => FormState::value($get('audience_kind')) === AnnouncementAudience::Personnel->value)
                            ->columnSpan(['default' => 1, 'md' => 2]),
                        TextInput::make('title')
                            ->label(__('announcement.fields.title'))
                            ->required()
                            ->maxLength(200)
                            ->columnSpan(['default' => 1, 'md' => 2]),
                        Textarea::make('body')
                            ->label(__('announcement.fields.body'))
                            ->required()
                            ->rows(5)
                            ->maxLength(4000),
                        TextInput::make('action_url')
                            ->label(__('announcement.fields.action_url'))
                            ->helperText(__('announcement.help.action_url'))
                            ->url()
                            ->maxLength(2000)
                            ->columnSpan(['default' => 1, 'md' => 2]),
                    ])))
                ->action(function (array $data): void {
                    $kind = (string) ($data['audience_kind'] ?? '');
                    $audienceId = match ($kind) {
                        AnnouncementAudience::Department->value => $data['org_unit_id'] ?? null,
                        AnnouncementAudience::Role->value => $data['role_id'] ?? null,
                        default => null,
                    };

                    try {
                        $announcement = app(AnnouncementService::class)->send([
                            'audience_kind' => $kind,
                            'audience_id' => $audienceId,
                            'audience_ids' => $data['audience_ids'] ?? [],
                            'priority' => $data['priority'] ?? null,
                            'title' => $data['title'] ?? '',
                            'body' => $data['body'] ?? '',
                            'action_url' => $data['action_url'] ?? null,
                        ]);

                        DomainNotifications::success(__('announcement.messages.sent', ['count' => $announcement->recipient_count]));

                        // Gonderen duyuruyu hemen Duyurular kutusunda gorur; kendisi de
                        // alicilar arasindaysa (personel degistirme) zil hemen dolar.
                        $this->dispatch(AnnouncementsWidget::SENT_EVENT);
                        $this->dispatch('databaseNotificationsSent');
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
    }

    /**
     * @return array<string, string>
     */
    private function audienceOptions(): array
    {
        $user = auth()->user();

        if (! $user instanceof Personnel) {
            return [];
        }

        $options = [];

        foreach (app(AudienceResolver::class)->permittedKinds($user) as $kind) {
            $options[$kind->value] = $kind->getLabel();
        }

        return $options;
    }

    private function canSendNotification(): bool
    {
        $user = auth()->user();

        return FeatureFlags::enabled(Feature::Announcements)
            && SchemaReadiness::hasBatch('B11A')
            && $user instanceof Personnel
            && app(AudienceResolver::class)->permittedKinds($user) !== [];
    }
}
