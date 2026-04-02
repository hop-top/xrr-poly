<?php

declare(strict_types=1);

namespace HopTop\Xrr\Adapters;

use HopTop\Xrr\AdapterInterface;

/**
 * Adapter for Redis interactions.
 *
 * Request shape:  ['command' => string, 'args' => string[]]
 * Response shape: ['result' => mixed]
 *
 * Fingerprint fields: strtoupper(command) + implode(' ', args)
 */
class RedisAdapter implements AdapterInterface
{
    public function getId(): string
    {
        return 'redis';
    }

    public function fingerprint(mixed $req): string
    {
        $command = strtoupper($req['command'] ?? '');
        $args    = $req['args'] ?? [];
        $parts   = array_merge([$command], $args);
        $joined  = implode(' ', $parts);

        $canonical = json_encode($joined, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return substr(hash('sha256', $canonical), 0, 8);
    }

    public function serializeReq(mixed $req): array
    {
        return [
            'command' => $req['command'] ?? '',
            'args'    => $req['args']    ?? [],
        ];
    }

    public function serializeResp(mixed $resp): array
    {
        return [
            'result' => $resp['result'] ?? null,
        ];
    }

    public function deserializeReq(array $data): mixed
    {
        return $data;
    }

    public function deserializeResp(array $data): mixed
    {
        return $data;
    }
}
