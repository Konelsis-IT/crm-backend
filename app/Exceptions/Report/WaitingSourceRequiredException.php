<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\AbstractException;

/** Bekleniyor durumundaki kartta kimden beklendigi zorunludur (B36, D-115). */
final class WaitingSourceRequiredException extends AbstractException {}
