<?php

declare(strict_types=1);

namespace HopTop\Xrr\Exception;

use RuntimeException;

class CassetteMissException extends RuntimeException
{
    public function __construct(string $adapterID, string $fingerprint)
    {
        parent::__construct(
            sprintf('xrr: cassette miss — adapter=%s fingerprint=%s', $adapterID, $fingerprint)
        );
    }
}
