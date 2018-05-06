<?php

/**
 * Created by PhpStorm.
 * User: EDLENKO
 * Date: 21.01.2017
 * Time: 4:33
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

/**
 * Class Watermark
 */
class Watermark
{
    const PATH_PHOTOS_ORIGIN = 'images_origin/';
    const PATH_PHOTOS_WATERMARKED = 'images_watermarked/';
    const PATH_WATERMARKS = 'watermarks/';
    const PATH_TEMP = 'temp/';
    const FALLBACK_BRIGHTNESS_LEVEL = 55;
    const BRIGHTNESS_OFFSET = 10;

    /**
     * @var
     */
    private $_watermark;

    /**
     * @var
     */
    private $_defaultWatermark;
    /**
     * @var null
     */
    private $_fallbackWatermark;

    /**
     * @var bool
     */
    private $_isFallback = false;

    /**
     * @var
     */
    private $_images;
    /**
     * @var array
     */
    private $_mimeTypes = ['image/gif', 'image/jpeg', 'image/png'];

    /**
     * @param $defaultWatermark
     * @param null $fallbackWatermark
     */
    public function __construct($defaultWatermark, $fallbackWatermark = null)
    {
        $this->_defaultWatermark = $defaultWatermark;
        $this->_fallbackWatermark = $fallbackWatermark;
    }

    /**
     * @return int
     */
    public function run()
    {
        try {
            return $this->processImages();
        } catch (Exception $e) {
            echo $e->getMessage();
            die;
        }
    }

    /**
     * @return int
     * @throws Exception
     */
    protected function processImages()
    {
        $images = $this->getImages();
        if (!empty($images)) {
            foreach ($images as $img) {
                $imgMimeType = mime_content_type(self::PATH_PHOTOS_ORIGIN.$img);
                if (!in_array($imgMimeType, $this->getMimeTypes())) {
                    continue;
                }
                $this->processImage($img, $imgMimeType);
            }
        } else {
            throw new Exception('No images to watermark!');
        }

        return count($images);
    }

    /**
     * @param $img
     * @param $imgMimeType
     * @throws Exception
     */
    protected function processImage($img, $imgMimeType)
    {
        $position = $this->analizeImgCorners($img, $imgMimeType);

        $imgSrc = $this->createGdImg(self::PATH_PHOTOS_ORIGIN.$img, $imgMimeType);
        $this->addWatermark($imgSrc, $position);
        $this->saveWatermarkedImg($imgSrc, $imgMimeType, $img);
        $this->destroyGdImg($imgSrc);
    }

    /**
     * @return mixed
     */
    protected function getImages()
    {
        if (empty($this->_images)) {
            $files = scandir(self::PATH_PHOTOS_ORIGIN);
            foreach ($files as $file) {
                if ($file == '.' || $file == '..') {
                    continue;
                }

                $imgMimeType = mime_content_type(self::PATH_PHOTOS_ORIGIN.$file);
                if (substr_count($imgMimeType, 'image/') > 0) {
                    $this->_images[] = $file;
                }
            }
        }
        return $this->_images;
    }

    /**
     * @param $imgSrc
     * @param $watermarkPosition
     */
    protected function addWatermark(&$imgSrc, $watermarkPosition)
    {
        $watermark = $this->getWatermark();

        $watermarkPositionX = $watermarkPosition['x'];
        $watermarkPositionY = $watermarkPosition['y'];

        imagecopy(
            $imgSrc,
            $watermark,
            $watermarkPositionX,
            $watermarkPositionY,
            0,
            0,
            imagesx($watermark),
            imagesy($watermark)
        );
    }

    /**
     * @param $imgSrc
     * @param $imgMimeType
     * @param $img
     * @throws Exception
     */
    protected function saveWatermarkedImg(&$imgSrc, $imgMimeType, $img)
    {
        $name = self::PATH_PHOTOS_WATERMARKED.$img;
        switch ($imgMimeType) {
            case 'image/gif':
                imagegif($imgSrc, $name);
                break;

            case 'image/jpeg':
                imagejpeg($imgSrc, $name);
                break;

            case 'image/png':
                imagepng($imgSrc, $name);
                break;

            default:
                throw new Exception('Unsupported mime-type!');
                break;
        }
        $this->setIsFallback(false);
    }

