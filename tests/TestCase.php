<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureViteManifest();
    }

    private function ensureViteManifest(): void
    {
        $buildPath = public_path('build');

        if (! is_dir($buildPath)) {
            mkdir($buildPath, 0777, true);
        }

        $manifestPath = $buildPath.'/manifest.json';

        if (! is_file($manifestPath)) {
            file_put_contents($manifestPath, json_encode([
                'resources/css/app.css' => [
                    'file' => 'assets/app.css',
                    'src' => 'resources/css/app.css',
                    'isEntry' => true,
                ],
                'resources/js/app.js' => [
                    'file' => 'assets/app.js',
                    'src' => 'resources/js/app.js',
                    'isEntry' => true,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $assetsPath = $buildPath.'/assets';

        if (! is_dir($assetsPath)) {
            mkdir($assetsPath, 0777, true);
        }

        if (! is_file($assetsPath.'/app.css')) {
            file_put_contents($assetsPath.'/app.css', '/* test asset */');
        }

        if (! is_file($assetsPath.'/app.js')) {
            file_put_contents($assetsPath.'/app.js', '// test asset');
        }
    }
}
