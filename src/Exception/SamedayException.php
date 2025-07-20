<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\Exception;

use Exception;

class SamedayException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