    /**
     * @param $img
     * @param $imgMimeType
     * @return array
     * @throws Exception
     */
    protected function analizeImgCorners($img, $imgMimeType)
    {
        if (!$this->isFallback()) {
            $src = $this->createGdImg(self::PATH_PHOTOS_ORIGIN.$img, $imgMimeType);
            $dest = $this->getWatermark();

            $positionRightBottom = $this->getRightBottom($dest, $src);
            $name = $this->saveTempImg($dest, $imgMimeType);
            $tempSrc = $this->createGdImg($name, $imgMimeType);
            $brightnessRightBottom = self::getBrightness($tempSrc);
            $this->destroyGdImg($tempSrc)->deleteImg($name);

            $positionLeftBottom = $this->getLeftBottom($dest, $src);
            $name = $this->saveTempImg($dest, $imgMimeType);
            $tempSrc = $this->createGdImg($name, $imgMimeType);
            $brightnessLeftBottom = self::getBrightness($tempSrc);
            $this->destroyGdImg($tempSrc)->deleteImg($name);

            if ($brightnessRightBottom < self::FALLBACK_BRIGHTNESS_LEVEL && $brightnessLeftBottom < self::FALLBACK_BRIGHTNESS_LEVEL) {
                // Run fallback
                $this->setIsFallback(true);
                $position = $this->analizeImgCorners($img, $imgMimeType);
            } else {
                if ($brightnessLeftBottom > $brightnessRightBottom + self::BRIGHTNESS_OFFSET) {
                    $position = $positionLeftBottom;
                } else {
                    $position = $positionRightBottom;
                }
            }
        } else {
            $src = $this->createGdImg(self::PATH_PHOTOS_ORIGIN.$img, $imgMimeType);
            $dest = $this->getWatermark();

            $positionRightBottom = $this->getRightBottom($dest, $src);
            $name = $this->saveTempImg($dest, $imgMimeType);
            $tempSrc = $this->createGdImg($name, $imgMimeType);
            $brightnessRightBottom = self::getBrightness($tempSrc);
            $this->destroyGdImg($tempSrc)->deleteImg($name);

            $positionLeftBottom = $this->getLeftBottom($dest, $src);
            $name = $this->saveTempImg($dest, $imgMimeType);
            $tempSrc = $this->createGdImg($name, $imgMimeType);
            $brightnessLeftBottom = self::getBrightness($tempSrc);
            $this->destroyGdImg($tempSrc)->deleteImg($name);

            if ($brightnessLeftBottom < $brightnessRightBottom - self::BRIGHTNESS_OFFSET) {
                $position = $positionLeftBottom;
            } else {
                $position = $positionRightBottom;
            }
        }

        $this->destroyGdImg($src)->destroyGdImg($dest);
        return $position;
    }

    /**
     * @param $dest
     * @param $src
     * @return array
     */
    protected function getLeftTop(&$dest, &$src)
    {
        $margins = $this->getMargins();

        $position = [
            'x' => 0+$margins['x'],
            'y' => 0+$margins['y'],
        ];

        imagecopy(
            $dest,
            $src,
            0,
            0,
            $position['x'],
            $position['y'],
            imagesx($dest),
            imagesy($dest)
        );

        return $position;
    }

    /**
     * @param $dest
     * @param $src
     * @return array
     */
    protected function getRightTop(&$dest, &$src)
    {
        $margins = $this->getMargins();

        $position = [
            'x' => imagesx($src)-imagesx($dest)-$margins['x'],
            'y' => 0+$margins['y'],
        ];

        imagecopy(
            $dest,
            $src,
            0,
            0,
            $position['x'],
            $position['y'],
            imagesx($dest),
            imagesy($dest)
        );

        return $position;
    }

    /**
     * @param $dest
     * @param $src
     * @return array
     */
    protected function getLeftBottom(&$dest, &$src)
    {
        $margins = $this->getMargins();

        $position = [
            'x' => 0+$margins['x'],
            'y' => imagesy($src)-imagesy($dest)-$margins['y'],
        ];

        imagecopy(
            $dest,
            $src,
            0,
            0,
            $position['x'],
            $position['y'],
            imagesx($dest),
            imagesy($dest)
        );

        return $position;
    }

    /**
     * @param $dest
     * @param $src
     * @return array
     */
    protected function getRightBottom(&$dest, &$src)
    {
        $margins = $this->getMargins();

        $position = [
            'x' => imagesx($src)-imagesx($dest)-$margins['x'],
            'y' => imagesy($src)-imagesy($dest)-$margins['y'],
        ];

        imagecopy(
            $dest,
            $src,
            0,
            0,
            $position['x'],
            $position['y'],
            imagesx($dest),
            imagesy($dest)
        );

        return $position;
    }

