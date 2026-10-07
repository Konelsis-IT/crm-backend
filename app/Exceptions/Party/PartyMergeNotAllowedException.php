<?php

declare(strict_types=1);

namespace App\Exceptions\Party;

use App\Exceptions\AbstractException;

/**
 * Taraf birlestirilemez (D-170, PartyMergeService): kaynak ve hedef ayni
 * taraf, turleri farkli (kurulus / kisi), biri zaten birlestirilmis ya da
 * hedef arsivde.
 */
final class PartyMergeNotAllowedException extends AbstractException
{
    protected $code = 4803;
}
