<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Dump;

use         Process\Process;

class SystemsWithCoordinatesLastWeek extends Process
{
    static private $tempFile        = APPLICATION_PATH . '/Data/Temp/systemsWithCoordinates7days.json';
    static private $finalFile       = PUBLIC_PATH . '/dump/systemsWithCoordinates7days.json.gz';

    static private $countPerPages   = 25000;

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

        $select       = $systemsModel->select()
                                     ->from($systemsModel, array('id'))
                                     ->where('x IS NOT NULL')
                                     ->where('updatetime >= DATE(DATE_SUB(NOW(), INTERVAL 7 DAY))')
                                     ->where('updatetime <= ?', date('Y-m-d', strtotime('yesterday')) . ' 23:59:59')
                                     ->where(
                                         'id NOT IN(?)',
                                         new \Zend_Db_Expr($systemsHidesModel->select()->from($systemsHidesModel, array('refSystem')))
                                     )
                                     ->order('updatetime ASC');

        file_put_contents(static::$tempFile, '[' . PHP_EOL);

        $adapter    = new \Zend_Paginator_Adapter_DbTableSelect($select);
        $paginator  = new \Zend_Paginator($adapter);
        $paginator->setItemCountPerPage( static::$countPerPages );

        $pageCount  = $paginator->count();
        $key        = 0;

        for($i = 1; $i <= $pageCount; $i++)
        {
            static::log('    - ' . \Zend_Locale_Format::toNumber($i / $pageCount * 100, array('precision' => 2)) . '%');

            $paginator->setCurrentPageNumber( $i );

            $dumpStr    = '';
            $systems    = $paginator->getCurrentItems()->toArray();

            foreach($systems AS $system)
            {
                $system     = \Component\System::getInstance($system['id']);

                if($system->isHidden() === false)
                {
                    $tmpSystem  = array();

                    $tmpSystem['name'] = $system->getName();

                    if(!is_null($system->getX()))
                    {
                        $tmpSystem['coords'] = array(
                            'x' => $system->getX() / 32,
                            'y' => $system->getY() / 32,
                            'z' => $system->getZ() / 32,
                        );
                    }

                    $id64 = $system->getId64();

                    if(is_null($id64))
                    {
                        $id64 = $system->calculateId64();

                        if(!is_null($id64))
                        {
                            $id64 = (int) $id64;

                            try
                            {
                                $systemsModel->updateById(
                                    $system->getId(),
                                    array(
                                        'id64'  => $id64,
                                    ),
                                    false
                                );
                            }
                            catch(\Zend_Exception $e){}
                        }
                    }

                    $tmpSystem['id']   = (int) $system->getId();
                    $tmpSystem['id64'] = $id64;
                    $tmpSystem['date'] = $system->getUpdateTime();

                    if($key > 0)
                    {
                        $dumpStr .= ',' . PHP_EOL;
                    }

                    $dumpStr .= '    ' . \Zend_Json::encode($tmpSystem);

                    $key++;
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