<?php

declare(strict_types=1);

namespace App\Exceptions\Chat;

use App\Exceptions\AbstractException;

/** Mesajin metni, baglantisi, dosyasi ya da belgesi yok. */
final class EmptyMessageException extends AbstractException
{
    protected $code = 4402;
}
