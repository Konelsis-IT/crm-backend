<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\AbstractException;

/** Rapor taslagi bulunamadi. */
final class TemplateNotFoundException extends AbstractException
{
    protected $code = 4601;
}
