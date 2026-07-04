<?php

namespace App\Support;

class BrandLogo
{
    public static function fileUrl(): string
    {
        return 'file://'.str_replace(' ', '%20', public_path('Khmer House Key.png'));
    }

    public static function dataUri(): ?string
    {
        $path = public_path('Khmer House Key.png');

        if (! is_file($path)) {
            return null;
        }

        $image = file_get_contents($path);

        if ($image === false || $image === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($image);
    }

    public static function fallbackInitials(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('') ?: 'R';
    }
}
