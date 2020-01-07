<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Dump;

use         Process\Process;

class SystemsWithoutCoordinates extends Process
{
    static private $tempFile        = APPLICATION_PATH . '/Data/Temp/systemsWithoutCoordinates.json';
    static private $finalFile       = PUBLIC_PATH . '/dump/systemsWithoutCoordinates.json.gz';

    static public function run()
    {
        static::log('Generating ' . str_replace(PUBLIC_PATH, '', static::$finalFile));

        if(file_exists(static::$tempFile))
        {
            unlink(static::$tempFile);
        }

        $systemsModel       = new \Models_Systems;
        $systemsHidesModel  = new \Models_Systems_Hides;

        // Disable cache
        $systemsModel->disableCache();

        // Fill an array with all days from start to yesterday
        $startDate  = strtotime('2015-05-12');
        $endDate    = strtotime('yesterday');

        $loopDates  = array();

        while($startDate < $endDate)
        {
            $loopDates[]    = date('Y-m-d', $startDate);
            $startDate     += 86400; // Add 1 day
        }

        file_put_contents(static::$tempFile, '[' . PHP_EOL);
        $line = 0;

        foreach($loopDates AS $key => $date)
        {
            static::log('    - ' . \Zend_Locale_Format::toNumber($key / count($loopDates) * 100, array('precision' => 2)) . '%');

            $select     = $systemsModel->select()
                                       ->from($systemsModel, array('id', 'id64', 'name', 'x', 'y', 'z', 'updatetime'))
                                       ->where('x IS NULL')
                                       ->where('updatetime > ?', $date . ' 00:00:00')
                                       ->where('updatetime <= ?', date('Y-m-d', (strtotime($date) + 86400)) . ' 00:00:00')
                                       ->where(
                                           'id NOT IN(?)',
                                           new \Zend_Db_Expr($systemsHidesModel->select()->from($systemsHidesModel, array('refSystem')))
                                       )
                                       ->order('updatetime ASC');

            $systems    = $systemsModel->fetchAll($select);
            $dumpStr    = '';

            if(!is_null($systems))
            {
                $systems = $systems->toArray();

                foreach($systems AS $system)
                {
                    $tmpSystem          = array();
                    $tmpSystem['id']    = (int) $system['id'];
                    $systemTmp          = \Component\System::getInstance($tmpSystem['id']);

                    if(is_null($system['id64']))
                    {
                        $id64       = $systemTmp->calculateId64();

                        if(!is_null($id64))
                        {
                            $id64 = (int) $id64;

                            try
                            {
                                $systemsModel->updateById(
                                    $systemTmp->getId(),
                                    array(
                                        'id64'  => $id64,
                                    ),
                                    false
                                );

                                $tmpSystem['id64'] = $id64;
                            }
                            catch(\Zend_Exception $e){
                                $tmpSystem['id64'] = null;
                            }
                        }
                        else
                        {
                            $tmpSystem['id64'] = null;
                        }
                    }
                    else
                    {
                        $tmpSystem['id64'] = (int) $system['id64'];
                    }

                    $tmpSystem['name']              = $system['name'];

                    $estimatedCoordinates = $systemTmp->getEstimatedCoordinates();

                    if(!is_null($estimatedCoordinates) && is_array($estimatedCoordinates))
                    {
                        $tmpSystem['estimatedCoordinates'] = array(
                            'x'         => (float) $estimatedCoordinates['x'],
                            'y'         => (float) $estimatedCoordinates['y'],
                            'z'         => (float) $estimatedCoordinates['z'],
                            'precision' => (float) $estimatedCoordinates['uncertainty'],
                        );
                    }

                    $tmpSystem['date']              = $system['updatetime'];

                    if($line > 0)
                    {
                        $dumpStr .= ',' . PHP_EOL;
                    }

                    $dumpStr .= '    ' . \Zend_Json::encode($tmpSystem);

                    $line++;
                }
            }

            file_put_contents(static::$tempFile, $dumpStr, FILE_APPEND);
        }

        file_put_contents(static::$tempFile, PHP_EOL . ']', FILE_APPEND);

        $dest = static::$tempFile . '.gz';
        $mode = 'wb9';
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

        $systemsModel->getAdapter()->closeConnection();
        unset($systemsModel, $systemsHidesModel, $systems);

        static::endLog();
        return;
    }
}