    /**
     * @param $img
     * @param $imgMimeType
     * @return string
     * @throws Exception
     */
    protected function saveTempImg($img, $imgMimeType)
    {
        imagefilter($img, IMG_FILTER_GRAYSCALE);
        imagefilter($img, IMG_FILTER_CONTRAST, -255);

        $name = self::PATH_TEMP.md5(time().round(microtime(true) * 1000).rand(1, 1000)).".".substr($imgMimeType, strpos($imgMimeType, '/')+1);
        switch ($imgMimeType) {
            case 'image/gif':
                imagegif($img, $name);
                break;

            case 'image/jpeg':
                imagejpeg($img, $name);
                break;

            case 'image/png':
                imagepng($img, $name);
                break;

            default:
                throw new Exception('Unsupported mime-type!');
                break;
        }
        return $name;
    }

    /**
     * @param $img
     * @param $imgMimeType
     * @return resource
     * @throws Exception
     */
    protected function createGdImg($img, $imgMimeType)
    {
        switch ($imgMimeType) {
            case 'image/gif':
                $imgSrc = imagecreatefromgif($img);
                break;

            case 'image/jpeg':
                $imgSrc = imagecreatefromjpeg($img);
                break;

            case 'image/png':
                $imgSrc = imagecreatefrompng($img);
                break;

            default:
                throw new Exception('Unsupported mime-type!');
                break;
        }
        return $imgSrc;
    }

    /**
     * @param $imgSrc
     * @return $this
     */
    private function destroyGdImg(&$imgSrc)
    {
        if (is_resource($imgSrc)) {
            imagedestroy($imgSrc);
        }
        return $this;
    }

    /**
     * @param $name
     */
    private function deleteImg($name)
    {
        if (file_exists($name)) {
            unlink($name);
        }
    }

    /**
     * @return bool
     */
    public function isFallback()
    {
        return $this->_isFallback;
    }

    /**
     * @param $isFallback
     */
    protected function setIsFallback($isFallback)
    {
        $this->_isFallback = $isFallback;
    }

    /**
     * @return mixed
     */
    public function getDefaultWatermark()
    {
        return $this->_defaultWatermark;
    }

    /**
     * @param $watermark
     */
    protected function setDefaultWatermark($watermark)
    {
        $this->_defaultWatermark = $watermark;
    }

    /**
     * @return null
     */
    public function getFallbackWatermark()
    {
        return $this->_fallbackWatermark;
    }

    /**
     * @param $watermark
     */
    protected function setFallbackWatermark($watermark)
    {
        $this->_fallbackWatermark = $watermark;
    }

    /**
     * @param $gdHandle
     * @return int
     */
    public static function getBrightness($gdHandle) {
        $width = imagesx($gdHandle);
        $height = imagesy($gdHandle);

        $totalBrightness = 0;

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($gdHandle, $x, $y);

                $red = ($rgb >> 16) & 0xFF;
                $green = ($rgb >> 8) & 0xFF;
                $blue = $rgb & 0xFF;

                $totalBrightness += (max($red, $green, $blue) + min($red, $green, $blue)) / 2;
            }
        }

        return intval(round(($totalBrightness / ($width * $height)) / 2.55));
    }

    /**
     * @return array
     */
    public function getMimeTypes()
    {
        return $this->_mimeTypes;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getWatermark()
    {
        ($this->isFallback()) ? $this->setWatermark($this->getFallbackWatermark()) : $this->setWatermark($this->getDefaultWatermark());
        return $this->_watermark;
    }

    /**
     * @param $watermark
     * @throws Exception
     */
    protected function setWatermark($watermark)
    {
        if (file_exists(self::PATH_WATERMARKS.$watermark)) {
            if (mime_content_type(self::PATH_WATERMARKS.$watermark) == 'image/png') {
                $this->_watermark = imagecreatefrompng(self::PATH_WATERMARKS.$watermark);
            } else {
                throw new Exception('Only .PNG watermarks are supported!');
            }
        } else {
            throw new Exception('Watermark does not exist!');
        }
    }

    /**
     * @return array
     */
    protected function getMargins()
    {
        return [
            'x' => 20,
            'y' => 20
        ];
    }
}