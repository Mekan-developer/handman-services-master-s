<?php

namespace Tests\Feature;

use App\Support\PhotoConverter;
use Tests\TestCase;

class PhotoConverterTest extends TestCase
{
    private function createJpeg(int $width, int $height, string $path): void
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 100, 150, 200);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $color);
        imagejpeg($image, $path, 90);
        imagedestroy($image);
    }

    public function test_converts_jpeg_to_webp(): void
    {
        $dir = sys_get_temp_dir().'/photo_converter_test_'.uniqid();
        mkdir($dir);
        $jpegPath = $dir.'/test.jpg';

        $this->createJpeg(800, 600, $jpegPath);

        $webpPath = PhotoConverter::convert($jpegPath);

        $this->assertFileExists($webpPath);
        $this->assertStringEndsWith('.webp', $webpPath);

        [$w, $h] = getimagesize($webpPath);
        $this->assertEquals(800, $w);
        $this->assertEquals(600, $h);

        unlink($jpegPath);
        unlink($webpPath);
        rmdir($dir);
    }

    public function test_scales_down_when_height_exceeds_1800px(): void
    {
        $dir = sys_get_temp_dir().'/photo_converter_test_'.uniqid();
        mkdir($dir);
        $jpegPath = $dir.'/tall.jpg';

        $this->createJpeg(1200, 2400, $jpegPath);

        $webpPath = PhotoConverter::convert($jpegPath);

        $this->assertFileExists($webpPath);

        [$w, $h] = getimagesize($webpPath);
        $this->assertEquals(1800, $h);
        $this->assertEquals(900, $w);

        unlink($jpegPath);
        unlink($webpPath);
        rmdir($dir);
    }

    public function test_does_not_scale_when_height_equals_1800px(): void
    {
        $dir = sys_get_temp_dir().'/photo_converter_test_'.uniqid();
        mkdir($dir);
        $jpegPath = $dir.'/exact.jpg';

        $this->createJpeg(1200, 1800, $jpegPath);

        $webpPath = PhotoConverter::convert($jpegPath);

        [$w, $h] = getimagesize($webpPath);
        $this->assertEquals(1800, $h);
        $this->assertEquals(1200, $w);

        unlink($jpegPath);
        unlink($webpPath);
        rmdir($dir);
    }

    /** Noisy image — compresses poorly, so it actually exercises the size budget. */
    private function createNoisyJpeg(int $width, int $height, string $path): void
    {
        $image = imagecreatetruecolor($width, $height);

        for ($x = 0; $x < $width; $x += 2) {
            for ($y = 0; $y < $height; $y += 2) {
                $color = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
                imagefilledrectangle($image, $x, $y, $x + 1, $y + 1, $color);
            }
        }

        imagejpeg($image, $path, 100);
        imagedestroy($image);
    }

    private function createTransparentPng(int $size, string $path): void
    {
        $image = imagecreatetruecolor($size, $size);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefilledrectangle($image, 0, 0, $size - 1, $size - 1, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, 0, 0, (int) ($size / 2), (int) ($size / 2), imagecolorallocate($image, 200, 30, 30));
        imagepng($image, $path);
        imagedestroy($image);
    }

    public function test_squeezes_image_under_the_byte_budget(): void
    {
        $dir = sys_get_temp_dir().'/photo_converter_test_'.uniqid();
        mkdir($dir);
        $jpegPath = $dir.'/noisy.jpg';

        $this->createNoisyJpeg(1600, 1200, $jpegPath);

        $webpPath = PhotoConverter::convertToMaxBytes($jpegPath, 50 * 1024, 512);

        $this->assertFileExists($webpPath);
        $this->assertLessThanOrEqual(50 * 1024, filesize($webpPath));

        [$w, , $type] = getimagesize($webpPath);
        $this->assertSame(IMAGETYPE_WEBP, $type);
        $this->assertLessThanOrEqual(512, $w);

        unlink($jpegPath);
        unlink($webpPath);
        rmdir($dir);
    }

    public function test_does_not_upscale_images_narrower_than_the_max_width(): void
    {
        $dir = sys_get_temp_dir().'/photo_converter_test_'.uniqid();
        mkdir($dir);
        $jpegPath = $dir.'/small.jpg';

        $this->createJpeg(120, 90, $jpegPath);

        $webpPath = PhotoConverter::convertToMaxBytes($jpegPath, 50 * 1024, 512);

        [$w, $h] = getimagesize($webpPath);
        $this->assertEquals(120, $w);
        $this->assertEquals(90, $h);

        unlink($jpegPath);
        unlink($webpPath);
        rmdir($dir);
    }

    public function test_keeps_transparency_when_converting_to_budget(): void
    {
        $dir = sys_get_temp_dir().'/photo_converter_test_'.uniqid();
        mkdir($dir);
        $pngPath = $dir.'/transparent.png';

        $this->createTransparentPng(800, $pngPath);

        $webpPath = PhotoConverter::convertToMaxBytes($pngPath, 50 * 1024, 512);

        $converted = imagecreatefromwebp($webpPath);
        $sample = (int) (imagesx($converted) * 0.875);
        $alpha = (imagecolorat($converted, $sample, $sample) >> 24) & 0x7F;
        imagedestroy($converted);

        // Bottom-right quadrant was fully transparent in the source.
        $this->assertGreaterThan(100, $alpha);

        unlink($pngPath);
        unlink($webpPath);
        rmdir($dir);
    }

    public function test_throws_on_unsupported_mime_type(): void
    {
        $dir = sys_get_temp_dir().'/photo_converter_test_'.uniqid();
        mkdir($dir);
        $fakePath = $dir.'/file.bmp';
        file_put_contents($fakePath, 'BM fake bitmap content');

        $this->expectException(\RuntimeException::class);

        try {
            PhotoConverter::convert($fakePath);
        } finally {
            @unlink($fakePath);
            rmdir($dir);
        }
    }
}
