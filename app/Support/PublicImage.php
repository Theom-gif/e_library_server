<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicImage
{
    /**
     * @return array{path: string, url: string, original_name: string|null, mime_type: string|null, encrypted_name?: string, server_url?: string}|null
     */
    public static function storeUploaded(?UploadedFile $file, string $directory = 'avatars'): ?array
    {
        if (!$file) {
            return null;
        }

        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg';
        $path = trim($directory, '/').'/'.Str::random(40).'.'.$extension;

        Storage::disk('public')->putFileAs(trim($directory, '/'), $file, basename($path));

        $url = self::absoluteUrl(Storage::disk('public')->url($path));
        $encryptedName = hash('sha256', $path);
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg';
        $serverUrl = env('IMAGE_SERVER_URL') ? rtrim(env('IMAGE_SERVER_URL'), '/').'/'.$encryptedName.'.'.$extension : null;

        return [
            'path' => $path,
            'url' => $url,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'encrypted_name' => $encryptedName.'.'.$extension,
            'server_url' => $serverUrl,
        ];
    }

    /**
     * @return array{path: string|null, url: string, encrypted_name?: string, server_url?: string}|null
     */
    public static function normalize(?string $value, string $directory = 'avatars'): ?array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(https?:|data:)/i', $value)) {
            $encryptedName = hash('sha256', $value);
            $extension = pathinfo(parse_url($value, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'jpg';
            $serverUrl = env('IMAGE_SERVER_URL') ? rtrim(env('IMAGE_SERVER_URL'), '/').'/'.$encryptedName.'.'.$extension : null;

            return [
                'path' => null,
                'url' => $value,
                'encrypted_name' => $encryptedName.'.'.$extension,
                'server_url' => $serverUrl,
            ];
        }

        if (Storage::disk('public')->exists($value)) {
            $url = self::absoluteUrl(Storage::disk('public')->url($value));
            $extension = pathinfo($value, PATHINFO_EXTENSION) ?: 'jpg';
            $encryptedName = hash('sha256', $value);
            $serverUrl = env('IMAGE_SERVER_URL') ? rtrim(env('IMAGE_SERVER_URL'), '/').'/'.$encryptedName.'.'.$extension : null;

            return [
                'path' => $value,
                'url' => $url,
                'encrypted_name' => $encryptedName.'.'.$extension,
                'server_url' => $serverUrl,
            ];
        }

        $localPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $value);
        if (!file_exists($localPath) || !is_file($localPath)) {
            if (!preg_match('/^(?:[A-Za-z]:[\\\\\\/]|\\\\\\\\)/', $value)) {
                return [
                    'path' => $value,
                    'url' => self::absoluteUrl(Storage::disk('public')->url($value)),
                ];
            }

            return [
                'path' => null,
                'url' => $value,
            ];
        }

        $extension = pathinfo($localPath, PATHINFO_EXTENSION) ?: 'jpg';
        $path = trim($directory, '/').'/'.Str::random(40).'.'.$extension;

        Storage::disk('public')->put($path, file_get_contents($localPath));

        $url = self::absoluteUrl(Storage::disk('public')->url($path));
        $encryptedName = hash('sha256', $path);
        $serverUrl = env('IMAGE_SERVER_URL') ? rtrim(env('IMAGE_SERVER_URL'), '/').'/'.$encryptedName.'.'.$extension : null;

        return [
            'path' => $path,
            'url' => $url,
            'encrypted_name' => $encryptedName.'.'.$extension,
            'server_url' => $serverUrl,
        ];
    }

    private static function absoluteUrl(string $url): string
    {
        if (preg_match('/^(https?:|data:)/i', $url)) {
            return $url;
        }

        return rtrim((string) config('app.url', env('APP_URL', 'http://localhost')), '/').'/'.ltrim($url, '/');
    }
}
