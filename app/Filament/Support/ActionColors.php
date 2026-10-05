<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Schemas\Components\Wizard;
use Filament\Support\Colors\Color;

/**
 * Dugme renkleri (D-148, 30 Eylul 2026 kullanici karari: "kaydet butonu
 * yesil, iptal gibi butonlar kirmizi olmali. Duzenle turuncu vs.. Ayrica
 * farkli tonlarda kirmizi turuncu gibi kullanilabilir, farkli tipte farkli
 * amacli butonlar icin"). Renk dugmenin amacina gore secilir; tek noktadan
 * uygulanir (AppServiceProvider):
 *
 *  - Pencere (modal) alt dugmeleri: onay / kaydet / gonder yesil, yikici
 *    eylemde (Sil, Reddet, Geri gonder) eylemin kendi rengi; Iptal / Kapat
 *    gul kirmizisi.
 *  - Filament'in hazir eylemleri: Duzenle turuncu, Yeni zumrut yesili,
 *    Goruntule mavi; Sil kirmizi kalir.
 *  - Olustur / Duzenle sayfalarinin alt dugmeleri HasColoredFormActions ile:
 *    Olustur / Kaydet yesil, Iptal gul kirmizisi. Sihirbazda Ileri mavi.
 *  - Dolu dugmelerde yazi her zaman beyaz (D-149, WhiteTextButtonComponent):
 *    acik tonlu renklerde (turuncu, zumrut, yesil, warning) zemin koyu tona
 *    gecer.
 *
 * Tek tek eylemde acikca verilen renk bu kurali ezer; ozel eylemler amacina
 * uyan sabiti kullanir (ActionColors::CANCEL, ::CREATE...).
 */
final class ActionColors
{
    /** Kaydet, Olustur (form onayi), Onayla, Tamamla, Yayinla: yesil (dugmede koyu ton, beyaz yazi). */
    public const SAVE = 'success';

    /** "Yeni ..." / "... ekle": kayit acan dugme, zumrut yesili (Kaydet'ten ayri ton). */
    public const CREATE = 'emerald';

    /** Duzenle: turuncu. */
    public const EDIT = 'orange';

    /** Iptal, Vazgec, Kapat, Geri cek: gul kirmizisi (Sil'den ayri ton). */
    public const CANCEL = 'rose';

    /** Sil, Reddet, Kaldir: kirmizi. */
    public const DELETE = 'danger';

    /** Goruntule, Ileri: mavi. */
    public const VIEW = 'info';

    /** Gonder, Ilet, Onaya sun: mavi. */
    public const SEND = 'info';

    /** Ikincil is (indir, onizle, liste / takvim gecisi, okundu): gri. */
    public const NEUTRAL = 'gray';

    /**
     * Filament'in hazir renklerine eklenen tonlar; her panel kaydeder
     * (warning sariya kactigi icin turuncu ayri, D-147). Dugmede hangi tonun
     * (600 / 700 / 800) kullanilacagini WhiteTextButtonComponent secer: yazi
     * her zaman beyaz (D-149).
     *
     * @return array<string, array<int, string>>
     */
    public static function panelColors(): array
    {
        return [
            self::EDIT => Color::Orange,
            self::CREATE => Color::Emerald,
            self::CANCEL => Color::Rose,
        ];
    }

    public static function register(): void
    {
        Action::configureUsing(function (Action $action): void {
            $action
                ->modalSubmitAction(fn (Action $action): Action => self::submit($action))
                ->modalCancelAction(fn (Action $action): Action => $action->color(self::CANCEL));
        });

        EditAction::configureUsing(fn (EditAction $action) => $action->defaultColor(self::EDIT));
        CreateAction::configureUsing(fn (CreateAction $action) => $action->defaultColor(self::CREATE));

        // ViewAction kendi setUp'inda "Kapat" dugmesini kurar (Action ayarini ezer).
        ViewAction::configureUsing(fn (ViewAction $action) => $action
            ->defaultColor(self::VIEW)
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label(__('filament-actions::view.single.modal.actions.close.label'))
                ->color(self::CANCEL)));

        Wizard::configureUsing(fn (Wizard $wizard) => $wizard
            ->nextAction(fn (Action $action): Action => $action->color(self::VIEW)));
    }

    /**
     * Pencere onay dugmesi (Kaydet / Olustur / Gonder / Onayla / Sil...):
     * yikici ve uyari eylemi kendi rengini korur; "... iptal et" eyleminin
     * onayi kirmizi olur (pencerenin Iptal dugmesi gul kalir, ikisi ayni renk
     * olmasin); digerleri yesil.
     */
    public static function submit(Action $action): Action
    {
        return match ($action->getColor()) {
            self::DELETE, 'warning' => $action,
            self::CANCEL => $action->color(self::DELETE),
            default => $action->color(self::SAVE),
        };
    }

    /** Sayfa alt dugmesi "Olustur" / "Kaydet". */
    public static function save(Action $action): Action
    {
        return $action->color(self::SAVE);
    }

    /** Sayfa alt dugmesi "Iptal". */
    public static function cancel(Action $action): Action
    {
        return $action->color(self::CANCEL);
    }
}
