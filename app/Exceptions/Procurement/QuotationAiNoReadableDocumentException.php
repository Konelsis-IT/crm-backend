<?php

declare(strict_types=1);

namespace App\Exceptions\Procurement;

use App\Exceptions\AbstractException;

/** None of the uploaded files can be sent to Konelsis AI (type or size) (D-192). */
final class QuotationAiNoReadableDocumentException extends AbstractException {}
