<?php

declare(strict_types=1);

namespace App\Exceptions\Procurement;

use App\Exceptions\AbstractException;

/** Taban birim donusumu olan birimin turu degistirilemez (D-194). */
final class UnitDimensionLockedException extends AbstractException {}
