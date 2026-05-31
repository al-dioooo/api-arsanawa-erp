<?php

namespace App\Modules\Inventory\Services;

use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductImage;
use App\Modules\Inventory\Models\ProductUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductImageService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function storeFor(Model $imageable, int $companyId, User $user, array $data): ProductImage
    {
        if (! $imageable instanceof Product && ! $imageable instanceof ProductUnit) {
            throw ValidationException::withMessages(['image' => [__('Unsupported image owner.')]]);
        }

        $payload = isset($data['remote_url'])
            ? $this->copyRemoteImage((string) $data['remote_url'], $companyId)
            : $this->storeUploadedImage($data['image'], $companyId);

        if (($data['is_primary'] ?? false) === true) {
            $imageable->images()->update(['is_primary' => false]);
        }

        return $imageable->images()->create([
            'company_id' => $companyId,
            'disk' => 'public',
            'path' => $payload['path'],
            'url' => Storage::disk('public')->url($payload['path']),
            'original_url' => $payload['original_url'] ?? null,
            'alt_text' => $data['alt_text'] ?? null,
            'mime_type' => $payload['mime_type'],
            'size_bytes' => $payload['size_bytes'],
            'width' => $payload['width'],
            'height' => $payload['height'],
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * @return array{path: string, mime_type: string|null, size_bytes: int, width: int|null, height: int|null}
     */
    private function storeUploadedImage(UploadedFile $file, int $companyId): array
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw ValidationException::withMessages(['image' => [__('Unable to read uploaded image.')]]);
        }

        $path = $this->path($companyId, $file->extension() ?: 'jpg');
        Storage::disk('public')->put($path, $contents);
        [$width, $height] = $this->dimensions($contents);

        return [
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => strlen($contents),
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @return array{path: string, original_url: string, mime_type: string|null, size_bytes: int, width: int|null, height: int|null}
     */
    private function copyRemoteImage(string $url, int $companyId): array
    {
        $this->validateRemoteUrl($url);

        $response = Http::timeout(10)->get($url);

        if (! $response->successful()) {
            throw ValidationException::withMessages(['remote_url' => [__('Unable to download the remote image.')]]);
        }

        $mimeType = $response->header('Content-Type');
        $mimeType = is_string($mimeType) ? strtolower(strtok($mimeType, ';') ?: $mimeType) : null;

        if ($mimeType === null || ! str_starts_with($mimeType, 'image/')) {
            throw ValidationException::withMessages(['remote_url' => [__('The remote URL must return image content.')]]);
        }

        $contents = $response->body();
        [$width, $height] = $this->dimensions($contents);

        if ($width === null || $height === null) {
            throw ValidationException::withMessages(['remote_url' => [__('The remote URL must return a valid image.')]]);
        }

        $extension = match ($mimeType) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $path = $this->path($companyId, $extension);
        Storage::disk('public')->put($path, $contents);

        return [
            'path' => $path,
            'original_url' => $url,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($contents),
            'width' => $width,
            'height' => $height,
        ];
    }

    private function validateRemoteUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw ValidationException::withMessages(['remote_url' => [__('Only public HTTP(S) image URLs are supported.')]]);
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false && filter_var($host, FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages(['remote_url' => [__('Private network image URLs are not supported.')]]);
        }
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions(string $contents): array
    {
        $dimensions = @getimagesizefromstring($contents);

        if ($dimensions === false) {
            return [null, null];
        }

        return [(int) $dimensions[0], (int) $dimensions[1]];
    }

    private function path(int $companyId, string $extension): string
    {
        return 'inventory/product-images/'.$companyId.'/'.Str::uuid().'.'.$extension;
    }
}
