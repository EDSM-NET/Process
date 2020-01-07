<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Dump;

use         Process\Process;

class Station extends Process
{
    static private $tempFile    = APPLICATION_PATH . '/Data/Temp/stations.json';
    static private $finalFile   = PUBLIC_PATH . '/dump/stations.json.gz';

    static public function run()
    {
        static::log('Generating ' . str_replace(PUBLIC_PATH, '', static::$finalFile));

        if(file_exists(static::$tempFile))
        {
            unlink(static::$tempFile);
        }

        $stationsModel  = new \Models_Stations;
        $select         = $stationsModel->select()
                                        ->from($stationsModel, array('id'))
                                        ->order('updateTime ASC');
        $stations       = $stationsModel->fetchAll($select)
                                        ->toArray();
        $stationsTotal  = count($stations);
        $lastPercent    = 0;

        file_put_contents(static::$tempFile, '[' . PHP_EOL);

        $dumpStr = '';

        foreach($stations AS $key => $station)
        {
            $newPercent = floor(($key + 1) / $stationsTotal * 100);

            if($newPercent > $lastPercent)
            {
                static::log('    - ' . \Zend_Locale_Format::toNumber($newPercent) . '%');
                $lastPercent = $newPercent;
            }

            $station    = \EDSM_System_Station::getInstance($station['id']);
            $tmpStation = $station->renderApiArray();

            $stationSystem = $station->getSystem();
            if(!is_null($stationSystem))
            {
                $tmpStation['systemId']    = $stationSystem->getId();
                $tmpStation['systemId64']  = $stationSystem->getId64();
                $tmpStation['systemName']  = $stationSystem->getName();
            }

            if($key > 0)
            {
                $dumpStr .= ',' . PHP_EOL;
            }

            $dumpStr .= '    ' . \Zend_Json::encode($tmpStation);
        }

        file_put_contents(static::$tempFile, $dumpStr, FILE_APPEND);

        file_put_contents(static::$tempFile, PHP_EOL . ']', FILE_APPEND);

        $dest = static::$tempFile . '.gz';
        $mode = 'wb9';
        $error = false;
        if($fp_out = gzopen($dest, $mode))
        {
            if($fp_in = fopen(static::$tempFile,'rb'))
            {
                while (!feof($fp_in))
                {
                    gzwrite($fp_out, fread($fp_in, 1024 * 512));
                }
                fclose($fp_in);
            }
            gzclose($fp_out);
        }

        unlink(static::$tempFile);
        rename($dest, static::$finalFile);

        // Add ContentLength JSON
        file_put_contents(
            str_replace('.json.gz', '.length.json.gz', static::$finalFile),
            filesize(static::$finalFile)
        );

        $stationsModel->getAdapter()->closeConnection();
        unset($stationsModel, $stations);

        static::endLog();
        return;
    }
}