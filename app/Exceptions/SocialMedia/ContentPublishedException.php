<?php

declare(strict_types=1);

namespace App\Exceptions\SocialMedia;

use App\Exceptions\AbstractException;

/** Paylasilmis icerik degistirilemez; once paylasim geri alinir. */
final class ContentPublishedException extends AbstractException
{
    protected $code = 4715;
}
