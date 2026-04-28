<?php

namespace App\Core\Media\Services;

use App\Core\Media\Models\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaVariantManager
{
    public function generateForUpload(UploadedFile $file, string $disk, string $directory, string $filename): array
    {
        return $this->generateForSource(
            sourcePath: $file->getRealPath() ?: $file->path(),
            disk: $disk,
            directory: $directory,
            filename: $filename,
        );
    }

    public function generateForMediaFile(MediaFile $mediaFile, bool $purgeExisting = true): array
    {
        if ($purgeExisting) {
            $this->deleteVariants($mediaFile);
        }

        return $this->generateForSource(
            sourcePath: Storage::disk($mediaFile->disk)->path($mediaFile->path),
            disk: $mediaFile->disk,
            directory: $mediaFile->directory,
            filename: $mediaFile->filename,
        );
    }

    public function moveVariants(MediaFile $mediaFile, string $targetDirectory): array
    {
        $variants = $mediaFile->variants ?? [];

        foreach ($variants as $name => $variant) {
            $currentPath = Arr::get($variant, 'path');

            if (! is_string($currentPath) || $currentPath === '') {
                continue;
            }

            $targetPath = $targetDirectory.'/'.basename($currentPath);

            Storage::disk($mediaFile->disk)->makeDirectory($targetDirectory);

            if (Storage::disk($mediaFile->disk)->exists($currentPath)) {
                Storage::disk($mediaFile->disk)->move($currentPath, $targetPath);
            }

            $variants[$name]['path'] = $targetPath;
        }

        return $variants;
    }

    public function deleteVariants(MediaFile $mediaFile): void
    {
        foreach ($mediaFile->variants ?? [] as $variant) {
            $path = Arr::get($variant, 'path');

            if (is_string($path) && $path !== '') {
                Storage::disk($mediaFile->disk)->delete($path);
            }
        }
    }

    public function rebaseVariantPaths(array $variants, string $currentDirectory, string $targetDirectory): array
    {
        foreach ($variants as $name => $variant) {
            $currentPath = Arr::get($variant, 'path');

            if (! is_string($currentPath) || $currentPath === '') {
                continue;
            }

            if (str_starts_with($currentPath, $currentDirectory.'/')) {
                $variants[$name]['path'] = $targetDirectory.substr($currentPath, strlen($currentDirectory));
            } else {
                $variants[$name]['path'] = $targetDirectory.'/'.basename($currentPath);
            }
        }

        return $variants;
    }

    protected function generateForSource(string $sourcePath, string $disk, string $directory, string $filename): array
    {
        $variants = [];

        foreach (config('media.variants', []) as $name => $definition) {
            $variant = $this->generateVariant(
                sourcePath: $sourcePath,
                disk: $disk,
                directory: $directory,
                filename: $filename,
                variantName: (string) $name,
                maxWidth: (int) Arr::get($definition, 'width', 0),
                maxHeight: (int) Arr::get($definition, 'height', 0),
            );

            if ($variant !== null) {
                $variants[$name] = $variant;
            }
        }

        return $variants;
    }

    protected function generateVariant(
        string $sourcePath,
        string $disk,
        string $directory,
        string $filename,
        string $variantName,
        int $maxWidth,
        int $maxHeight,
    ): ?array {
        if ($maxWidth <= 0 || $maxHeight <= 0) {
            return null;
        }

        try {
            return $this->generateWithImagick($sourcePath, $disk, $directory, $filename, $variantName, $maxWidth, $maxHeight);
        } catch (RuntimeException) {
            return $this->generateWithGd($sourcePath, $disk, $directory, $filename, $variantName, $maxWidth, $maxHeight);
        }
    }

    protected function generateWithImagick(
        string $sourcePath,
        string $disk,
        string $directory,
        string $filename,
        string $variantName,
        int $maxWidth,
        int $maxHeight,
    ): array {
        if (! class_exists('Imagick')) {
            throw new RuntimeException('Imagick is not available.');
        }

        $imagick = new \Imagick();
        $imagick->readImage($sourcePath.'[0]');

        if (method_exists($imagick, 'autoOrient')) {
            $imagick->autoOrient();
        }

        $imagick->thumbnailImage($maxWidth, $maxHeight, true, true);
        $imagick->setImagePage(0, 0, 0, 0);
        $imagick->stripImage();

        $format = $this->preferredVariantFormat();
        $imagick->setImageFormat($format);

        if (in_array($format, ['jpeg', 'webp'], true)) {
            $imagick->setImageCompressionQuality(82);
        }

        $variantPath = $this->variantPath($directory, $filename, $variantName, $format);

        Storage::disk($disk)->put($variantPath, $imagick->getImageBlob());

        return [
            'path' => $variantPath,
            'width' => $imagick->getImageWidth(),
            'height' => $imagick->getImageHeight(),
            'size' => Storage::disk($disk)->size($variantPath),
        ];
    }

    protected function generateWithGd(
        string $sourcePath,
        string $disk,
        string $directory,
        string $filename,
        string $variantName,
        int $maxWidth,
        int $maxHeight,
    ): ?array {
        if (! extension_loaded('gd')) {
            return null;
        }

        $binary = file_get_contents($sourcePath);

        if ($binary === false) {
            return null;
        }

        $sourceImage = @imagecreatefromstring($binary);

        if ($sourceImage === false) {
            return null;
        }

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1);
        $targetWidth = max(1, (int) round($sourceWidth * $ratio));
        $targetHeight = max(1, (int) round($sourceHeight * $ratio));
        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);

        $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
        imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
        imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        $format = $this->preferredVariantFormat();
        $variantPath = $this->variantPath($directory, $filename, $variantName, $format);

        ob_start();

        if ($format === 'webp' && function_exists('imagewebp')) {
            imagewebp($targetImage, null, 82);
        } else {
            imagejpeg($targetImage, null, 82);
            $format = 'jpeg';
            $variantPath = $this->variantPath($directory, $filename, $variantName, $format);
        }

        $content = (string) ob_get_clean();

        imagedestroy($targetImage);
        imagedestroy($sourceImage);

        Storage::disk($disk)->put($variantPath, $content);

        return [
            'path' => $variantPath,
            'width' => $targetWidth,
            'height' => $targetHeight,
            'size' => Storage::disk($disk)->size($variantPath),
        ];
    }

    protected function preferredVariantFormat(): string
    {
        if (class_exists('Imagick')) {
            $formats = array_map('strtoupper', \Imagick::queryFormats());

            if (in_array('WEBP', $formats, true)) {
                return 'webp';
            }
        }

        if (function_exists('imagewebp')) {
            return 'webp';
        }

        return 'jpeg';
    }

    protected function variantPath(string $directory, string $filename, string $variantName, string $format): string
    {
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $extension = $format === 'jpeg' ? 'jpg' : strtolower($format);

        return $directory.'/'.$variantName.'-'.$basename.'.'.$extension;
    }
}