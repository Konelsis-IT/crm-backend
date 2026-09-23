<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\AbstractException;

/** Baskasina kart yazmak icin amiri, departman yoneticisi ya da tum kartlari goren yetki gerekir (B36, D-115). */
final class AssigneeNotAllowedException extends AbstractException {}
