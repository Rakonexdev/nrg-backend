<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaStorageService
{
    public function store(UploadedFile $file, string $directory): array
    {
        return $this->useBunny()
            ? $this->storeToBunny($file, $directory)
            : $this->storeLocally($file, $directory);
    }

    public function archiveIfReplaced(?string $oldPathOrUrl): void
    {
        if (!$oldPathOrUrl) {
            return;
        }

        if ($this->useBunny() && str_contains($oldPathOrUrl, '/')) {
            $archivePath = 'archive/' . now()->format('Y/m') . '/' . basename($oldPathOrUrl);
            try {
                $response = Http::withHeaders([
                    'AccessKey' => Setting::getValue('bunny.access_key'),
                ])->get($this->bunnyBaseUrl($this->extractBunnyPath($oldPathOrUrl)));

                if ($response->successful()) {
                    Http::withHeaders(['AccessKey' => Setting::getValue('bunny.access_key')])
                        ->withBody($response->body(), 'application/octet-stream')
                        ->put($this->bunnyBaseUrl($archivePath));
                    Http::withHeaders(['AccessKey' => Setting::getValue('bunny.access_key')])
                        ->delete($this->bunnyBaseUrl($this->extractBunnyPath($oldPathOrUrl)));
                }
            } catch (\Throwable) {
            }
            return;
        }

        if (Storage::disk('public')->exists($oldPathOrUrl)) {
            $archivePath = 'archive/' . now()->format('Y/m') . '/' . basename($oldPathOrUrl);
            Storage::disk('public')->move($oldPathOrUrl, $archivePath);
        }
    }

    public function delete(?string $path): bool
    {
        if (!$path) {
            return false;
        }

        if ($this->useBunny()) {
            $response = Http::withHeaders([
                'AccessKey' => Setting::getValue('bunny.access_key'),
            ])->delete($this->bunnyBaseUrl($this->extractBunnyPath($path)));

            return $response->successful();
        }

        return Storage::disk('public')->delete($path);
    }

    public function testConnection(): array
    {
        if (!$this->useBunny()) {
            return [
                'ok' => true,
                'message' => 'Using local public storage. Bunny is disabled.',
            ];
        }

        $response = Http::withHeaders([
            'AccessKey' => Setting::getValue('bunny.access_key'),
        ])->get($this->bunnyBaseUrl(''));

        return [
            'ok' => $response->successful(),
            'message' => $response->successful() ? 'Connected to Bunny storage successfully.' : 'Unable to connect to Bunny storage with the current settings.',
        ];
    }

    private function storeLocally(UploadedFile $file, string $directory): array
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $filename, 'public');

        return [
            'path' => $path,
            'url' => Storage::url($path),
            'disk' => 'public',
        ];
    }

    private function storeToBunny(UploadedFile $file, string $directory): array
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = trim($directory . '/' . $filename, '/');

        Http::withHeaders([
            'AccessKey' => Setting::getValue('bunny.access_key'),
            'Content-Type' => $file->getMimeType() ?: 'application/octet-stream',
        ])->withBody(file_get_contents($file->getRealPath()), $file->getMimeType() ?: 'application/octet-stream')
            ->put($this->bunnyBaseUrl($path))
            ->throw();

        $pullZone = rtrim((string) Setting::getValue('bunny.pull_zone'), '/');
        $baseUrl = str_starts_with($pullZone, 'http') ? $pullZone : 'https://' . $pullZone;

        return [
            'path' => $path,
            'url' => $baseUrl . '/' . $path,
            'disk' => 'bunny',
        ];
    }

    private function bunnyBaseUrl(string $path): string
    {
        $zone = trim((string) Setting::getValue('bunny.storage_zone'), '/');
        return 'https://storage.bunnycdn.com/' . $zone . '/' . ltrim($path, '/');
    }

    private function extractBunnyPath(string $urlOrPath): string
    {
        if (str_starts_with($urlOrPath, 'http')) {
            $parts = parse_url($urlOrPath);
            return ltrim($parts['path'] ?? '', '/');
        }

        return ltrim($urlOrPath, '/');
    }

    private function useBunny(): bool
    {
        return filter_var(Setting::getValue('bunny.enabled', false), FILTER_VALIDATE_BOOL)
            && Setting::getValue('bunny.storage_zone')
            && Setting::getValue('bunny.access_key')
            && Setting::getValue('bunny.pull_zone');
    }
}
