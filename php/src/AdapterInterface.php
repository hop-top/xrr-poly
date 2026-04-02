<?php

declare(strict_types=1);

namespace HopTop\Xrr;

interface AdapterInterface
{
    public function getId(): string;

    public function fingerprint(mixed $req): string;

    /** @return array<string, mixed> */
    public function serializeReq(mixed $req): array;

    /** @return array<string, mixed> */
    public function serializeResp(mixed $resp): array;

    /** @param array<string, mixed> $data */
    public function deserializeReq(array $data): mixed;

    /** @param array<string, mixed> $data */
    public function deserializeResp(array $data): mixed;
}
