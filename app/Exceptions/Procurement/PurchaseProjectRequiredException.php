<?php

declare(strict_types=1);

namespace App\Exceptions\Procurement;

use App\Exceptions\AbstractException;

/** D-230: Saha'ya satin alma talebinde / kaleminde proje secimi zorunludur. */
final class PurchaseProjectRequiredException extends AbstractException {}
