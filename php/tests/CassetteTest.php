<?php

declare(strict_types=1);

namespace HopTop\Xrr\Tests;

use HopTop\Xrr\Exception\CassetteMissException;
use HopTop\Xrr\FileCassette;
use PHPUnit\Framework\TestCase;

class CassetteTest extends TestCase
{
    public function testSaveLoadRoundtrip(): void
    {
        $dir = sys_get_temp_dir() . '/xrr_' . uniqid();
        mkdir($dir);
        $c = new FileCassette($dir);
        $c->save('exec', 'a3f9c1b2', ['argv' => ['gh', 'pr']], ['stdout' => 'ok', 'exit_code' => 0]);
        $data = $c->load('exec', 'a3f9c1b2');
        $this->assertEquals(['argv' => ['gh', 'pr']], $data['req']);
        $this->assertEquals(['stdout' => 'ok', 'exit_code' => 0], $data['resp']);
    }

    public function testLoadMissingThrows(): void
    {
        $dir = sys_get_temp_dir() . '/xrr_' . uniqid();
        mkdir($dir);
        $c = new FileCassette($dir);

        $this->expectException(CassetteMissException::class);
        $c->load('exec', 'deadbeef');
    }

    public function testSaveCreatesFiles(): void
    {
        $dir = sys_get_temp_dir() . '/xrr_' . uniqid();
        mkdir($dir);
        $c = new FileCassette($dir);
        $c->save('http', 'ab12cd34', ['method' => 'GET'], ['status' => 200]);

        $this->assertFileExists($dir . '/http-ab12cd34.req.yaml');
        $this->assertFileExists($dir . '/http-ab12cd34.resp.yaml');
    }

    public function testEnvelopeContainsRequiredFields(): void
    {
        $dir = sys_get_temp_dir() . '/xrr_' . uniqid();
        mkdir($dir);
        $c = new FileCassette($dir);
        $c->save('exec', 'a3f9c1b2', ['argv' => ['ls']], ['stdout' => '']);

        $content = file_get_contents($dir . '/exec-a3f9c1b2.req.yaml');
        $this->assertStringContainsString('xrr:', $content);
        $this->assertStringContainsString('adapter: exec', $content);
        $this->assertStringContainsString("fingerprint: a3f9c1b2", $content);
        $this->assertStringContainsString('recorded_at:', $content);
        $this->assertStringContainsString('payload:', $content);
    }
}
