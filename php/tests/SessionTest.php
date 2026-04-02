<?php

declare(strict_types=1);

namespace HopTop\Xrr\Tests;

use HopTop\Xrr\AdapterInterface;
use HopTop\Xrr\Exception\CassetteMissException;
use HopTop\Xrr\FileCassette;
use HopTop\Xrr\Mode;
use HopTop\Xrr\Session;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    private function makeAdapter(string $id = 'exec', string $fp = 'testfp01'): AdapterInterface
    {
        return new class($id, $fp) implements AdapterInterface {
            public function __construct(private string $id, private string $fp) {}
            public function getId(): string { return $this->id; }
            public function fingerprint(mixed $req): string { return $this->fp; }
            public function serializeReq(mixed $req): array { return (array) $req; }
            public function serializeResp(mixed $resp): array { return (array) $resp; }
            public function deserializeReq(array $data): mixed { return $data; }
            public function deserializeResp(array $data): mixed { return $data; }
        };
    }

    public function testRecord(): void
    {
        $dir = sys_get_temp_dir() . '/xrr_' . uniqid();
        mkdir($dir);

        $cassette = new FileCassette($dir);
        $session  = new Session(Mode::Record, $cassette);
        $adapter  = $this->makeAdapter();

        $called = false;
        $result = $session->record($adapter, ['argv' => ['echo']], function ($req) use (&$called) {
            $called = true;
            return ['stdout' => 'hello', 'exit_code' => 0];
        });

        $this->assertTrue($called, 'do() must be called in record mode');
        $this->assertEquals(['stdout' => 'hello', 'exit_code' => 0], $result);
        $this->assertFileExists($dir . '/exec-testfp01.req.yaml');
        $this->assertFileExists($dir . '/exec-testfp01.resp.yaml');
    }

    public function testReplay(): void
    {
        $dir = sys_get_temp_dir() . '/xrr_' . uniqid();
        mkdir($dir);

        $cassette = new FileCassette($dir);
        $cassette->save('exec', 'a3f9c1b2', ['argv' => ['echo']], ['stdout' => 'hello', 'exit_code' => 0]);

        $session = new Session(Mode::Replay, $cassette);
        $adapter = $this->makeAdapter('exec', 'a3f9c1b2');

        $called = false;
        $result = $session->record($adapter, ['argv' => ['echo']], function ($req) use (&$called) {
            $called = true;
            return ['stdout' => 'should-not-be-returned'];
        });

        $this->assertFalse($called, 'do() must NOT be called in replay mode');
        $this->assertEquals('hello', $result['stdout']);
    }

    public function testReplayMissThrows(): void
    {
        $dir = sys_get_temp_dir() . '/xrr_' . uniqid();
        mkdir($dir);

        $session = new Session(Mode::Replay, new FileCassette($dir));
        $adapter = $this->makeAdapter('exec', 'deadbeef');

        $this->expectException(CassetteMissException::class);

        $session->record($adapter, [], fn($req) => null);
    }

    public function testPassthrough(): void
    {
        $dir = sys_get_temp_dir() . '/xrr_' . uniqid();
        mkdir($dir);

        $cassette = new FileCassette($dir);
        $session  = new Session(Mode::Passthrough, $cassette);
        $adapter  = $this->makeAdapter();

        $called = false;
        $result = $session->record($adapter, [], function ($req) use (&$called) {
            $called = true;
            return ['stdout' => 'passthrough-result'];
        });

        $this->assertTrue($called, 'do() must be called in passthrough mode');
        $this->assertEquals(['stdout' => 'passthrough-result'], $result);

        // No cassette files should be written
        $files = glob($dir . '/*.yaml');
        $this->assertCount(0, $files, 'Passthrough must not write cassette files');
    }
}
