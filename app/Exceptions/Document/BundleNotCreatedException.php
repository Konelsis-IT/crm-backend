<?php

declare(strict_types=1);

namespace App\Exceptions\Document;

use App\Exceptions\AbstractException;

/**
 * "Tum belgeleri indir" ZIP'i olusturulamadi (D-176): gecici dosya yazilamadi
 * ya da ZIP acilamadi. Mesaj dil dosyasindan gelir.
 */
final class BundleNotCreatedException extends AbstractException
{
    protected $code = 5101;
}
