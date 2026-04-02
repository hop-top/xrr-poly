<?php

declare(strict_types=1);

namespace HopTop\Xrr;

interface AdapterInterface
{
    public function getId(): string;

    public function fingerprint(mixed $req): string;

    public function serializeReq(mixed $req): array;

    public function serializeResp(mixed $resp): array;

    public function deserializeReq(array $data): mixed;

    public function deserializeResp(array $data): mixed;
}
