<?php

declare(strict_types=1);

namespace HopTop\Xrr;

enum Mode: string
{
    case Record      = 'record';
    case Replay      = 'replay';
    case Passthrough = 'passthrough';
}
