<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Telefon, e-posta ve WhatsApp baglantilarini uretir.
 *
 * Numaralar arayuzde girildigi gibi gosterilir; yalniz baglanti adresi
 * uretilirken normallestirilir.
 */
final class ContactLinks
{
    /** Varsayilan ulke kodu (Turkiye). */
    private const COUNTRY_CODE = '90';

    /** tel: baglantisi; numara yoksa null. */
    public static function tel(?string $phone): ?string
    {
        $digits = self::digits($phone);

        return $digits === null ? null : 'tel:+'.$digits;
    }

    /** mailto: baglantisi; adres yoksa null. */
    public static function mailto(?string $email): ?string
    {
        $email = trim((string) $email);

        return $email === '' ? null : 'mailto:'.$email;
    }

    /** WhatsApp sohbet baglantisi; numara yoksa null. */
    public static function whatsapp(?string $phone): ?string
    {
        $digits = self::digits($phone);

        return $digits === null ? null : 'https://wa.me/'.$digits;
    }

    /**
     * Numarayi uluslararasi bicime cevirir: yalniz rakamlar, basinda ulke kodu.
     * Anlamli bir numara cikmazsa null doner.
     */
    private static function digits(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        // 00 ile baslayan uluslararasi yazim
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Sehir ici yazim: 0 ile baslayan 11 haneli numara
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = self::COUNTRY_CODE.substr($digits, 1);
        }

        // Ulke kodu olmadan yazilmis 10 haneli numara
        if (strlen($digits) === 10) {
            $digits = self::COUNTRY_CODE.$digits;
        }

        return strlen($digits) >= 10 ? $digits : null;
    }
}
