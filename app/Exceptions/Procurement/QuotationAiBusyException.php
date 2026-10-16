<?php

declare(strict_types=1);

namespace App\Exceptions\Procurement;

use App\Exceptions\AbstractException;

/** Konelsis AI is already reading this quotation's documents (D-192). */
final class QuotationAiBusyException extends AbstractException {}
