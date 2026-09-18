<?php

declare(strict_types=1);

namespace App\Exceptions\SocialMedia;

use App\Exceptions\AbstractException;

/** Parcali yukleme oturumu gecersiz: sahibi, sirasi ya da boyutu tutmuyor. */
final class UploadSessionInvalidException extends AbstractException
{
    protected $code = 4708;
}
