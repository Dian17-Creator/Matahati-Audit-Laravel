<?php

namespace App\Services;

use Exception;
use finfo;

class ImageUploadService
{
    /**
     * Uploads, fixes orientation, resizes, and compresses an image.
     *
     * @param string $sourcePath The temporary uploaded file path
     * @param string $destinationPath The absolute path to save the image
     * @param string $mime The mime type of the image
     * @throws Exception
     */
    public function optimizeAndSave(string $sourcePath, string $destinationPath, string $mime): void
    {
        switch ($mime) {
            case "image/jpeg":
                $src = @imagecreatefromjpeg($sourcePath);
                break;
            case "image/png":
                $src = @imagecreatefrompng($sourcePath);
                break;
            case "image/webp":
                $src = @imagecreatefromwebp($sourcePath);
                break;
            default:
                throw new Exception("Jenis foto tidak dikenali.");
        }

        if (!$src) {
            throw new Exception("Gagal membaca foto.");
        }

        // Fix EXIF Orientation (JPEG only)
        if ($mime === "image/jpeg" && function_exists("exif_read_data")) {
            $exif = @exif_read_data($sourcePath);
            if (!empty($exif["Orientation"])) {
                switch ($exif["Orientation"]) {
                    case 3:
                        $src = imagerotate($src, 180, 0);
                        break;
                    case 6:
                        $src = imagerotate($src, -90, 0);
                        break;
                    case 8:
                        $src = imagerotate($src, 90, 0);
                        break;
                }
            }
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $maxDimension = 1600;

        if ($width <= $maxDimension && $height <= $maxDimension) {
            $newWidth = $width;
            $newHeight = $height;
        } elseif ($width >= $height) {
            $newWidth = $maxDimension;
            $newHeight = intval($height * ($newWidth / $width));
        } else {
            $newHeight = $maxDimension;
            $newWidth = intval($width * ($newHeight / $height));
        }

        $dst = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG and WebP
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            imagefilledrectangle($dst, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled(
            $dst,
            $src,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $width, $height
        );

        // Save as JPEG with 82 quality
        imagejpeg($dst, $destinationPath, 82);

        imagedestroy($src);
        imagedestroy($dst);
    }
}
