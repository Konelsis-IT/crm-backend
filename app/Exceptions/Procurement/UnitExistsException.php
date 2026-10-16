<?php

declare(strict_types=1);

namespace App\Exceptions\Procurement;

use App\Exceptions\AbstractException;

/** Ayni adli ya da ayni turde ayni simgeli birim zaten var (D-194). */
final class UnitExistsException extends AbstractException {}
