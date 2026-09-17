<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Auth\PersonnelProfile;
use App\Filament\Pages\Dashboard;
use App\Filament\NavigationGroup;
use App\Http\Controllers\Chat\ChatAttachmentController;
use App\Http\Controllers\Chat\ChatController;
use App\Http\Controllers\Files\ProjectPhotoController;
use App\Http\Controllers\Files\RevisionFileController;
use App\Http\Controllers\Notifications\ApprovalQuickDecisionController;
use App\Http\Controllers\Notifications\BusinessAlertAcknowledgeController;
use App\Http\Middleware\SetLocale;
use App\Models\Personnel\Personnel;
use App\Services\Notification\AudienceResolver;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Filament\Support\ReleaseNotesSchema;
use App\Support\ReleaseNotes;
use App\Support\RoleLabels;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
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
            ])
            // Her kaynak icin Policy zorunlu; tanimsiz yetki reddedilir.
            ->strictAuthorization()
            // Goruntuleme sayfalari bu urunde calisma alanidir (proje, is dosyasi,
            // dokuman): alt tablolar salt okunur degil, Policy'nin izin verdigi
            // olcude kayit ekler/duzenler (D-73). Filament varsayilani kapatildi.
            ->readOnlyRelationManagersOnResourceViewPagesByDefault(false)
            ->navigationGroups(NavigationGroup::class)
            // Sag ustteki kullanici menusunde giris yapan kisinin rolu ve
            // departmani; salt bilgi amacli, tiklanamaz.
            ->userMenuItems([
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
                Route::prefix('files')->name('files.')->group(function (): void {
                    Route::get('revisions/{file}', RevisionFileController::class)->name('revision');
                    Route::get('project-photos/{photo}', ProjectPhotoController::class)->name('photo');
                });

                // Bildirimden tek tiklama (D-82): imzali baglantilar.
                // filament.admin.notifications.approval-approve / .alert-acknowledge
                Route::prefix('notifications')->name('notifications.')->group(function (): void {
                    Route::get('approvals/{approval}/approve', ApprovalQuickDecisionController::class)->name('approval-approve');
                    Route::get('alerts/{alert}/acknowledge', BusinessAlertAcknowledgeController::class)->name('alert-acknowledge');
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
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn () => view('filament.components.language-switcher'),
            )
            // Sag alttaki sohbet baslaticisi (D-83): yalniz oturum acmis personel,
            // B12A uygulanmis ve bayrak acikken.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => auth()->check()
                    && SchemaReadiness::hasBatch('B12A')
                    && FeatureFlags::enabled('chat.admin_ui')
                    ? view('filament.chat.launcher')
                    : '',
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
            ]);
    }
}
