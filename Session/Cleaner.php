<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Session;

use         Process\Process;

class Cleaner extends Process
{
    static private $sessionPath     = null;
    static private $maxLife         = null;

    static public function run()
    {
        if(is_null(static::$sessionPath))
        {
            static::$sessionPath    = \Zend_Session::getOptions('save_path');
        }
        if(is_null(static::$maxLife))
        {
            static::$maxLife        = \Zend_Session::getOptions('gc_maxlifetime');
        }


        $files          = scandir(static::$sessionPath);
        $nbDelete       = 0;
        $currentTime    = time();

        foreach($files AS $file)
        {
            if(is_file(static::$sessionPath . '/' . $file))
            {
                $fileTime       = filemtime(static::$sessionPath . '/' . $file);
                $fileSize       = filesize(static::$sessionPath . '/' . $file);

                if($fileSize == 0)
                {
                    if($fileTime + 60 < $currentTime)
                    {
                        @unlink(static::$sessionPath . '/' . $file);
                        $nbDelete++;

                        continue;
                    }
                }

                if($fileTime + static::$maxLife < $currentTime)
                {
                    @unlink(static::$sessionPath . '/' . $file);
                    $nbDelete++;
                }
            }
        }

        if($nbDelete > 0)
        {
            static::log('<span class="text-info">Session\Cleaner:</span> ' . \Zend_Locale_Format::toNumber($nbDelete) . ' files deleted.');
        }

        unset($files, $nbDelete, $currentTime);

        return;
    }
}