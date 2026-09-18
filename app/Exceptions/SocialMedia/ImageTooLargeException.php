<?php

declare(strict_types=1);

namespace App\Exceptions\SocialMedia;

use App\Exceptions\AbstractException;

/** Gorsel islenemeyecek kadar buyuk (:max megapiksel). */
final class ImageTooLargeException extends AbstractException
{
    protected $code = 4706;
}
