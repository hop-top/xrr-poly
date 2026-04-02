<?php

declare(strict_types=1);

namespace HopTop\Xrr\Tests;

use HopTop\Xrr\Adapters\ExecAdapter;
use HopTop\Xrr\Adapters\HttpAdapter;
use HopTop\Xrr\Adapters\RedisAdapter;
use HopTop\Xrr\Adapters\SqlAdapter;
use HopTop\Xrr\Exception\CassetteMissException;
use HopTop\Xrr\FileCassette;
use HopTop\Xrr\Mode;
use HopTop\Xrr\Session;
use PHPUnit\Framework\TestCase;

/**
 * E2e adapter tests — record + replay round-trips, plus cassette-miss guard.
 *
 * US-0101 (record first cassette)
 * US-0102 (replay in CI)
 * US-0104 (adapter selection)
 * US-0105 (cassette miss)
 */
class E2eTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/xrr-e2e-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir, 0700, true);
    }

    protected function tearDown(): void
    {
        // Clean up cassette files written during the test.
        foreach (glob($this->tmpDir . '/*.yaml') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // Exec adapter
    // -------------------------------------------------------------------------

    /**
     * US-0101, US-0102, US-0104
     * Record a shell command result, then replay — assert stdout matches.
     */
    public function testExecRecordReplay(): void
    {
        $adapter = new ExecAdapter();
        $req     = ['argv' => ['echo', 'hello'], 'stdin' => '', 'env' => []];

        // Record phase — $do returns a fake response (no real subprocess needed).
        $recordSession = new Session(Mode::Record, new FileCassette($this->tmpDir));
        $recorded      = $recordSession->record(
            $adapter,
            $req,
            fn($r) => ['stdout' => 'hello', 'stderr' => '', 'exit_code' => 0, 'duration_ms' => 1]
        );

        $this->assertSame('hello', $recorded['stdout']);
        $this->assertSame(0, $recorded['exit_code']);

        // Replay phase — $do must NOT be called; cassette must supply the response.
        $replaySession = new Session(Mode::Replay, new FileCassette($this->tmpDir));
        $replayed      = $replaySession->record(
            $adapter,
            $req,
            fn($r) => $this->fail('$do must not be called in Replay mode')
        );

        $this->assertSame($recorded['stdout'],    $replayed['stdout']);
        $this->assertSame($recorded['exit_code'], $replayed['exit_code']);
    }

    /**
     * US-0105
     * Replay on unknown exec request must throw CassetteMissException.
     */
    public function testExecReplayMissThrows(): void
    {
        $this->expectException(CassetteMissException::class);

        $adapter = new ExecAdapter();
        $req     = ['argv' => ['unknown-cmd'], 'stdin' => '', 'env' => []];

        $session = new Session(Mode::Replay, new FileCassette($this->tmpDir));
        $session->record($adapter, $req, fn($r) => null);
    }

    // -------------------------------------------------------------------------
    // HTTP adapter
    // -------------------------------------------------------------------------

    /**
     * US-0101, US-0102, US-0104
     * Record an HTTP GET response, then replay — assert status + body match.
     */
    public function testHttpRecordReplay(): void
    {
        $adapter = new HttpAdapter();
        $req     = [
            'method'  => 'GET',
            'url'     => 'https://example.com/api/ping',
            'headers' => ['Accept' => 'application/json'],
            'body'    => '',
        ];

        $fakeResp = ['status' => 200, 'headers' => ['Content-Type' => 'application/json'], 'body' => '{"ok":true}'];

        $recordSession = new Session(Mode::Record, new FileCassette($this->tmpDir));
        $recorded      = $recordSession->record($adapter, $req, fn($r) => $fakeResp);

        $this->assertSame(200, $recorded['status']);
        $this->assertSame('{"ok":true}', $recorded['body']);

        $replaySession = new Session(Mode::Replay, new FileCassette($this->tmpDir));
        $replayed      = $replaySession->record(
            $adapter,
            $req,
            fn($r) => $this->fail('$do must not be called in Replay mode')
        );

        $this->assertSame($recorded['status'], $replayed['status']);
        $this->assertSame($recorded['body'],   $replayed['body']);
    }

    /**
     * US-0105
     * Replay on unknown HTTP request must throw CassetteMissException.
     */
    public function testHttpReplayMissThrows(): void
    {
        $this->expectException(CassetteMissException::class);

        $adapter = new HttpAdapter();
        $req     = ['method' => 'DELETE', 'url' => 'https://example.com/no-such-resource', 'headers' => [], 'body' => ''];

        $session = new Session(Mode::Replay, new FileCassette($this->tmpDir));
        $session->record($adapter, $req, fn($r) => null);
    }

    // -------------------------------------------------------------------------
    // Redis adapter
    // -------------------------------------------------------------------------

    /**
     * US-0101, US-0102, US-0104
     * Record a Redis GET result, then replay — assert result matches.
     */
    public function testRedisRecordReplay(): void
    {
        $adapter = new RedisAdapter();
        $req     = ['command' => 'GET', 'args' => ['my-key']];

        $fakeResp = ['result' => 'my-value'];

        $recordSession = new Session(Mode::Record, new FileCassette($this->tmpDir));
        $recorded      = $recordSession->record($adapter, $req, fn($r) => $fakeResp);

        $this->assertSame('my-value', $recorded['result']);

        $replaySession = new Session(Mode::Replay, new FileCassette($this->tmpDir));
        $replayed      = $replaySession->record(
            $adapter,
            $req,
            fn($r) => $this->fail('$do must not be called in Replay mode')
        );

        $this->assertSame($recorded['result'], $replayed['result']);
    }

    /**
     * US-0105
     * Replay on unknown Redis command must throw CassetteMissException.
     */
    public function testRedisReplayMissThrows(): void
    {
        $this->expectException(CassetteMissException::class);

        $adapter = new RedisAdapter();
        $req     = ['command' => 'HGET', 'args' => ['no-hash', 'no-field']];

        $session = new Session(Mode::Replay, new FileCassette($this->tmpDir));
        $session->record($adapter, $req, fn($r) => null);
    }

    // -------------------------------------------------------------------------
    // SQL adapter
    // -------------------------------------------------------------------------

    /**
     * US-0101, US-0102, US-0104
     * Record a SQL SELECT result, then replay — assert rows + affected match.
     */
    public function testSqlRecordReplay(): void
    {
        $adapter = new SqlAdapter();
        $req     = ['query' => 'SELECT id, name FROM users WHERE id = ?', 'args' => [42]];

        $fakeResp = ['rows' => [['id' => 42, 'name' => 'Alice']], 'affected' => 0];

        $recordSession = new Session(Mode::Record, new FileCassette($this->tmpDir));
        $recorded      = $recordSession->record($adapter, $req, fn($r) => $fakeResp);

        $this->assertCount(1, $recorded['rows']);
        $this->assertSame('Alice', $recorded['rows'][0]['name']);

        $replaySession = new Session(Mode::Replay, new FileCassette($this->tmpDir));
        $replayed      = $replaySession->record(
            $adapter,
            $req,
            fn($r) => $this->fail('$do must not be called in Replay mode')
        );

        $this->assertSame($recorded['rows'],     $replayed['rows']);
        $this->assertSame($recorded['affected'], $replayed['affected']);
    }

    /**
     * US-0105
     * Replay on unknown SQL query must throw CassetteMissException.
     */
    public function testSqlReplayMissThrows(): void
    {
        $this->expectException(CassetteMissException::class);

        $adapter = new SqlAdapter();
        $req     = ['query' => 'DROP TABLE users', 'args' => []];

        $session = new Session(Mode::Replay, new FileCassette($this->tmpDir));
        $session->record($adapter, $req, fn($r) => null);
    }
}
