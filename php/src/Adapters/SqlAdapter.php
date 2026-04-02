<?php

declare(strict_types=1);

namespace HopTop\Xrr\Adapters;

use HopTop\Xrr\AdapterInterface;

/**
 * Adapter for SQL interactions.
 *
 * Request shape:  ['query' => string, 'args' => array<mixed>]
 * Response shape: ['rows' => array<int, array<string, mixed>>, 'affected' => int]
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
        /** @var array<string, mixed> $req */
        $rawQuery = $req['query'] ?? '';
        $query    = $this->normalizeQuery(is_string($rawQuery) ? $rawQuery : '');
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
        return trim(preg_replace('/\s+/', ' ', strtolower($query)) ?? $query);
    }

    /** @return array<string, mixed> */
    public function serializeReq(mixed $req): array
    {
        /** @var array<string, mixed> $req */
        return [
            'query' => $req['query'] ?? '',
            'args'  => $req['args']  ?? [],
        ];
    }

    /** @return array<string, mixed> */
    public function serializeResp(mixed $resp): array
    {
        /** @var array<string, mixed> $resp */
        return [
            'rows'     => $resp['rows']     ?? [],
            'affected' => $resp['affected'] ?? 0,
        ];
    }

    /** @param array<string, mixed> $data */
    public function deserializeReq(array $data): mixed
    {
        return $data;
    }

    /** @param array<string, mixed> $data */
    public function deserializeResp(array $data): mixed
    {
        return $data;
    }
}
