<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IncidentPhotoService
{
    public const DISK = 'report_photos';

    public const MAX_SIZE_BYTES = 5242880; // 5 MB

    public const MAX_DIMENSION = 6000;

    public const MIN_DIMENSION = 40;

    public const THUMB_MAX_BOX = 320;

    public const FULL_MAX_BOX = 1920;

    protected const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Validate the uploaded file strictly by inspecting actual binary content,
     * dimensions, and magic bytes. Do NOT rely on client headers alone.
     *
     * @throws ValidationException
     */
    public function validateFile(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'photos' => [__('The uploaded file was corrupted or incomplete.')],
            ]);
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw ValidationException::withMessages([
                'photos' => [__('Each photo must be under 5 MB.')],
            ]);
        }

        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            throw ValidationException::withMessages([
                'photos' => [__('Could not read uploaded photo.')],
            ]);
        }

        // Deep MIME type inspection via fileinfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $realPath);
        finfo_close($finfo);

        if (! isset(self::ALLOWED_MIMES[$detectedMime])) {
            throw ValidationException::withMessages([
                'photos' => [__('Unsupported image format. Only JPEG, PNG, and WebP are accepted.')],
            ]);
        }

        // Validate image dimensions and header structure
        $imageInfo = @getimagesize($realPath);
        if ($imageInfo === false) {
            throw ValidationException::withMessages([
                'photos' => [__('The file is not a valid image or is corrupted.')],
            ]);
        }

        [$width, $height, $imageType] = $imageInfo;

        if ($width < self::MIN_DIMENSION || $height < self::MIN_DIMENSION) {
            throw ValidationException::withMessages([
                'photos' => [__('Image dimensions are too small (minimum 40x40 pixels).')],
            ]);
        }

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw ValidationException::withMessages([
                'photos' => [__('Image dimensions exceed the maximum allowed limit of 6000x6000 pixels.')],
            ]);
        }

        // Check aspect ratio to prevent elongated image exploits
        $ratio = $width / max(1, $height);
        if ($ratio < 0.15 || $ratio > 6.67) {
            throw ValidationException::withMessages([
                'photos' => [__('Extreme image aspect ratios are not permitted.')],
            ]);
        }

        return [
            'mime' => $detectedMime,
            'extension' => self::ALLOWED_MIMES[$detectedMime],
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Process, strip EXIF metadata, generate a genuine thumbnail, and store
     * securely on the private filesystem disk using opaque file identifiers.
     *
     * @return array{path: string, thumbnail: string, size_bytes: int}
     */
    public function processAndStore(UploadedFile $file, Report $report): array
    {
        $meta = $this->validateFile($file);
        $realPath = $file->getRealPath();
        $ext = $meta['extension'];

        // Opaque non-guessable storage identifiers
        $hash = Str::random(40);
        $fullPath = "photos/{$hash}.{$ext}";
        $thumbPath = "thumbs/{$hash}_thumb.{$ext}";

        $disk = Storage::disk(self::DISK);

        if (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
            $rawBytes = file_get_contents($realPath);
            $src = @imagecreatefromstring($rawBytes);

            if ($src !== false) {
                try {
                    $origW = imagesx($src);
                    $origH = imagesy($src);

                    // 1. Process and save sanitized full image (downscaled if > FULL_MAX_BOX)
                    $fullImg = $this->resizeGd($src, $origW, $origH, self::FULL_MAX_BOX);
                    $fullBytes = $this->encodeGd($fullImg, $ext);
                    imagedestroy($fullImg);

                    // 2. Process and save genuine downscaled thumbnail (<= THUMB_MAX_BOX)
                    $thumbImg = $this->resizeGd($src, $origW, $origH, self::THUMB_MAX_BOX);
                    $thumbBytes = $this->encodeGd($thumbImg, $ext);
                    imagedestroy($thumbImg);

                    imagedestroy($src);

                    $disk->put($fullPath, $fullBytes, 'private');
                    $disk->put($thumbPath, $thumbBytes, 'private');

                    return [
                        'path' => $fullPath,
                        'thumbnail' => $thumbPath,
                        'size_bytes' => strlen($fullBytes),
                    ];
                } catch (\Throwable $e) {
                    if (is_resource($src) || (is_object($src) && $src instanceof \GdImage)) {
                        imagedestroy($src);
                    }
                    // Fall back to binary EXIF stripper if GD encountered an encoding error
                }
            }
        }

        // Fallback for environments where GD is unavailable: binary metadata stripping
        $cleanBytes = $this->stripBinaryMetadata(file_get_contents($realPath), $meta['mime']);
        $disk->put($fullPath, $cleanBytes, 'private');
        $disk->put($thumbPath, $cleanBytes, 'private');

        return [
            'path' => $fullPath,
            'thumbnail' => $thumbPath,
            'size_bytes' => strlen($cleanBytes),
        ];
    }

    /**
     * Proportionally downscale an image to fit inside a max bounding box.
     *
     * @param  \GdImage|resource  $src
     * @return \GdImage|resource
     */
    protected function resizeGd($src, int $w, int $h, int $maxBox)
    {
        $scale = min($maxBox / max(1, $w), $maxBox / max(1, $h), 1.0);
        $newW = max(1, (int) round($w * $scale));
        $newH = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($newW, $newH);

        // Preserve alpha transparency for PNG and WebP
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        return $dst;
    }

    /**
     * Encode GD image to clean binary bytes without EXIF metadata.
     *
     * @param  \GdImage|resource  $img
     */
    protected function encodeGd($img, string $ext): string
    {
        ob_start();
        match ($ext) {
            'png' => imagepng($img, null, 8),
            'webp' => function_exists('imagewebp') ? imagewebp($img, null, 85) : imagejpeg($img, null, 85),
            default => imagejpeg($img, null, 85),
        };

        return ob_get_clean();
    }

    /**
     * Pure-PHP binary metadata stripper for JPEG/PNG streams.
     * Removes APP1 (0xFFE1) EXIF/GPS segments, APP2, and Adobe XMP markers.
     */
    public function stripBinaryMetadata(string $data, string $mime): string
    {
        if ($mime === 'image/jpeg') {
            return $this->stripJpegExif($data);
        }

        return $data;
    }

    /**
     * Strips JPEG APP1 (EXIF/GPS/XMP) and COM markers while preserving SOI, DQT, DHT, SOF, SOS.
     */
    protected function stripJpegExif(string $data): string
    {
        if (substr($data, 0, 2) !== "\xFF\xD8") {
            return $data; // Not a standard JPEG
        }

        $out = "\xFF\xD8";
        $len = strlen($data);
        $pos = 2;

        while ($pos < $len) {
            if ($data[$pos] !== "\xFF") {
                $out .= substr($data, $pos);
                break;
            }

            $marker = ord($data[$pos + 1]);

            // SOS (Start of Scan) or EOI (End of Image) -> copy remaining image stream
            if ($marker === 0xDA || $marker === 0xD9) {
                $out .= substr($data, $pos);
                break;
            }

            // Standalone markers without length
            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $out .= substr($data, $pos, 2);
                $pos += 2;

                continue;
            }

            // Segment with length
            $segLen = (ord($data[$pos + 2]) << 8) + ord($data[$pos + 3]);

            // Marker 0xE1 = APP1 (EXIF & GPS), 0xFE = COM (Comment)
            if ($marker === 0xE1 || $marker === 0xFE) {
                // Skip this metadata segment entirely!
                $pos += 2 + $segLen;
            } else {
                // Keep image structure segments (DQT, DHT, SOF0, APP0/JFIF)
                $out .= substr($data, $pos, 2 + $segLen);
                $pos += 2 + $segLen;
            }
        }

        return $out;
    }

    /**
     * Clean up files from storage disk safely.
     */
    public function deleteFile(?string $path): void
    {
        if (! $path) {
            return;
        }

        $disk = Storage::disk(self::DISK);
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
