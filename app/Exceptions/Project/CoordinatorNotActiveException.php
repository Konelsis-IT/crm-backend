<?php

declare(strict_types=1);

namespace App\Exceptions\Project;

use App\Exceptions\AbstractException;

/**
 * Proje tipi koordinatoru olarak aktif olmayan (ya da bulunamayan) personel
 * secildi (B49, D-175).
 *
 * Mesaj dil dosyasindaki exceptions anahtarindan gelir.
 */
final class CoordinatorNotActiveException extends AbstractException
{
    protected $code = 4212;
}
