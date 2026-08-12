<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * ImageCropper — a FileUpload extended with cropping in the UI before the
 * upload.
 *
 * The SPA crops the picture on the client, in a canvas, and sends the cropped
 * result. aspectRatio sets W:H — 16/9 = 1.7777, and 1 is a square.
 */
final class ImageCropper extends FileUpload
{
    public function fieldType(): string
    {
        return 'image_cropper';
    }

    /**
     * The aspect ratio; null leaves it free.
     */
    public function aspectRatio(?float $ratio): static
    {
        $this->attributes['aspectRatio'] = $ratio;

        return $this;
    }

    /**
     * The smallest crop area, in pixels.
     */
    public function minCrop(int $width, int $height): static
    {
        $this->attributes['minCropWidth'] = $width;
        $this->attributes['minCropHeight'] = $height;

        return $this;
    }

    /**
     * The final size of the uploaded image — a resize after the crop.
     */
    public function outputSize(int $width, int $height): static
    {
        $this->attributes['outputWidth'] = $width;
        $this->attributes['outputHeight'] = $height;

        return $this;
    }

    /**
     * The JPG/WEBP quality, from 0 to 1.
     */
    public function quality(float $quality): static
    {
        $this->attributes['quality'] = $quality;

        return $this;
    }
}
