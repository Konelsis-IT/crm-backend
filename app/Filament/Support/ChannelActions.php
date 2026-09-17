<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Party\CommunicationChannelType;
use App\Support\ContactLinks;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;

/**
 * Iletisim bilgisi (telefon, e-posta, web) icin ortak kucuk eylemler
 * (16 Eylul 2026, kullanici istegi): kopyala, WhatsApp'a git, ara. Taraf
 * karti ve kisiler tablosu ayni seti kullanir; hepsi Filament eylemidir,
 * kopyalama Filament'in kendi "copyable" davranisiyla ayni pano cagrisini
 * kullanir (Alpine tiklama isleyicisi; ayri JS dosyasi yok).
 */
final class ChannelActions
{
    /** Panoya kopyalama; deger yoksa null. */
    public static function copy(string $key, ?string $value): ?Action
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $copied = Js::from(__('communication_point.actions.copied'));

        return Action::make('copy_'.$key)
            ->label(__('communication_point.actions.copy'))
            ->tooltip(__('communication_point.actions.copy'))
            ->icon(Heroicon::OutlinedClipboardDocument)
            ->color('gray')
            ->link()
            ->iconButton()
            ->livewireClickHandlerEnabled(false)
            ->alpineClickHandler("window.navigator.clipboard.writeText(".Js::from($value)."); \$tooltip({$copied}, { theme: \$store.theme, timeout: 1500 })");
    }

    /** WhatsApp sohbeti; numara gecersizse null. */
    public static function whatsapp(string $key, ?string $phone): ?Action
    {
        $url = ContactLinks::whatsapp($phone);

        if ($url === null) {
            return null;
        }

        return Action::make('whatsapp_'.$key)
            ->label(__('personnel.actions.whatsapp_short'))
            ->tooltip(__('personnel.actions.whatsapp'))
            ->icon(BrandIcons::whatsapp())
            ->color('success')
            ->link()
            ->iconButton()
            ->url($url, shouldOpenInNewTab: true);
    }

    /** Arama baglantisi; numara gecersizse null. */
    public static function call(string $key, ?string $phone): ?Action
    {
        $url = ContactLinks::tel($phone);

        if ($url === null) {
            return null;
        }

        return Action::make('call_'.$key)
            ->label(__('personnel.actions.call'))
            ->tooltip(__('personnel.actions.call'))
            ->icon(Heroicon::OutlinedPhone)
            ->color('success')
            ->link()
            ->iconButton()
            ->url($url);
    }

    /** Kanal turune gore satir ici baglanti: tel:, mailto:, https://. */
    public static function url(?CommunicationChannelType $type, ?string $value): ?string
    {
        return match ($type) {
            CommunicationChannelType::Email => ContactLinks::mailto($value),
            CommunicationChannelType::Mobile, CommunicationChannelType::Phone => ContactLinks::tel($value),
            CommunicationChannelType::Website, CommunicationChannelType::Linkedin => ContactLinks::website($value),
            default => null,
        };
    }

    /** Kanal turune gore simge. */
    public static function icon(?CommunicationChannelType $type): Heroicon
    {
        return match ($type) {
            CommunicationChannelType::Email => Heroicon::OutlinedEnvelope,
            CommunicationChannelType::Mobile => Heroicon::OutlinedDevicePhoneMobile,
            CommunicationChannelType::Phone => Heroicon::OutlinedPhone,
            CommunicationChannelType::Fax => Heroicon::OutlinedPrinter,
            CommunicationChannelType::Website => Heroicon::OutlinedGlobeAlt,
            default => Heroicon::OutlinedLink,
        };
    }

    /** Telefon turu mu (WhatsApp / arama uygulanir)? */
    public static function isPhone(?CommunicationChannelType $type): bool
    {
        return $type === CommunicationChannelType::Mobile || $type === CommunicationChannelType::Phone;
    }
}
