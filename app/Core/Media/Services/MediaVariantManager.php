<?php

namespace App\Core\Media\Services;

use App\Core\Media\Models\MediaFile;
use App\Core\Settings\Services\SettingsManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MediaVariantManager
{
    public function preferredUploadExtension(UploadedFile $file): string
    {
        $metadata = $this->inspectSource(
            sourcePath: $file->getRealPath() ?: $file->path(),
            providedMime: $file->getMimeType(),
            filename: $file->getClientOriginalName(),
            providedSize: $file->getSize(),
        );

        return $this->resolvePrimaryExtension($metadata);
    }

    public function processUploadedFile(UploadedFile $file, string $disk, string $directory, string $filename): array
    {
        $sourcePath = $file->getRealPath() ?: $file->path();
        $metadata = $this->inspectSource(
            sourcePath: $sourcePath,
            providedMime: $file->getMimeType(),
            filename: $file->getClientOriginalName(),
            providedSize: $file->getSize(),
        );
        $createdPaths = [];

        try {
            $primary = $this->storePrimaryAsset($file, $sourcePath, $disk, $directory, $filename, $metadata);
            $createdPaths[] = $primary['path'];

            $variants = $this->generateDerivativeVariants(
                sourcePath: $sourcePath,
                disk: $disk,
                directory: $directory,
                filename: $primary['filename'],
                metadata: $metadata,
                includeOptimizedVariant: $this->shouldGenerateOptimizedVariant($metadata),
            );

            foreach ($variants as $variant) {
                $path = Arr::get($variant, 'path');

                if (is_string($path) && $path !== '') {
                    $createdPaths[] = $path;
                }
            }

            return [
                'primary' => $primary,
                'variants' => $variants,
                'source' => $metadata,
            ];
        } catch (Throwable $exception) {
            foreach (array_unique($createdPaths) as $path) {
                Storage::disk($disk)->delete($path);
            }

            throw $exception;
        }
    }

    public function generateForUpload(UploadedFile $file, string $disk, string $directory, string $filename): array
    {
        return $this->processUploadedFile($file, $disk, $directory, $filename)['variants'];
    }

    public function generateForMediaFile(MediaFile $mediaFile, bool $purgeExisting = true): array
    {
        return $this->processMediaFile($mediaFile, $purgeExisting)['variants'];
    }

    public function processMediaFile(MediaFile $mediaFile, bool $purgeExisting = true): array
    {
        if ($purgeExisting) {
            $this->deleteVariants($mediaFile);
        }

        $sourcePath = Storage::disk($mediaFile->disk)->path($mediaFile->path);
        $metadata = $this->inspectSource(
            sourcePath: $sourcePath,
            providedMime: $mediaFile->mime_type,
            filename: $mediaFile->filename,
            providedSize: $mediaFile->size,
        );
        $variants = $this->generateDerivativeVariants(
            sourcePath: $sourcePath,
            disk: $mediaFile->disk,
            directory: $mediaFile->directory,
            filename: $mediaFile->filename,
            metadata: $metadata,
            includeOptimizedVariant: $this->shouldGenerateOptimizedVariant($metadata),
        );

        return [
            'primary' => [
                'path' => $mediaFile->path,
                'filename' => $mediaFile->filename,
                'extension' => strtolower((string) ($mediaFile->extension ?? pathinfo($mediaFile->filename, PATHINFO_EXTENSION))),
                'mime_type' => (string) ($mediaFile->mime_type ?: 'application/octet-stream'),
                'size' => (int) ($mediaFile->size ?? 0),
                'width' => $metadata['width'],
                'height' => $metadata['height'],
            ],
            'variants' => $variants,
            'source' => $metadata,
        ];
    }

    public function transformMediaFile(MediaFile $mediaFile, array $options): array
    {
        $sourcePath = Storage::disk($mediaFile->disk)->path($mediaFile->path);
        $metadata = $this->inspectSource(
            sourcePath: $sourcePath,
            providedMime: $mediaFile->mime_type,
            filename: $mediaFile->filename,
            providedSize: $mediaFile->size,
        );

        if (! $this->canGenerateRasterDerivatives($metadata)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => 'Этот файл нельзя кадрировать или конвертировать как растровое изображение.',
            ]);
        }

        $crop = $this->normalizeCropBox($options);
        $format = $this->resolveTransformFormat((string) ($options['format'] ?? 'original'), $metadata);
        $extension = $this->extensionForFormat($format);
        $baseName = pathinfo($mediaFile->filename, PATHINFO_FILENAME);
        $targetFilename = $baseName.'.'.$extension;
        $targetPath = $mediaFile->directory.'/'.$targetFilename;

        $primary = $this->generateProcessedAsset(
            sourcePath: $sourcePath,
            disk: $mediaFile->disk,
            directory: $mediaFile->directory,
            filename: $targetFilename,
            variantName: null,
            metadata: $metadata,
            width: isset($options['resize_width']) ? (int) $options['resize_width'] : null,
            height: isset($options['resize_height']) ? (int) $options['resize_height'] : null,
            mode: (($options['maintain_aspect_ratio'] ?? true) ? 'resize' : 'force'),
            format: $format,
            crop: $crop,
            quality: isset($options['quality']) ? (int) $options['quality'] : null,
        );

        if ($primary === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => 'Не удалось обработать изображение с указанными параметрами.',
            ]);
        }

        $transformedMetadata = $this->inspectSource(
            sourcePath: Storage::disk($mediaFile->disk)->path($primary['path']),
            providedMime: $primary['mime_type'],
            filename: $primary['filename'],
            providedSize: $primary['size'],
        );

        $this->deleteVariants($mediaFile);

        $variants = $this->generateDerivativeVariants(
            sourcePath: Storage::disk($mediaFile->disk)->path($primary['path']),
            disk: $mediaFile->disk,
            directory: $mediaFile->directory,
            filename: $primary['filename'],
            metadata: $transformedMetadata,
            includeOptimizedVariant: $this->shouldGenerateOptimizedVariant($transformedMetadata),
        );

        return [
            'primary' => $primary,
            'variants' => $variants,
            'source' => $transformedMetadata,
        ];
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

    protected function generateDerivativeVariants(
        string $sourcePath,
        string $disk,
        string $directory,
        string $filename,
        array $metadata,
        bool $includeOptimizedVariant,
    ): array
    {
        $variants = [];

        if ($includeOptimizedVariant) {
            $optimized = $this->generateProcessedAsset(
                sourcePath: $sourcePath,
                disk: $disk,
                directory: $directory,
                filename: $filename,
                variantName: 'optimized',
                metadata: $metadata,
                width: $this->maxWidth(),
                height: $this->maxHeight(),
                mode: 'resize',
                format: $this->resolveDerivativeFormat($metadata),
            );

            if ($optimized !== null) {
                $variants['optimized'] = $optimized;
            }
        }

        if (! $this->shouldCreateThumbnails()) {
            return $variants;
        }

        foreach ($this->configuredSizes() as $name => $definition) {
            $variant = $this->generateVariant(
                sourcePath: $sourcePath,
                disk: $disk,
                directory: $directory,
                filename: $filename,
                variantName: (string) $name,
                metadata: $metadata,
                width: Arr::get($definition, 'width'),
                height: Arr::get($definition, 'height'),
                mode: (string) Arr::get($definition, 'mode', 'resize'),
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
        array $metadata,
        mixed $width,
        mixed $height,
        string $mode,
    ): ?array {
        if (! $this->canGenerateRasterDerivatives($metadata)) {
            return null;
        }

        $targetWidth = is_numeric($width) ? (int) $width : null;
        $targetHeight = is_numeric($height) ? (int) $height : null;

        if (($targetWidth ?? 0) <= 0 && ($targetHeight ?? 0) <= 0) {
            return null;
        }

        try {
            return $this->generateProcessedAsset(
                sourcePath: $sourcePath,
                disk: $disk,
                directory: $directory,
                filename: $filename,
                variantName: $variantName,
                metadata: $metadata,
                width: $targetWidth,
                height: $targetHeight,
                mode: $mode,
                format: $this->resolveDerivativeFormat($metadata),
            );
        } catch (RuntimeException) {
            return null;
        }
    }

    protected function storePrimaryAsset(
        UploadedFile $file,
        string $sourcePath,
        string $disk,
        string $directory,
        string $filename,
        array $metadata,
    ): array {
        if (! $this->shouldOptimizeOriginal($metadata)) {
            $storedPath = $file->storeAs($directory, $filename, $disk);

            return [
                'path' => $storedPath,
                'filename' => basename($storedPath),
                'extension' => strtolower((string) pathinfo($storedPath, PATHINFO_EXTENSION)),
                'mime_type' => (string) ($metadata['mime_type'] ?: 'application/octet-stream'),
                'size' => (int) ($metadata['size'] ?? 0),
                'width' => $metadata['width'],
                'height' => $metadata['height'],
            ];
        }

        $path = $directory.'/'.$filename;
        $format = $this->normalizeOutputFormat(pathinfo($filename, PATHINFO_EXTENSION) ?: $this->resolvePrimaryExtension($metadata));

        $optimized = $this->generateProcessedAsset(
            sourcePath: $sourcePath,
            disk: $disk,
            directory: $directory,
            filename: basename($path),
            variantName: null,
            metadata: $metadata,
            width: $this->maxWidth(),
            height: $this->maxHeight(),
            mode: 'resize',
            format: $format,
        );

        if ($optimized === null) {
            $storedPath = $file->storeAs($directory, $filename, $disk);

            return [
                'path' => $storedPath,
                'filename' => basename($storedPath),
                'extension' => strtolower((string) pathinfo($storedPath, PATHINFO_EXTENSION)),
                'mime_type' => (string) ($metadata['mime_type'] ?: 'application/octet-stream'),
                'size' => (int) ($metadata['size'] ?? 0),
                'width' => $metadata['width'],
                'height' => $metadata['height'],
            ];
        }

        return $optimized;
    }

    protected function generateProcessedAsset(
        string $sourcePath,
        string $disk,
        string $directory,
        string $filename,
        ?string $variantName,
        array $metadata,
        ?int $width,
        ?int $height,
        string $mode,
        string $format,
        ?array $crop = null,
        ?int $quality = null,
    ): ?array {
        if (! $this->canGenerateRasterDerivatives($metadata)) {
            return null;
        }

        $outputPath = $variantName === null
            ? $directory.'/'.basename($filename)
            : $this->variantPath($directory, $filename, $variantName, $format);

        try {
            return $this->generateWithImagick(
                sourcePath: $sourcePath,
                disk: $disk,
                outputPath: $outputPath,
                metadata: $metadata,
                width: $width,
                height: $height,
                mode: $mode,
                format: $format,
                crop: $crop,
                quality: $quality,
            );
        } catch (RuntimeException) {
            return $this->generateWithGd(
                sourcePath: $sourcePath,
                disk: $disk,
                outputPath: $outputPath,
                metadata: $metadata,
                width: $width,
                height: $height,
                mode: $mode,
                format: $format,
                crop: $crop,
                quality: $quality,
            );
        }
    }

    protected function generateWithImagick(
        string $sourcePath,
        string $disk,
        string $outputPath,
        array $metadata,
        ?int $width,
        ?int $height,
        string $mode,
        string $format,
        ?array $crop,
        ?int $quality,
    ): array {
        if (! class_exists('Imagick')) {
            throw new RuntimeException('Imagick is not available.');
        }

        $imagick = new \Imagick();
        $imagick->readImage($sourcePath.'[0]');

        if (method_exists($imagick, 'autoOrient')) {
            $imagick->autoOrient();
        }

        if ($crop !== null) {
            $cropBox = $this->absoluteCropBox(
                imageWidth: $imagick->getImageWidth(),
                imageHeight: $imagick->getImageHeight(),
                crop: $crop,
            );

            $imagick->cropImage($cropBox['width'], $cropBox['height'], $cropBox['x'], $cropBox['y']);
            $imagick->setImagePage(0, 0, 0, 0);
        }

        $plan = $this->buildResizePlan(
            sourceWidth: $imagick->getImageWidth(),
            sourceHeight: $imagick->getImageHeight(),
            targetWidth: $width,
            targetHeight: $height,
            mode: $mode,
        );

        if ($plan === null) {
            return null;
        }

        $imagick->resizeImage($plan['resize_width'], $plan['resize_height'], \Imagick::FILTER_LANCZOS, 1, true);

        if ($plan['crop']) {
            $imagick->cropImage($plan['target_width'], $plan['target_height'], $plan['crop_x'], $plan['crop_y']);
        }

        $imagick->setImagePage(0, 0, 0, 0);
        $imagick->stripImage();
        $imagick->setImageFormat($format);

        if ($format === 'jpeg') {
            $imagick->setImageCompressionQuality($quality ?? $this->jpgQuality());
            $imagick->setInterlaceScheme(\Imagick::INTERLACE_PLANE);
        }

        if ($format === 'webp') {
            $imagick->setImageCompressionQuality($quality ?? $this->webpQuality());
        }

        Storage::disk($disk)->put($outputPath, $imagick->getImageBlob());

        return [
            'path' => $outputPath,
            'filename' => basename($outputPath),
            'extension' => strtolower((string) pathinfo($outputPath, PATHINFO_EXTENSION)),
            'mime_type' => $this->mimeTypeForFormat($format, (string) ($metadata['mime_type'] ?? 'application/octet-stream')),
            'width' => $imagick->getImageWidth(),
            'height' => $imagick->getImageHeight(),
            'size' => Storage::disk($disk)->size($outputPath),
        ];
    }

    protected function generateWithGd(
        string $sourcePath,
        string $disk,
        string $outputPath,
        array $metadata,
        ?int $width,
        ?int $height,
        string $mode,
        string $format,
        ?array $crop,
        ?int $quality,
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

        if ($crop !== null) {
            $cropBox = $this->absoluteCropBox(
                imageWidth: imagesx($sourceImage),
                imageHeight: imagesy($sourceImage),
                crop: $crop,
            );
            $croppedImage = imagecrop($sourceImage, [
                'x' => $cropBox['x'],
                'y' => $cropBox['y'],
                'width' => $cropBox['width'],
                'height' => $cropBox['height'],
            ]);

            if ($croppedImage instanceof \GdImage) {
                imagedestroy($sourceImage);
                $sourceImage = $croppedImage;
            }
        }

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        $plan = $this->buildResizePlan($sourceWidth, $sourceHeight, $width, $height, $mode);

        if ($plan === null) {
            imagedestroy($sourceImage);

            return null;
        }

        $targetImage = imagecreatetruecolor($plan['target_width'], $plan['target_height']);

        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);

        $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
        imagefilledrectangle($targetImage, 0, 0, $plan['target_width'], $plan['target_height'], $transparent);
        imagecopyresampled(
            $targetImage,
            $sourceImage,
            0,
            0,
            $plan['source_x'],
            $plan['source_y'],
            $plan['target_width'],
            $plan['target_height'],
            $plan['source_width'],
            $plan['source_height'],
        );

        ob_start();

        $writeSucceeded = $this->writeGdImage($targetImage, $format, $quality);

        $content = (string) ob_get_clean();

        imagedestroy($targetImage);
        imagedestroy($sourceImage);

        if (! $writeSucceeded || $content === '') {
            return null;
        }

        Storage::disk($disk)->put($outputPath, $content);

        return [
            'path' => $outputPath,
            'filename' => basename($outputPath),
            'extension' => strtolower((string) pathinfo($outputPath, PATHINFO_EXTENSION)),
            'mime_type' => $this->mimeTypeForFormat($format, (string) ($metadata['mime_type'] ?? 'application/octet-stream')),
            'width' => $plan['target_width'],
            'height' => $plan['target_height'],
            'size' => Storage::disk($disk)->size($outputPath),
        ];
    }

    protected function writeGdImage(\GdImage $image, string $format, ?int $quality = null): bool
    {
        return match ($format) {
            'png' => imagepng($image, null, $this->pngCompressionLevel()),
            'gif' => imagegif($image),
            'webp' => function_exists('imagewebp')
                ? imagewebp($image, null, $quality ?? $this->webpQuality())
                : imagejpeg($image, null, $quality ?? $this->jpgQuality()),
            default => imagejpeg($image, null, $quality ?? $this->jpgQuality()),
        };
    }

    protected function buildResizePlan(
        int $sourceWidth,
        int $sourceHeight,
        ?int $targetWidth,
        ?int $targetHeight,
        string $mode,
    ): ?array {
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            return null;
        }

        $targetWidth = $targetWidth !== null && $targetWidth > 0 ? $targetWidth : null;
        $targetHeight = $targetHeight !== null && $targetHeight > 0 ? $targetHeight : null;

        if ($targetWidth === null && $targetHeight === null) {
            return [
                'target_width' => $sourceWidth,
                'target_height' => $sourceHeight,
                'resize_width' => $sourceWidth,
                'resize_height' => $sourceHeight,
                'crop' => false,
                'crop_x' => 0,
                'crop_y' => 0,
                'source_x' => 0,
                'source_y' => 0,
                'source_width' => $sourceWidth,
                'source_height' => $sourceHeight,
            ];
        }

        if ($mode === 'crop' && $targetWidth !== null && $targetHeight !== null) {
            $cropScale = max($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
            $cropScale = min($cropScale, 1);
            $sampleWidth = max(1, (int) floor($targetWidth / $cropScale));
            $sampleHeight = max(1, (int) floor($targetHeight / $cropScale));
            $sampleWidth = min($sampleWidth, $sourceWidth);
            $sampleHeight = min($sampleHeight, $sourceHeight);

            return [
                'target_width' => min($targetWidth, $sourceWidth),
                'target_height' => min($targetHeight, $sourceHeight),
                'resize_width' => min($targetWidth, $sourceWidth),
                'resize_height' => min($targetHeight, $sourceHeight),
                'crop' => false,
                'crop_x' => 0,
                'crop_y' => 0,
                'source_x' => max(0, (int) floor(($sourceWidth - $sampleWidth) / 2)),
                'source_y' => max(0, (int) floor(($sourceHeight - $sampleHeight) / 2)),
                'source_width' => $sampleWidth,
                'source_height' => $sampleHeight,
            ];
        }

        if ($mode === 'force' && $targetWidth !== null && $targetHeight !== null) {
            return [
                'target_width' => $targetWidth,
                'target_height' => $targetHeight,
                'resize_width' => $targetWidth,
                'resize_height' => $targetHeight,
                'crop' => false,
                'crop_x' => 0,
                'crop_y' => 0,
                'source_x' => 0,
                'source_y' => 0,
                'source_width' => $sourceWidth,
                'source_height' => $sourceHeight,
            ];
        }

        $widthRatio = $targetWidth !== null ? $targetWidth / $sourceWidth : PHP_FLOAT_MAX;
        $heightRatio = $targetHeight !== null ? $targetHeight / $sourceHeight : PHP_FLOAT_MAX;
        $ratio = min($widthRatio, $heightRatio, 1);
        $finalWidth = max(1, (int) round($sourceWidth * $ratio));
        $finalHeight = max(1, (int) round($sourceHeight * $ratio));

        if ($targetWidth !== null && $targetHeight === null) {
            $finalWidth = min($targetWidth, $sourceWidth);
            $finalHeight = max(1, (int) round($sourceHeight * ($finalWidth / $sourceWidth)));
        }

        if ($targetHeight !== null && $targetWidth === null) {
            $finalHeight = min($targetHeight, $sourceHeight);
            $finalWidth = max(1, (int) round($sourceWidth * ($finalHeight / $sourceHeight)));
        }

        return [
            'target_width' => $finalWidth,
            'target_height' => $finalHeight,
            'resize_width' => $finalWidth,
            'resize_height' => $finalHeight,
            'crop' => false,
            'crop_x' => 0,
            'crop_y' => 0,
            'source_x' => 0,
            'source_y' => 0,
            'source_width' => $sourceWidth,
            'source_height' => $sourceHeight,
        ];
    }

    protected function inspectSource(
        string $sourcePath,
        ?string $providedMime = null,
        ?string $filename = null,
        ?int $providedSize = null,
    ): array {
        $extension = strtolower((string) pathinfo((string) $filename, PATHINFO_EXTENSION));
        $mimeType = $this->detectMimeType($sourcePath, $providedMime, $extension);
        $size = $providedSize ?: (is_file($sourcePath) ? (filesize($sourcePath) ?: 0) : 0);
        $dimensions = $mimeType === 'image/svg+xml'
            ? $this->svgDimensions($sourcePath)
            : $this->imageDimensions($sourcePath);
        $isGif = in_array($mimeType, ['image/gif'], true) || $extension === 'gif';
        $isSvg = $mimeType === 'image/svg+xml' || $extension === 'svg';
        $isImage = $isSvg || str_starts_with((string) $mimeType, 'image/');
        $isAnimatedGif = $isGif && $this->isAnimatedGif($sourcePath);

        return [
            'mime_type' => $mimeType,
            'size' => $size > 0 ? (int) $size : 0,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'extension' => $extension,
            'is_image' => $isImage,
            'is_svg' => $isSvg,
            'is_gif' => $isGif,
            'is_animated_gif' => $isAnimatedGif,
        ];
    }

    protected function detectMimeType(string $sourcePath, ?string $providedMime, string $extension): string
    {
        if (is_string($providedMime) && trim($providedMime) !== '') {
            return strtolower(trim($providedMime));
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $detected = finfo_file($finfo, $sourcePath) ?: null;
                finfo_close($finfo);

                if (is_string($detected) && $detected !== '') {
                    return strtolower($detected);
                }
            }
        }

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            default => 'application/octet-stream',
        };
    }

    protected function imageDimensions(string $sourcePath): array
    {
        [$width, $height] = array_pad((array) @getimagesize($sourcePath), 2, null);

        return [
            'width' => is_int($width) ? $width : null,
            'height' => is_int($height) ? $height : null,
        ];
    }

    protected function svgDimensions(string $sourcePath): array
    {
        $content = @file_get_contents($sourcePath);

        if ($content === false) {
            return ['width' => null, 'height' => null];
        }

        $width = $this->extractSvgLength($content, 'width');
        $height = $this->extractSvgLength($content, 'height');

        if ($width !== null && $height !== null) {
            return ['width' => $width, 'height' => $height];
        }

        if (preg_match('/viewBox\s*=\s*["\']\s*[-\d.]+\s+[-\d.]+\s+([\d.]+)\s+([\d.]+)\s*["\']/i', $content, $matches) === 1) {
            return [
                'width' => (int) round((float) $matches[1]),
                'height' => (int) round((float) $matches[2]),
            ];
        }

        return ['width' => null, 'height' => null];
    }

    protected function extractSvgLength(string $content, string $attribute): ?int
    {
        if (preg_match('/'.$attribute.'\s*=\s*["\']\s*([\d.]+)(px)?\s*["\']/i', $content, $matches) !== 1) {
            return null;
        }

        return (int) round((float) $matches[1]);
    }

    protected function isAnimatedGif(string $sourcePath): bool
    {
        $content = @file_get_contents($sourcePath);

        if ($content === false) {
            return false;
        }

        return preg_match_all('/\x00\x21\xF9\x04.{4}\x00\x2C/s', $content) > 1;
    }

    protected function shouldOptimizeOriginal(array $metadata): bool
    {
        return $this->shouldOptimize()
            && ! $this->shouldKeepOriginal()
            && $this->canGenerateRasterDerivatives($metadata);
    }

    protected function shouldGenerateOptimizedVariant(array $metadata): bool
    {
        return $this->shouldOptimize()
            && $this->shouldKeepOriginal()
            && $this->canGenerateRasterDerivatives($metadata);
    }

    protected function canGenerateRasterDerivatives(array $metadata): bool
    {
        if (! ($metadata['is_image'] ?? false) || ($metadata['is_svg'] ?? false) || ($metadata['is_animated_gif'] ?? false)) {
            return false;
        }

        if (($metadata['width'] ?? null) === null || ($metadata['height'] ?? null) === null) {
            return false;
        }

        return class_exists('Imagick') || extension_loaded('gd');
    }

    protected function configuredSizes(): array
    {
        $sizes = config('media.images.sizes', []);

        return is_array($sizes) ? $sizes : [];
    }

    protected function shouldCreateThumbnails(): bool
    {
        return $this->settings()['media_image_create_thumbnails'] ?? (bool) config('media.images.create_thumbnails', true);
    }

    protected function shouldOptimize(): bool
    {
        return $this->settings()['media_image_optimize'] ?? (bool) config('media.images.optimize', true);
    }

    protected function shouldKeepOriginal(): bool
    {
        return $this->settings()['media_image_keep_original'] ?? (bool) config('media.images.keep_original', true);
    }

    protected function resolvePrimaryExtension(array $metadata): string
    {
        if ($this->shouldOptimizeOriginal($metadata) && $this->shouldConvertToWebp() && $this->supportsWebpOutput()) {
            return 'webp';
        }

        return $this->normalizedSourceExtension($metadata);
    }

    protected function resolveDerivativeFormat(array $metadata): string
    {
        if ($this->shouldConvertToWebp() && $this->supportsWebpOutput()) {
            return 'webp';
        }

        return $this->normalizeOutputFormat($this->normalizedSourceExtension($metadata));
    }

    protected function normalizedSourceExtension(array $metadata): string
    {
        return match (strtolower((string) ($metadata['extension'] ?? ''))) {
            'jpeg' => 'jpg',
            'svg' => 'svg',
            'jpg', 'png', 'gif', 'webp', 'bmp' => strtolower((string) $metadata['extension']),
            default => match (strtolower((string) ($metadata['mime_type'] ?? ''))) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg',
                'image/bmp', 'image/x-ms-bmp' => 'bmp',
                default => 'bin',
            },
        };
    }

    protected function normalizeOutputFormat(string $extension): string
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => 'jpeg',
            'png' => 'png',
            'gif' => 'gif',
            'webp' => 'webp',
            default => 'jpeg',
        };
    }

    protected function shouldConvertToWebp(): bool
    {
        return $this->settings()['media_image_convert_to_webp'] ?? (bool) config('media.images.convert_to_webp', true);
    }

    protected function supportsWebpOutput(): bool
    {
        if (class_exists('Imagick')) {
            $formats = array_map('strtoupper', \Imagick::queryFormats());

            if (in_array('WEBP', $formats, true)) {
                return true;
            }
        }

        return function_exists('imagewebp');
    }

    protected function pngCompressionLevel(): int
    {
        $quality = max(0, min(100, $this->jpgQuality()));

        return (int) round((100 - $quality) * 9 / 100);
    }

    protected function maxWidth(): int
    {
        return (int) ($this->settings()['media_image_max_width'] ?? config('media.images.max_width', 1920));
    }

    protected function maxHeight(): int
    {
        return (int) ($this->settings()['media_image_max_height'] ?? config('media.images.max_height', 1920));
    }

    protected function jpgQuality(): int
    {
        return (int) ($this->settings()['media_image_jpg_quality'] ?? config('media.images.jpg_quality', 82));
    }

    protected function webpQuality(): int
    {
        return (int) ($this->settings()['media_image_webp_quality'] ?? config('media.images.webp_quality', 80));
    }

    protected function settings(): array
    {
        return app(SettingsManager::class)->all();
    }

    protected function mimeTypeForFormat(string $format, string $fallback): string
    {
        return match ($format) {
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => $fallback,
        };
    }

    protected function variantPath(string $directory, string $filename, string $variantName, string $format): string
    {
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $extension = $format === 'jpeg' ? 'jpg' : strtolower($format);

        return $directory.'/'.$variantName.'-'.$basename.'.'.$extension;
    }

    protected function normalizeCropBox(array $options): ?array
    {
        if (! ($options['crop_enabled'] ?? false)) {
            return null;
        }

        $x = isset($options['crop_x']) ? (float) $options['crop_x'] : 0.0;
        $y = isset($options['crop_y']) ? (float) $options['crop_y'] : 0.0;
        $width = isset($options['crop_width']) ? (float) $options['crop_width'] : 1.0;
        $height = isset($options['crop_height']) ? (float) $options['crop_height'] : 1.0;

        return [
            'x' => max(0.0, min(1.0, $x)),
            'y' => max(0.0, min(1.0, $y)),
            'width' => max(0.01, min(1.0, $width)),
            'height' => max(0.01, min(1.0, $height)),
        ];
    }

    protected function absoluteCropBox(int $imageWidth, int $imageHeight, array $crop): array
    {
        $x = (int) floor($imageWidth * ($crop['x'] ?? 0));
        $y = (int) floor($imageHeight * ($crop['y'] ?? 0));
        $width = max(1, (int) round($imageWidth * ($crop['width'] ?? 1)));
        $height = max(1, (int) round($imageHeight * ($crop['height'] ?? 1)));

        $width = min($width, $imageWidth - $x);
        $height = min($height, $imageHeight - $y);

        return [
            'x' => max(0, min($imageWidth - 1, $x)),
            'y' => max(0, min($imageHeight - 1, $y)),
            'width' => max(1, $width),
            'height' => max(1, $height),
        ];
    }

    protected function resolveTransformFormat(string $requestedFormat, array $metadata): string
    {
        return match ($requestedFormat) {
            'jpg' => 'jpeg',
            'png' => 'png',
            'webp' => 'webp',
            default => $this->normalizeOutputFormat($this->normalizedSourceExtension($metadata)),
        };
    }

    protected function extensionForFormat(string $format): string
    {
        return $format === 'jpeg' ? 'jpg' : strtolower($format);
    }
}