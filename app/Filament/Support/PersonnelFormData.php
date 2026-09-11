<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Personnel\Personnel;

/**
 * Formda gosterilmeyen alanlarin mevcut degerlerini korur.
 *
 * UpdatePersonnel butun karti tek seferde yazar. Bir alan ekranda yoksa
 * form verisinde de bulunmaz; bu yardimci, kayittaki mevcut degeri geri
 * koyarak alanin sessizce bosalmasini engeller.
 */
final class PersonnelFormData
{
    /**
     * Personel ekranindaki duzenleme formu icin.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function forEditScreen(array $data, Personnel $record): array
    {
        return array_merge(self::hiddenPreferences($record), $data);
    }

    /**
     * Kendi profili ekrani icin: gorev bilgileri ve yetkinlikler burada
     * duzenlenmez, oldugu gibi korunur.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function forProfileScreen(array $data, Personnel $record): array
    {
        return array_merge(self::hiddenPreferences($record), [
            'org_unit_id' => $record->org_unit_id,
            'job_title' => $record->job_title,
            'hired_on' => $record->hired_on?->format('Y-m-d'),
            'status' => $record->status,
        ], $data);
    }

    /**
     * Arayuzde gosterilmeyen alanlar: sicil no, dil ve saat dilimi.
     *
     * @return array<string, mixed>
     */
    private static function hiddenPreferences(Personnel $record): array
    {
        return [
            'personnel_no' => $record->personnel_no,
            'locale' => $record->locale,
            'timezone' => $record->timezone,
        ];
    }
}
