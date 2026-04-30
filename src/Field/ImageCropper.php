<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * ImageCropper — расширение FileUpload с обрезкой на UI до загрузки.
 *
 * SPA крипает картинку на клиенте (canvas) и шлёт уже обрезанную.
 * aspectRatio задаёт W:H (например, 16/9 = 1.7777, 1 = квадрат).
 */
final class ImageCropper extends FileUpload
{
    public function fieldType(): string
    {
        return 'image_cropper';
    }

    /**
     * Соотношение сторон. null = свободное.
     */
    public function aspectRatio(?float $ratio): static
    {
        $this->attributes['aspectRatio'] = $ratio;

        return $this;
    }

    /**
     * Минимальный crop-area в px.
     */
    public function minCrop(int $width, int $height): static
    {
        $this->attributes['minCropWidth'] = $width;
        $this->attributes['minCropHeight'] = $height;

        return $this;
    }

    /**
     * Финальный размер выгружаемой картинки (resize after crop).
     */
    public function outputSize(int $width, int $height): static
    {
        $this->attributes['outputWidth'] = $width;
        $this->attributes['outputHeight'] = $height;

        return $this;
    }

    /**
     * Качество JPG/WEBP (0..1).
     */
    public function quality(float $quality): static
    {
        $this->attributes['quality'] = $quality;

        return $this;
    }
}
