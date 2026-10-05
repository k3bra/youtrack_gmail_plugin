<?php

namespace App\Services\Fix;

use RuntimeException;
use Throwable;

/**
 * A fix that failed after some work was done; carries the attributes gathered so far
 * (repo, branch, Claude usage and answer) so the failed record still shows them.
 */
class TicketFixFailed extends RuntimeException
{
    public function __construct(string $message, public readonly array $attributes, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
