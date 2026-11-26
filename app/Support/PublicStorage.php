<?php

declare(strict_types=1);

namespace App\Support;

final class PublicStorage
{
    /**
     * URL relatif ke file di disk "public", lewat symlink public/storage.
     */
    public static function url(string $path): string
    {
        return '/storage/'.ltrim(str_replace('\\', '/', $path), '/');
    }
}
