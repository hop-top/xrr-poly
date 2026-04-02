<?php

declare(strict_types=1);

namespace HopTop\Xrr\Adapters;

use HopTop\Xrr\AdapterInterface;

/**
 * Adapter for HTTP interactions.
 *
 * Request shape:  ['method' => string, 'url' => string, 'headers' => array, 'body' => string]
 * Response shape: ['status' => int, 'headers' => array, 'body' => string]
 *
 * Fingerprint fields: method + path+query (no host) + sha256(body)[:8]
 */
class HttpAdapter implements AdapterInterface
{
    public function getId(): string
    {
        return 'http';
    }

    public function fingerprint(mixed $req): string
    {
        $url      = $req['url'] ?? '';
        $parsed   = parse_url($url);
        $pathQuery = ($parsed['path'] ?? '/');
        if (!empty($parsed['query'])) {
            $pathQuery .= '?' . $parsed['query'];
        }

        $body     = $req['body'] ?? '';
        $bodyHash = substr(hash('sha256', $body), 0, 8);

        $fields = [
            'body_hash' => $bodyHash,
            'method'    => $req['method'] ?? 'GET',
            'path'      => $pathQuery,
        ];

        ksort($fields);
        $canonical = json_encode($fields, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return substr(hash('sha256', $canonical), 0, 8);
    }

    public function serializeReq(mixed $req): array
    {
        return [
            'method'  => $req['method']  ?? 'GET',
            'url'     => $req['url']     ?? '',
            'headers' => $req['headers'] ?? [],
            'body'    => $req['body']    ?? '',
        ];
    }

    public function serializeResp(mixed $resp): array
    {
        return [
            'status'  => $resp['status']  ?? 200,
            'headers' => $resp['headers'] ?? [],
            'body'    => $resp['body']    ?? '',
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
