<?php

declare(strict_types=1);

namespace App\Exceptions\Chat;

use App\Exceptions\AbstractException;

/** Belge bulunamadi, goruntuleme yetkisi yok ya da yayimli revizyonu yok. */
final class DocumentNotShareableException extends AbstractException
{
    protected $code = 4405;
}
