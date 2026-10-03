<?php

declare(strict_types=1);

namespace Base\Tenant\Files;

use Base\Tenant\Models\File;
use GdImage;
use Illuminate\Support\Facades\Log;
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

    /**
     * 40 megapixels: any camera's full-size photo, and some 160 MB of GD
     * memory at four bytes a pixel.
     */
    public const DEFAULT_MAX_PIXELS = 40_000_000;

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
        if (! in_array($file->mime_type, self::SUPPORTED, true)) {
            return [];
        }

        $disk = $file->storage();
        $bytes = $disk->get($file->path);

        if ($bytes === null) {
            throw new RuntimeException("The original of file {$file->getKey()} is missing.");
        }

        // Before GD: the check reads only the header, and is the one thing
        // standing between a small file and a worker out of memory.
        if (! $this->withinPixelLimit($file, $bytes)) {
            return [];
        }

        if (! extension_loaded('gd')) {
            return [];
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
     * The most pixels an original may have before renditions are skipped;
     * null when there is no limit.
     */
    public static function maxPixels(): ?int
    {
        $limit = config('base-tenant.files.max_image_pixels', self::DEFAULT_MAX_PIXELS);

        return is_numeric($limit) && (int) $limit > 0 ? (int) $limit : null;
    }

    /**
     * A decompression bomb: a PNG of a few kilobytes can declare 50,000 x
     * 50,000 pixels, and GD allocates four bytes for each of them before
     * resizing anything -- some 10 GB for that one. The dimensions are in the
     * header, so they are read from there first, without decoding.
     *
     * Over the limit the original is kept as it is and simply gets no
     * renditions; the reason goes to the log.
     */
    protected function withinPixelLimit(File $file, string $bytes): bool
    {
        $limit = self::maxPixels();

        if ($limit === null) {
            return true;
        }

        $size = @getimagesizefromstring($bytes);

        if ($size === false) {
            return false;
        }

        [$width, $height] = $size;
        $pixels = (int) $width * (int) $height;

        if ($pixels <= $limit) {
            return true;
        }

        Log::warning('Image renditions skipped: the original exceeds files.max_image_pixels.', [
            'file' => $file->getKey(),
            'account' => $file->account_id,
            'width' => $width,
            'height' => $height,
            'pixels' => $pixels,
            'limit' => $limit,
        ]);

        return false;
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
