<?php

declare(strict_types=1);

namespace Base\Tenant\Files;

use Base\Tenant\Models\File;
use GdImage;
use RuntimeException;

/**
 * Derived renditions of an image, on GD.
 *
 * GD rather than an image library because it ships with almost every PHP
 * build: a thumbnail is not worth adding a dependency the host then has to
 * carry, and the two operations needed here -- cover and contain -- are a
 * dozen lines each.
 */
class ImageVariants
{
    public const SUPPORTED = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public static function supports(string $mimeType): bool
    {
        return in_array($mimeType, self::SUPPORTED, true) && extension_loaded('gd');
    }

    /**
     * Write every declared rendition next to the original and return their
     * paths, keyed by name.
     *
     * @param  array<string, array{width?: int, height?: int, fit?: string}>  $variants
     * @return array<string, string>
     */
    public function generate(File $file, array $variants): array
    {
        if (! self::supports($file->mime_type)) {
            return [];
        }

        $disk = $file->storage();
        $bytes = $disk->get($file->path);

        if ($bytes === null) {
            throw new RuntimeException("The original of file {$file->getKey()} is missing.");
        }

        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            return [];
        }

        $written = [];

        foreach ($variants as $name => $spec) {
            $rendition = $this->resize(
                $source,
                (int) ($spec['width'] ?? 0),
                (int) ($spec['height'] ?? 0),
                $spec['fit'] ?? 'contain',
            );

            if ($rendition === null) {
                continue;
            }

            $path = $file->directory().'/'.$name.'.'.$this->extensionFor($file->mime_type);

            $disk->put($path, $this->encode($rendition, $file->mime_type));
            imagedestroy($rendition);

            $written[$name] = $path;
        }

        imagedestroy($source);

        return $written;
    }

    /**
     * `cover` fills the box and crops the overflow; `contain` fits inside it.
     *
     * Neither ever enlarges: blowing a 100 px avatar up to 1200 produces a
     * larger file that looks worse than the original.
     */
    protected function resize(GdImage $source, int $width, int $height, string $fit): ?GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($width === 0 && $height === 0) {
            return null;
        }

        if ($fit === 'cover' && $width > 0 && $height > 0) {
            $scale = max($width / $sourceWidth, $height / $sourceHeight);

            if ($scale >= 1) {
                $scale = 1;
                $width = min($width, $sourceWidth);
                $height = min($height, $sourceHeight);
            }

            $scaledWidth = (int) ceil($sourceWidth * $scale);
            $scaledHeight = (int) ceil($sourceHeight * $scale);

            $target = imagecreatetruecolor($width, $height);
            $this->preserveTransparency($target);

            imagecopyresampled(
                $target,
                $source,
                0,
                0,
                (int) (($scaledWidth - $width) / 2 / $scale),
                (int) (($scaledHeight - $height) / 2 / $scale),
                $scaledWidth,
                $scaledHeight,
                $sourceWidth,
                $sourceHeight,
            );

            return $target;
        }

        $scale = min(
            $width > 0 ? $width / $sourceWidth : PHP_INT_MAX,
            $height > 0 ? $height / $sourceHeight : PHP_INT_MAX,
            1,
        );

        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        $this->preserveTransparency($target);

        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        return $target;
    }

    /**
     * Without this a transparent PNG comes back with a black background,
     * because a true-colour canvas starts filled with index zero.
     */
    protected function preserveTransparency(GdImage $image): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);

        if ($transparent !== false) {
            imagefill($image, 0, 0, $transparent);
        }

        imagealphablending($image, true);
    }

    protected function encode(GdImage $image, string $mimeType): string
    {
        ob_start();

        match ($mimeType) {
            'image/png' => imagepng($image, null, 6),
            'image/gif' => imagegif($image),
            'image/webp' => imagewebp($image, null, 82),
            default => imagejpeg($image, null, 82),
        };

        return (string) ob_get_clean();
    }

    protected function extensionFor(string $mimeType): string
    {
        return match ($mimeType) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
