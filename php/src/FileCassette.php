<?php

declare(strict_types=1);

namespace HopTop\Xrr;

use HopTop\Xrr\Exception\CassetteMissException;
use Symfony\Component\Yaml\Yaml;

class FileCassette
{
    public function __construct(private string $dir) {}

    /**
     * Save request and response payloads as two YAML cassette files.
     */
    public function save(string $adapterID, string $fingerprint, array $req, array $resp): void
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');

        $this->write($adapterID, $fingerprint, 'req', $now, $req);
        $this->write($adapterID, $fingerprint, 'resp', $now, $resp);
    }

    private function write(
        string $adapterID,
        string $fingerprint,
        string $kind,
        string $recordedAt,
        array $payload
    ): void {
        $envelope = [
            'xrr'         => '1',
            'adapter'     => $adapterID,
            'fingerprint' => $fingerprint,
            'recorded_at' => $recordedAt,
            'payload'     => $payload,
        ];

        $path = $this->path($adapterID, $fingerprint, $kind);
        file_put_contents($path, Yaml::dump($envelope, 4, 2));
    }

    /**
     * Load request and response payloads from cassette files.
     *
     * @return array{req: array, resp: array}
     * @throws CassetteMissException
     */
    public function load(string $adapterID, string $fingerprint): array
    {
        $req  = $this->read($adapterID, $fingerprint, 'req');
        $resp = $this->read($adapterID, $fingerprint, 'resp');

        return ['req' => $req, 'resp' => $resp];
    }

    private function read(string $adapterID, string $fingerprint, string $kind): array
    {
        $path = $this->path($adapterID, $fingerprint, $kind);

        if (!file_exists($path)) {
            throw new CassetteMissException($adapterID, $fingerprint);
        }

        $envelope = Yaml::parseFile($path);

        if (!isset($envelope['payload']) || !is_array($envelope['payload'])) {
            throw new \RuntimeException(
                sprintf('xrr: missing or invalid payload in %s', $path)
            );
        }

        return $envelope['payload'];
    }

    private function path(string $adapterID, string $fingerprint, string $kind): string
    {
        return sprintf('%s/%s-%s.%s.yaml', $this->dir, $adapterID, $fingerprint, $kind);
    }
}
