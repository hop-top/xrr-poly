<?php

declare(strict_types=1);

namespace HopTop\Xrr\Adapters;

use HopTop\Xrr\AdapterInterface;

/**
 * Adapter for SQL interactions.
 *
 * Request shape:  ['query' => string, 'args' => array]
 * Response shape: ['rows' => array, 'affected' => int]
 *
 * Fingerprint fields: normalized query (strtolower + collapse whitespace) + args
 */
class SqlAdapter implements AdapterInterface
{
    public function getId(): string
    {
        return 'sql';
    }

    public function fingerprint(mixed $req): string
    {
        $query = $this->normalizeQuery($req['query'] ?? '');
        $args  = $req['args'] ?? [];

        $fields = [
            'args'  => $args,
            'query' => $query,
        ];

        ksort($fields);
        $canonical = json_encode($fields, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return substr(hash('sha256', $canonical), 0, 8);
    }

    private function normalizeQuery(string $query): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower($query)));
    }

    public function serializeReq(mixed $req): array
    {
        return [
            'query' => $req['query'] ?? '',
            'args'  => $req['args']  ?? [],
        ];
    }

    public function serializeResp(mixed $resp): array
    {
        return [
            'rows'     => $resp['rows']     ?? [],
            'affected' => $resp['affected'] ?? 0,
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
