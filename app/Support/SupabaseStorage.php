<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SupabaseStorage
{
    public function put(string $bucket, string $path, string $contents, ?string $mimeType = null): void
    {
        $response = Http::withHeaders($this->headers())
            ->withBody($contents, $mimeType ?? 'application/octet-stream')
            ->post($this->objectUrl($bucket, $path));

        if (! $response->successful()) {
            throw new RuntimeException('Unable to upload object to Supabase Storage.');
        }
    }

    public function get(string $bucket, string $path): string
    {
        $response = Http::withHeaders($this->headers())
            ->get($this->objectUrl($bucket, $path));

        if (! $response->successful()) {
            throw new RuntimeException('Unable to read object from Supabase Storage.');
        }

        return $response->body();
    }

    public function publicUrl(string $bucket, string $path): string
    {
        return $this->baseUrl().'/storage/v1/object/public/'.$this->encodePath($bucket).'/'.$this->encodePath($path);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $key = (string) config('services.supabase.service_role_key');

        return [
            'apikey' => $key,
            'Authorization' => 'Bearer '.$key,
            'x-upsert' => 'true',
        ];
    }

    private function objectUrl(string $bucket, string $path): string
    {
        return $this->baseUrl().'/storage/v1/object/'.$this->encodePath($bucket).'/'.$this->encodePath($path);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.supabase.url'), '/');
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }
}
