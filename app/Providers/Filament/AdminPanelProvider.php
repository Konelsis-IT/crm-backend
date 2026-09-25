<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Auth\PersonnelProfile;
use App\Filament\Clusters\Settings;
use App\Filament\Pages\Dashboard;
use App\Filament\NavigationGroup;
use Filament\Navigation\NavigationGroup as FilamentNavigationGroup;
use App\Filament\Pages\Work\ControlMatrix;
use App\Filament\Pages\Work\WorkAnalysis;
use App\Filament\Pages\Work\WorkBoard;
use App\Filament\Resources\MeetingPlans\Pages\MeetingPlanCalendar;
use App\Filament\Resources\Personnel\Pages\ViewPersonnelRecord;
use App\Filament\Resources\SocialContents\Pages\ManageSocialMedia;
use App\Filament\Support\ReactRuntime;
use App\Http\Controllers\Chat\ChatAttachmentController;
use App\Http\Controllers\Chat\ChatController;
use App\Http\Controllers\Files\ProjectPhotoController;
use App\Http\Controllers\Meeting\MeetingPlanCalendarController;
use App\Http\Controllers\Work\ControlMatrixController;
use App\Http\Controllers\Work\WorkBoardController;
use App\Http\Controllers\Work\WorkInsightController;
use App\Http\Controllers\WorkRequest\WorkRequestFileController;
use App\Http\Controllers\Files\RevisionFileController;
use App\Http\Controllers\Notifications\ApprovalQuickDecisionController;
use App\Http\Controllers\Notifications\AlertFeedController;
use App\Http\Controllers\Notifications\BusinessAlertAcknowledgeController;
use App\Http\Controllers\SocialMedia\SocialCatalogController;
use App\Http\Controllers\SocialMedia\SocialCommentController;
use App\Http\Controllers\SocialMedia\SocialContentController;
use App\Http\Controllers\SocialMedia\SocialMediaController;
use App\Http\Controllers\SocialMedia\SocialMediaFileController;
use App\Http\Controllers\SocialMedia\SocialMetricController;
use App\Http\Controllers\SocialMedia\SocialPlanningController;
use App\Http\Controllers\SocialMedia\SocialSettingsController;
use App\Http\Controllers\SocialMedia\SocialUploadController;
use App\Http\Middleware\ImpersonatePersonnel;
use App\Http\Middleware\SetLocale;
use App\Models\Personnel\Personnel;
use App\Query\Personnel\PersonnelQueries;
use App\Services\Authorization\ImpersonationService;
use App\Services\Notification\AudienceResolver;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Filament\Support\ReleaseNotesSchema;
use App\Filament\Support\TopbarShortcuts;
use App\Support\ReleaseNotes;
use App\Support\RoleLabels;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Select;
use Filament\Http\Middleware\Authenticate;
use App\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // Sag ustteki kullanici menusunden kendi profiline erisim.
            ->profile(PersonnelProfile::class, isSimple: false)
            ->brandName(fn (): string => __('app.name'))
            // Kurumsal logo (11 Eylul 2026 kullanici karari): yatay Konelsis logosu,
            // seffaf zemin (Projects/logo/logo vector 3.png'ten uretildi); acik ve
            // koyu temada ayni dosya (kirmizi yazi + kirmizi slogan).
            ->brandLogo(asset('images/konelsis-logo.png'))
            ->darkModeBrandLogo(asset('images/konelsis-logo.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('images/konelsis-favicon.png'))
            ->colors([
                'primary' => Color::Red,
                // Is panosu "Bekleniyor" durumu (B36, D-115).
                'violet' => Color::Violet,
            ])
            // Icerik alani tam genislik (17 Eylul 2026 kullanici karari): Filament
            // varsayilani 7xl (1280px) icerigi ortalayip genis ekranda kenarlarda
            // bos alan birakiyordu; tablolar ve formlar artik kullanilabilir
            // alanin tamamini kullanir. Sayfa bazinda daraltmak gerekirse ilgili
            // Page sinifinda $maxContentWidth ozelligi ile asilir.
            ->maxContentWidth(Width::Full)
            // Her kaynak icin Policy zorunlu; tanimsiz yetki reddedilir.
            ->strictAuthorization()
            // Goruntuleme sayfalari bu urunde calisma alanidir (proje, is dosyasi,
            // dokuman): alt tablolar salt okunur degil, Policy'nin izin verdigi
            // olcude kayit ekler/duzenler (D-73). Filament varsayilani kapatildi.
            ->readOnlyRelationManagersOnResourceViewPagesByDefault(false)
            // Sol menu gruplari kapali baslar, tiklaninca acilir (kullanici
            // istegi, 23 Eylul 2026): uzun menu tek bakista okunabilsin.
            ->navigationGroups(array_map(
                fn (NavigationGroup $group): FilamentNavigationGroup => FilamentNavigationGroup::fromEnum($group)
                    ->collapsible()
                    ->collapsed(),
                NavigationGroup::cases(),
            ))
            // Sag ustteki kullanici menusunde giris yapan kisinin rolu ve
            // departmani; salt bilgi amacli, tiklanamaz.
            ->userMenuItems([
                // En ustte kisinin adi tek satir baslik olarak (24 Eylul 2026
                // kullanici istegi); baglantisiz profil ogesi Filament'te baslik
                // olarak cizilir. Profil sayfasi ve Ayarlar hemen altinda.
                'profile' => fn (Action $action): Action => $action
                    ->label(fn (): string => Filament::getUserName(Filament::auth()->user()))
                    ->url(null),
                'my_profile' => Action::make('my_profile')
                    ->label(fn (): string => PersonnelProfile::getLabel())
                    ->icon(Heroicon::OutlinedIdentification)
                    ->url(fn (): ?string => Filament::getProfileUrl())
                    ->sort(-1),
                // Ayarlar sol menude degil burada (24 Eylul 2026 kullanici istegi).
                'settings' => Action::make('settings')
                    ->label(fn (): string => __('app.nav.settings'))
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn (): string => Settings::getUrl())
                    ->visible(fn (): bool => Settings::canAccessClusteredComponents())
                    ->sort(-1),
                // Masaustu (Windows) bildirim izni (D-126): tarayici izni ancak
                // kullanici tiklayinca sorar; konelsis-alerts.js izni ister,
                // deneme sesi calar ve sonucu Filament bildirimiyle yazar.
                'desktop_alerts' => Action::make('desktop_alerts')
                    ->label(fn (): string => __('alerts.actions.enable'))
                    ->icon(Heroicon::OutlinedBellAlert)
                    ->alpineClickHandler('window.KonelsisAlerts && window.KonelsisAlerts.enable()')
                    ->sort(-1),
                // Personel degistir (D-120): yalniz gizli sistem hesabinda
                // gorunur, Roller ekranindan verilmez ve alinamaz.
                'impersonate' => Action::make('impersonate')
                    ->label(fn (): string => __('impersonation.actions.switch'))
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->visible(fn (): bool => app(ImpersonationService::class)->canOperate())
                    ->modalHeading(fn (): string => __('impersonation.modal.heading'))
                    ->modalDescription(fn (): string => __('impersonation.modal.description'))
                    ->modalSubmitActionLabel(fn (): string => __('impersonation.actions.submit'))
                    ->schema(fn (Schema $schema): Schema => $schema->columns(1)->components([
                        Select::make('personnel_id')
                            ->label(__('impersonation.fields.personnel'))
                            ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                            ->searchable()
                            ->required()
                            ->native(false),
                    ]))
                    ->action(function (array $data): void {
                        $service = app(ImpersonationService::class);
                        $operator = $service->operator();
                        $target = $operator === null ? null : $service->start($operator, (int) $data['personnel_id']);

                        if ($target === null) {
                            Notification::make()
                                ->title(__('impersonation.messages.failed'))
                                ->danger()
                                ->send();

                            return;
                        }

                        redirect(Dashboard::getUrl());
                    }),
                'stop_impersonating' => Action::make('stop_impersonating')
                    ->label(fn (): string => __('impersonation.actions.stop'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (): bool => app(ImpersonationService::class)->isSwapped())
                    ->action(function (): void {
                        app(ImpersonationService::class)->stop();

                        redirect(Dashboard::getUrl());
                    }),
                // Surum notlari (D-91): her surum ayri acilir bolum, en yenisi acik.
                'release_notes' => Action::make('release_notes')
                    ->label(fn (): string => __('release.actions.open'))
                    ->icon(Heroicon::OutlinedSparkles)
                    ->modalHeading(fn (): string => __('release.modal.heading'))
                    ->modalDescription(fn (): string => __('release.modal.description'))
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(fn (): string => __('release.actions.close'))
                    // Yayin tarihi gelmemis surumler gizli; hic yayin yoksa menude yer almaz.
                    ->visible(fn (): bool => ReleaseNotes::published() !== [])
                    ->schema(fn (Schema $schema): Schema => $schema
                        ->columns(1)
                        ->components(ReleaseNotesSchema::components())),
                // Bildirim/duyuru gonderme (D-82): panoya gider ve pencereyi acar.
                'send_notification' => Action::make('send_notification')
                    ->label(fn (): string => __('announcement.actions.send'))
                    ->icon(Heroicon::OutlinedMegaphone)
                    ->url(fn (): string => Dashboard::getUrl(['bildirim' => 'gonder']))
                    ->visible(fn (): bool => SchemaReadiness::hasBatch('B11A')
                        && auth()->user() instanceof Personnel
                        && app(AudienceResolver::class)->permittedKinds(auth()->user()) !== []),
                'current_role' => Action::make('current_role')
                    ->label(fn (): string => __('role.label').': '.(
                        auth()->user() instanceof Personnel
                            ? (RoleLabels::list(auth()->user()->getRoleNames()) ?: '-')
                            : '-'
                    ))
                    ->icon(Heroicon::OutlinedKey)
                    ->disabled(),
                'current_org_unit' => Action::make('current_org_unit')
                    ->label(fn (): string => __('personnel.fields.department').': '.(
                        auth()->user() instanceof Personnel
                            ? (auth()->user()->orgUnit?->name ?? '-')
                            : '-'
                    ))
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->disabled()
                    ->visible(fn (): bool => SchemaReadiness::hasBatch('B03')),
            ])
            // Bildirimler Filament'in kendi bildirim arayuzunde gosterilir.
            ->databaseNotifications(fn (): bool => FeatureFlags::enabled('notifications.database')
                && SchemaReadiness::hasBatch('B00'))
            // Yetki kontrollu dosya indirme/onizleme uclari (D-71); panelin kimlik
            // dogrulamali rota grubunda: filament.admin.files.revision / .photo
            ->authenticatedRoutes(function (Panel $panel): void {
                // Gorusme plani takvim verisi (B34, D-109): filament.admin.meeting-calendar.data
                Route::get('meeting-calendar/data', MeetingPlanCalendarController::class)->name('meeting-calendar.data');

                // Is panosu, kontrol matrisi, is raporlari JSON uclari (B36, D-115): filament.admin.work.*
                // Her uc B36 + etkin personel + politika kontrolunden gecer; kart silme yalniz
                // elle girilen karti, sahibi ya da tam yetki siler.
                Route::prefix('work')->name('work.')->whereNumber(['item', 'activity', 'personnel'])->group(function (): void {
                    Route::get('board', [WorkBoardController::class, 'board'])->name('board');
                    Route::get('dismissed', [WorkBoardController::class, 'dismissed'])->name('dismissed');
                    Route::post('items', [WorkBoardController::class, 'store'])->name('items.store');
                    Route::post('items/{item}', [WorkBoardController::class, 'update'])->name('items.update');
                    Route::post('items/{item}/status', [WorkBoardController::class, 'status'])->name('items.status');
                    Route::post('items/{item}/critical', [WorkBoardController::class, 'critical'])->name('items.critical');
                    Route::post('items/{item}/delete', [WorkBoardController::class, 'destroy'])->name('items.delete');
                    Route::post('reorder', [WorkBoardController::class, 'reorder'])->name('reorder');
                    Route::post('suggestions/{activity}/card', [WorkBoardController::class, 'fromSuggestion'])->name('suggestions.card');
                    Route::post('suggestions/{activity}/dismiss', [WorkBoardController::class, 'dismiss'])->name('suggestions.dismiss');
                    Route::post('suggestions/{activity}/restore', [WorkBoardController::class, 'restore'])->name('suggestions.restore');
                    Route::get('lookup/links', [WorkBoardController::class, 'links'])->name('lookup.links');
                    Route::get('lookup/parties', [WorkBoardController::class, 'parties'])->name('lookup.parties');
                    Route::get('day', [WorkBoardController::class, 'day'])->name('day');
                    Route::post('day', [WorkBoardController::class, 'closeDay'])->name('day.close');
                    Route::get('week', [WorkBoardController::class, 'week'])->name('week');
                    Route::post('week', [WorkBoardController::class, 'closeWeek'])->name('week.close');
                    Route::post('freeze', [WorkBoardController::class, 'freeze'])->name('freeze');
                    Route::get('matrix', [ControlMatrixController::class, 'show'])->name('matrix');
                    Route::post('matrix', [ControlMatrixController::class, 'save'])->name('matrix.save');
                    Route::get('analysis', [WorkInsightController::class, 'analysis'])->name('analysis');
                    Route::get('attention/{personnel}', [WorkInsightController::class, 'attention'])->name('attention');
                });

                Route::prefix('files')->name('files.')->group(function (): void {
                    Route::get('revisions/{file}', RevisionFileController::class)->name('revision');
                    Route::get('project-photos/{photo}', ProjectPhotoController::class)->name('photo');
                    Route::get('work-request/{file}', WorkRequestFileController::class)->name('work-request');
                });

                // Bildirimden tek tiklama (D-82): imzali baglantilar.
                // filament.admin.notifications.approval-approve / .alert-acknowledge
                Route::prefix('notifications')->name('notifications.')->group(function (): void {
                    Route::get('approvals/{approval}/approve', ApprovalQuickDecisionController::class)->name('approval-approve');
                    Route::get('alerts/{alert}/acknowledge', BusinessAlertAcknowledgeController::class)->name('alert-acknowledge');
                    // Masaustu bildirimi + ses beslemesi (D-126): filament.admin.notifications.feed
                    Route::get('feed', AlertFeedController::class)->name('feed');
                });

                // Kurum ici sohbet JSON uclari (D-83): filament.admin.chat.*
                Route::prefix('chat')->name('chat.')->group(function (): void {
                    Route::get('bootstrap', [ChatController::class, 'bootstrap'])->name('bootstrap');
                    Route::get('sync', [ChatController::class, 'sync'])->name('sync');
                    Route::get('conversations', [ChatController::class, 'conversations'])->name('conversations');
                    Route::post('conversations', [ChatController::class, 'createConversation'])->name('conversations.create');
                    Route::get('conversations/{conversation}/messages', [ChatController::class, 'messages'])->name('messages');
                    Route::post('conversations/{conversation}/messages', [ChatController::class, 'send'])->name('messages.send');
                    Route::post('conversations/{conversation}/read', [ChatController::class, 'read'])->name('read');
                    Route::post('conversations/{conversation}/pin', [ChatController::class, 'pin'])->name('pin');
                    Route::post('conversations/{conversation}/typing', [ChatController::class, 'typing'])->name('typing');
                    Route::delete('conversations/{conversation}', [ChatController::class, 'deleteConversation'])->name('conversations.delete');
                    Route::delete('messages/{message}', [ChatController::class, 'deleteMessage'])->name('messages.delete');
                    Route::get('directory', [ChatController::class, 'directory'])->name('directory');
                    Route::get('documents', [ChatController::class, 'documents'])->name('documents');
                    Route::get('attachments/{attachment}', ChatAttachmentController::class)->name('attachment');
                });

                // Sosyal Medya JSON uclari (B31, D-106): filament.admin.social.*
                // Sayfadaki React uygulamasi kullanir; her uc denetleyicide B31 + bayrak +
                // etkin personel kontrolunden ve Gate::authorize'dan gecer. Silme ucu yoktur.
                // Rota parametre adlari denetleyici arguman adlariyla aynidir (model baglama).
                // Yeni rota eklenince App\Filament\Support\SocialAppConfig::ROUTES da guncellenir.
                Route::prefix('social')->name('social.')->where([
                    'content' => '[0-9]+',
                    'comment' => '[0-9]+',
                    'media' => '[0-9]+',
                    'account' => '[0-9]+',
                    'category' => '[0-9]+',
                    'day' => '[0-9]+',
                    'profile' => '[0-9]+',
                    'entry' => '[0-9]+',
                    'token' => '[A-Za-z0-9]{40}',
                ])->group(function (): void {
                    // Icerik
                    Route::get('bootstrap', [SocialContentController::class, 'bootstrap'])->name('bootstrap');
                    Route::get('counts', [SocialContentController::class, 'counts'])->name('counts');
                    Route::get('contents', [SocialContentController::class, 'index'])->name('contents');
                    Route::post('contents', [SocialContentController::class, 'store'])->name('contents.store');
                    Route::get('contents/{content}', [SocialContentController::class, 'show'])->name('contents.show');
                    Route::post('contents/{content}', [SocialContentController::class, 'update'])->name('contents.update');
                    Route::post('contents/{content}/status', [SocialContentController::class, 'status'])->name('contents.status');
                    Route::post('contents/{content}/publish', [SocialContentController::class, 'publish'])->name('contents.publish');
                    Route::post('contents/{content}/unpublish', [SocialContentController::class, 'unpublish'])->name('contents.unpublish');
                    Route::post('contents/{content}/urgent', [SocialContentController::class, 'urgent'])->name('contents.urgent');
                    Route::post('contents/{content}/reaction', [SocialContentController::class, 'reaction'])->name('contents.reaction');

                    // Yorumlar ve gorsel uzeri isaretler
                    Route::post('contents/{content}/comments', [SocialCommentController::class, 'store'])->name('comments.store');
                    Route::post('comments/{comment}/resolve', [SocialCommentController::class, 'resolve'])->name('comments.resolve');

                    // Medya (galeri, surumler, video kapagi) ve yetki kontrollu dosya sunumu
                    Route::post('contents/{content}/media', [SocialMediaController::class, 'store'])->name('media.store');
                    Route::post('contents/{content}/media/order', [SocialMediaController::class, 'order'])->name('media.order');
                    Route::post('media/{media}', [SocialMediaController::class, 'update'])->name('media.update');
                    Route::post('media/{media}/variant', [SocialMediaController::class, 'variant'])->name('media.variant');
                    Route::post('media/{media}/select', [SocialMediaController::class, 'select'])->name('media.select');
                    Route::post('media/{media}/remove', [SocialMediaController::class, 'remove'])->name('media.remove');
                    Route::post('media/{media}/restore', [SocialMediaController::class, 'restore'])->name('media.restore');
                    Route::post('media/{media}/poster', [SocialMediaController::class, 'poster'])->name('media.poster');
                    Route::get('media/{media}/file', SocialMediaFileController::class)->name('media.file');
                    // Seri indirme: galerinin tum secili surumleri tek zip dosyasinda.
                    Route::get('contents/{content}/media/zip', [SocialMediaController::class, 'zip'])->name('media.zip');

                    // Parcali video yukleme
                    Route::post('uploads', [SocialUploadController::class, 'begin'])->name('uploads.begin');
                    Route::get('uploads/{token}', [SocialUploadController::class, 'status'])->name('uploads.status');
                    Route::post('uploads/{token}/chunks', [SocialUploadController::class, 'chunk'])->name('uploads.chunk');
                    Route::post('uploads/{token}/complete', [SocialUploadController::class, 'complete'])->name('uploads.complete');
                    Route::post('uploads/{token}/abort', [SocialUploadController::class, 'abort'])->name('uploads.abort');

                    // Plan (takvim, ajanda), analiz ve depolama kutusu
                    Route::get('calendar', [SocialPlanningController::class, 'calendar'])->name('calendar');
                    Route::get('agenda', [SocialPlanningController::class, 'agenda'])->name('agenda');
                    Route::get('analytics', [SocialPlanningController::class, 'analytics'])->name('analytics');
                    Route::get('storage', [SocialPlanningController::class, 'storage'])->name('storage');

                    // Ilham ve rakipler + ayarlar (kategori, ozel gun, hesap baglantilari, sorumlu gorevler)
                    Route::get('watch', [SocialSettingsController::class, 'watch'])->name('watch');
                    Route::post('watch', [SocialSettingsController::class, 'watchStore'])->name('watch.store');
                    Route::post('watch/{account}', [SocialSettingsController::class, 'watchUpdate'])->name('watch.update');
                    Route::post('categories', [SocialSettingsController::class, 'categoryStore'])->name('categories.store');
                    Route::post('categories/{category}', [SocialSettingsController::class, 'categoryUpdate'])->name('categories.update');
                    Route::get('days', [SocialSettingsController::class, 'days'])->name('days');
                    Route::post('days', [SocialSettingsController::class, 'dayStore'])->name('days.store');
                    Route::post('days/{day}', [SocialSettingsController::class, 'dayUpdate'])->name('days.update');
                    Route::post('profiles/{profile}', [SocialSettingsController::class, 'profileUpdate'])->name('profiles.update');
                    Route::get('responsibles', [SocialSettingsController::class, 'responsibles'])->name('responsibles');
                    Route::post('responsibles', [SocialSettingsController::class, 'responsiblesSync'])->name('responsibles.sync');

                    // Platform istatistikleri (elle giris ya da rapor dosyasi)
                    Route::get('metrics', [SocialMetricController::class, 'index'])->name('metrics');
                    Route::post('metrics', [SocialMetricController::class, 'store'])->name('metrics.store');
                    Route::post('metrics/{entry}', [SocialMetricController::class, 'update'])->name('metrics.update');
                    Route::get('metrics/{entry}/file', [SocialMetricController::class, 'file'])->name('metrics.file');

                    // Sirket katalogu (DMS sabit belge turu KAT)
                    Route::get('catalog', [SocialCatalogController::class, 'info'])->name('catalog');
                    Route::get('catalog/file', [SocialCatalogController::class, 'file'])->name('catalog.file');
                });
            })
            // Kumeler, onlara bagli kaynaklardan once kesfedilmeli; aksi halde
            // kaynaklarin rotalari kume on ekiyle degil eski adiyla kayit olur.
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // "Hoş geldin" hesap widget'i kaldirildi (11 Eylul 2026, kullanici
            // istegi); oturum kapatma zaten sag ust kullanici menusunde.
            // Rol/izin yonetim ekrani (D-65): Ayarlar kumesindeki Roller ekrani.
            ->plugin(
                FilamentShieldPlugin::make()
                    // Roller ekrani Ayarlar kumesinde (config/filament-shield.php `cluster`);
                    // kume icinde ayri bir alt grup basligi olusmasin, sekmelerde son sirada.
                    ->navigationGroup(null)
                    ->navigationSort(130),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn () => view('filament.components.language-switcher', ['onLoginPage' => true]),
            )
            // Ust cubuk kisayollari (24 Eylul 2026): Hizli islemler, Is panosu,
            // Kontrol matrisi; TR / EN'nin solunda. Filament eylem bilesenleri.
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): Htmlable => TopbarShortcuts::render(),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn () => view('filament.components.language-switcher'),
            )
            // Sag alttaki sohbet baslaticisi (D-83): yalniz oturum acmis personel,
            // B12A uygulanmis ve bayrak acikken. Kosul ReactRuntime'dadir; ayni
            // kosula Sosyal Medya betikleri de bakar (React iki kez yuklenmesin).
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => ReactRuntime::providedByChat()
                    ? view('filament.chat.launcher')
                    : '',
            )
            // Sosyal Medya React betikleri (D-106): yalniz o sayfada ve sohbet
            // kancasindan SONRA (kapsamsiz kancalar once cizilir), boylece belge
            // sirasi react, react-dom, konelsis-chat, social-* olur.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.social.scripts'),
                scopes: ManageSocialMedia::class,
            )
            // Gorusme plani takvimi (B34, D-109): ayni cekirdek + ortak takvim parcasi.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.meetings.scripts'),
                scopes: MeetingPlanCalendar::class,
            )
            // Is panosu React ekranlari (B36, D-115): ayni cekirdek (social-core) +
            // work-core + ekranin betigi; yalniz ilgili sayfada.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.work.scripts', ['screens' => ['work-board']]),
                scopes: WorkBoard::class,
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.work.scripts', ['screens' => ['work-matrix']]),
                scopes: ControlMatrix::class,
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.work.scripts', ['screens' => ['work-analysis']]),
                scopes: WorkAnalysis::class,
            )
            // Personel kartindaki Dikkat karti (ozel yetki; kisi kendi kartini gormez).
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.work.scripts', ['screens' => ['work-attention']]),
                scopes: ViewPersonnelRecord::class,
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                // Personel degistirme (D-120): oturum sistem hesabinda kalir,
                // arayuz secilen personelin gozuyle calisir.
                ImpersonatePersonnel::class,
            ])
            // Livewire guncelleme istekleri (tablo suzgeci, form kaydi, eylem,
            // zil yoklamasi) da secilen personelin gozuyle calissin. Oturum
            // korumasi projedeki alt siniftir (Filament'inki kalici listede
            // sinif adiyla eslestigi icin burada ayrica kalici yapilir).
            ->persistentMiddleware([
                AuthenticateSession::class,
                ImpersonatePersonnel::class,
            ]);
    }
}
