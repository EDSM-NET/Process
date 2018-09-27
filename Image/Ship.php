<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Image;

use         Process\Process;
use         Alias\Ship\PaintJob;

class Ship extends Process
{
    static private $tempFolder  = APPLICATION_PATH . '/Data/Temp/Ships/';
    static private $shipFolder  = PUBLIC_PATH . '/img/ships/';
    
    static private $limit       = 50;
    
    static protected $cropLeft  = [
        128049267 => 400,   // Adder
        128816588 => 400,   // Alliance Challenger
        128816574 => 400,   // Alliance Chieftain
        128816581 => 400,   // Alliance Crusader
        128049363 => 400,
        128049303 => 300, // Reverse?
        128672276 => 300, // Reverse?
        128049345 => 400,
        128049279 => 400,
        128672262 => 400,
        128049291 => 400,
        128671831 => 300,
        128671217 => 400,
        128049255 => 400,
        128672145 => 400,
        128049369 => 0,
        128049321 => 400,
        128672152 => 400,
        128049351 => 400,
        128049261 => 400,
        128049315 => 400,
        128671223 => 275,
        128049375 => 270,   // Imperial Cutter
        128672138 => 400,
        128672269 => 400,   // Keelback
        128816567 => 300,   // Krait MkII
        128049327 => 325,
        128049339 => 400,
        128049249 => 400,
        128049285 => 325,
        128049297 => 325,
        128049333 => 400,
        128785619 => 185,
        128049273 => 400,
        128672255 => 350,
        128049309 => 300,
    ];
    
    /**
     * Find a ship image into a folder and convert it to be used
     */
    static public function run()
    {
        $imagesToConvert = self::generateImagesToConvert();
        
        if(count($imagesToConvert) > 0)
        {
            foreach($imagesToConvert AS $imageInformations)
            {
                $image = self::cropImage($imageInformations);
                
                if(!is_null($image) && $image !== false)
                {
                    // Downsize
                    
                    // Save JPG
                    imagejpeg(
                        $image,
                        self::$shipFolder . $imageInformations['shipId'] . '/' . $imageInformations['file'] . '.jpg',
                        75
                    );
                    
                    static::log('<span class="text-info">Image\Ship:</span> Saving ' . $imageInformations['shipId'] . '/' . $imageInformations['file']);
                    
                    imagedestroy($image);
                    
                    // Remove temp image
                    if(is_file(self::$tempFolder . $imageInformations['file'] . '.png'))
                    {
                        unlink(self::$tempFolder . $imageInformations['file'] . '.png');
                    }
                    if(is_file(self::$tempFolder . $imageInformations['file'] . '.bmp'))
                    {
                        unlink(self::$tempFolder . $imageInformations['file'] . '.bmp');
                    }
                }
            }
        }
        
        return;
    }
    
    static private function generateImagesToConvert()
    {
        $tmp        = array();
        $dir        = scandir(self::$tempFolder);
        
        $paintjobs  = PaintJob::getAllFromFd();
        
        foreach($dir AS $file)
        {
            if(is_file(self::$tempFolder . $file) && ( strpos($file, '.png') !== false || strpos($file, '.bmp') !== false ))
            {
                $file   = str_replace('.png', '', $file);
                $file   = str_replace('.bmp', '', $file);
                
                foreach($paintjobs AS $shipId => $shipPaints)
                {
                    foreach($shipPaints as $paintKey => $name)
                    {
                        if($file == $paintKey)
                        {
                            $tmp[] = array(
                                'shipId'    => $shipId,
                                'name'      => $name,
                                'file'      => $paintKey,
                            );
                            
                            break 2;
                        }
                    }
                }
            }
            
            if(count($tmp) >= self::$limit)
            {
                unset($paintjobs);
                return $tmp;
            }
        }
        
        unset($paintjobs);
        return $tmp;
    }
    
    static private function cropImage($options)
    {
        if(is_file(self::$tempFolder . $options['file'] . '.png'))
        {
            $image = imagecreatefrompng(self::$tempFolder . $options['file'] . '.png');
        }
        elseif(is_file(self::$tempFolder . $options['file'] . '.bmp'))
        {
            $image = imagecreatefrombmp(self::$tempFolder . $options['file'] . '.bmp');
        }
        else
        {
            return null;
        }
        
        if($image !== false && array_key_exists($options['shipId'], self::$cropLeft))
        {
            $croppedImage   = imagecreatetruecolor(760, 540);
            $cropResult     = imagecopyresampled(
                $croppedImage,
                $image,
                0,
                0,
                self::$cropLeft[$options['shipId']],
                0,
                760,
                540,
                1520,
                1080
            );
            
            imagedestroy($image);
            
            if($cropResult === true && $croppedImage !== false)
            {
                return $croppedImage;
            }
            
            unset($croppedImage);
        }
        
        return null;
    }
}