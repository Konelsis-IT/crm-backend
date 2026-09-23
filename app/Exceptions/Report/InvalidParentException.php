<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\AbstractException;

/** Ana is olarak kartin kendisi, alt karti ya da baska bir alt kart secilemez (B36, D-115). */
final class InvalidParentException extends AbstractException {}
