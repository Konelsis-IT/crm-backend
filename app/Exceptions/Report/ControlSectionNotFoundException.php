<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\AbstractException;

/** Kontrol matrisinde tanimsiz bolum (B36, D-115). */
final class ControlSectionNotFoundException extends AbstractException {}
