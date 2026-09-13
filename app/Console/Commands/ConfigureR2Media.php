<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ConfigureR2Media extends Command
{
    protected $signature = 'media:configure-r2
        {--access-key= : Bucket-scoped R2 access key}
        {--secret= : Bucket-scoped R2 secret key}';

    protected $description = 'Configure the production R2 media disk and verify it with an immutable smoke object';

    public function handle(): int
    {
        $accessKey = trim((string) $this->option('access-key'));
        $secret = trim((string) $this->option('secret'));

        if ($accessKey === '' || $secret === '') {
            $this->error('R2 credentials are required.');

            return self::FAILURE;
        }

        $values = [
            'MEDIA_DISK' => 'r2',
            'MEDIA_LEGACY_DISK' => 'public',
            'MEDIA_R2_PREFIX' => 'r2/v1',
            'MEDIA_CACHE_CONTROL' => 'public, max-age=31536000, immutable',
            'R2_ACCESS_KEY_ID' => $accessKey,
            'R2_SECRET_ACCESS_KEY' => $secret,
            'R2_REGION' => 'auto',
            'R2_BUCKET' => 'tribunasyon-media',
            'R2_ENDPOINT' => 'https://ea8b0ce33868a2dad4117818f6cfe95f.r2.cloudflarestorage.com',
            'R2_PUBLIC_URL' => 'https://img.tribunasyon.com',
        ];

        $this->writeEnvironment($values);
        $this->configureRuntime($values);

        $path = 'r2/v1/system/production-smoke.txt';
        $written = Storage::disk('r2')->put($path, "Tribunasyon R2 production ready\n", [
            'CacheControl' => $values['MEDIA_CACHE_CONTROL'],
            'ContentType' => 'text/plain; charset=utf-8',
        ]);

        if (! $written || ! Storage::disk('r2')->exists($path)) {
            throw new RuntimeException('R2 smoke object could not be verified.');
        }

        $this->callSilent('config:clear');
        $this->info('R2 media configured and smoke object verified.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function writeEnvironment(array $values): void
    {
        $path = app()->environmentFilePath();
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('The environment file could not be read.');
        }

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->quote($value);
            $pattern = '/^'.preg_quote($key, '/').'\s*=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace($pattern, $line, $contents, 1);
            } else {
                $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
            }
        }

        $temporaryPath = $path.'.r2-tmp';

        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false || ! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new RuntimeException('The environment file could not be updated atomically.');
        }
    }

    /**
     * @param  array<string, string>  $values
     */
    private function configureRuntime(array $values): void
    {
        config([
            'media.disk' => 'r2',
            'media.legacy_disk' => 'public',
            'media.r2_prefix' => 'r2/v1',
            'filesystems.disks.r2.key' => $values['R2_ACCESS_KEY_ID'],
            'filesystems.disks.r2.secret' => $values['R2_SECRET_ACCESS_KEY'],
            'filesystems.disks.r2.region' => $values['R2_REGION'],
            'filesystems.disks.r2.bucket' => $values['R2_BUCKET'],
            'filesystems.disks.r2.endpoint' => $values['R2_ENDPOINT'],
            'filesystems.disks.r2.url' => $values['R2_PUBLIC_URL'],
        ]);

        Storage::forgetDisk('r2');
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
