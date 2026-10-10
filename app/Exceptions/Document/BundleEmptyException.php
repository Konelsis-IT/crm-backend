<?php

declare(strict_types=1);

namespace App\Exceptions\Document;

use App\Exceptions\AbstractException;

/**
 * "Tum belgeleri indir" (D-176): kayitta indirilebilecek (gorme yetkisi olan,
 * dosyasi diskte bulunan) belge yok. Mesaj dil dosyasindan gelir.
 */
final class BundleEmptyException extends AbstractException
{
    protected $code = 5102;
}
