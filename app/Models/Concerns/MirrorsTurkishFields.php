<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Ingilizce ad alanlari arayuzde yoktur (D-131, 28 Eylul 2026 kullanici karari:
 * "Ingilizce ad ifadesini ve ilgili kolon, input'lari projeden kaldir. Zaten
 * turkce seciliyken turkce olacak."). Kolonlar veritabaninda kalir (cogu zorunlu);
 * kayit sirasinda Ingilizce alan bossa ya da Turkce karsiligi degistiyse Turkce
 * degerle doldurulur. Seed'lerin yazdigi Ingilizce ad, Turkce ad degismedikce korunur.
 */
trait MirrorsTurkishFields
{
    public static function bootMirrorsTurkishFields(): void
    {
        static::saving(function (Model $model): void {
            foreach ($model->mirroredLocaleFields() as $english => $turkish) {
                $value = $model->getAttribute($turkish);

                if ($value === null) {
                    continue;
                }

                if (blank($model->getAttribute($english)) || ($model->isDirty($turkish) && ! $model->isDirty($english))) {
                    $model->setAttribute($english, $value);
                }
            }
        });
    }

    /**
     * Ingilizce alan => Turkce alan.
     *
     * @return array<string, string>
     */
    public function mirroredLocaleFields(): array
    {
        return ['name_en' => 'name_tr'];
    }
}
