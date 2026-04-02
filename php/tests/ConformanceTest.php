<?php

declare(strict_types=1);

namespace HopTop\Xrr\Tests;

use HopTop\Xrr\FileCassette;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class ConformanceTest extends TestCase
{
    private function fixturesDir(): string
    {
        return dirname(__DIR__, 2) . '/spec/fixtures';
    }

    public function testFixturesDirExists(): void
    {
        $this->assertDirectoryExists($this->fixturesDir());
    }

    public function testAllFixtures(): void
    {
        $fixturesDir = $this->fixturesDir();
        $entries     = array_filter(
            scandir($fixturesDir),
            fn($e) => $e !== '.' && $e !== '..' && is_dir($fixturesDir . '/' . $e)
        );

        $this->assertNotEmpty($entries, 'no fixture dirs found');

        foreach ($entries as $entry) {
            $dir          = $fixturesDir . '/' . $entry;
            $manifestPath = $dir . '/manifest.yaml';

            $this->assertFileExists($manifestPath, "manifest.yaml missing in $entry");

            $manifest     = Yaml::parseFile($manifestPath);
            $interactions = $manifest['interactions'] ?? [];

            $this->assertNotEmpty($interactions, "no interactions in $entry manifest");

            $cassette = new FileCassette($dir);

            foreach ($interactions as $interaction) {
                $adapter     = $interaction['adapter'];
                $fingerprint = $interaction['fingerprint'];

                $data = $cassette->load($adapter, $fingerprint);

                $this->assertArrayHasKey('req', $data,
                    "missing req for $adapter/$fingerprint in $entry");
                $this->assertArrayHasKey('resp', $data,
                    "missing resp for $adapter/$fingerprint in $entry");
            }
        }
    }
}
