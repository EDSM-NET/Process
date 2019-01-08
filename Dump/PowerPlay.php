<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Dump;

use         Process\Process;

class PowerPlay extends Process
{
    static private $tempFile        = APPLICATION_PATH . '/Data/Temp/powerPlay.json';
    static private $finalFile       = PUBLIC_PATH . '/dump/powerPlay.json';

    static private $countPerPages   = 1000;

    static public function run()
    {
        static::log('Generating ' . str_replace(PUBLIC_PATH, '', static::$finalFile));

        if(file_exists(static::$tempFile))
        {
            unlink(static::$tempFile);
        }

        $systemsPowerplayModel      = new \Models_Systems_Powerplay;
        $systemsHidesModel          = new \Models_Systems_Hides;

        $select  = $systemsPowerplayModel->select()
                                         ->where('dateUpdated >= ?', date('Y-m-d', strtotime('1 MONTH AGO')) . ' 00:00:00')
                                         ->where('dateUpdated <= ?', date('Y-m-d', strtotime('yesterday')) . ' 23:59:59')
                                         ->where(
                                             'refSystem NOT IN(?)',
                                             new \Zend_Db_Expr($systemsHidesModel->select()->from($systemsHidesModel, array('refSystem')))
                                         )
                                         ->order('refPower ASC')
                                         ->order('dateUpdated ASC');

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

            $systemsPowerplay = $paginator->getCurrentItems()->toArray();

            foreach($systemsPowerplay AS $systemPowerplay)
            {
                $system     = \Component\System::getInstance($systemPowerplay['refSystem']);

                if($system->isHidden() === false)
                {
                    $tmpSystem  = array();

                    $tmpSystem['power']         = \Alias\System\Power::get($systemPowerplay['refPower']);
                    $tmpSystem['powerState']    = $systemPowerplay['state'];

                    $tmpSystem['id']            = (int) $system->getId();
                    $tmpSystem['id64']          = $system->getId64();;
                    $tmpSystem['name']          = $system->getName();

                    if(!is_null($system->getX()))
                    {
                        $tmpSystem['coords'] = array(
                            'x' => $system->getX() / 32,
                            'y' => $system->getY() / 32,
                            'z' => $system->getZ() / 32,
                        );
                    }

                    $tmpSystem['allegiance']    = $system->getAllegianceName();
                    $tmpSystem['government']    = $system->getGovernmentName();
                    $tmpSystem['state']         = $system->getFactionStateName();

                    $tmpSystem['date'] = $systemPowerplay['dateUpdated'];

                    if($key > 0)
                    {
                        file_put_contents(static::$tempFile, ',' . PHP_EOL, FILE_APPEND);
                    }

                    file_put_contents(static::$tempFile, '    ' . \Zend_Json::encode($tmpSystem), FILE_APPEND);

                    $key++;
                }
            }
        }

        file_put_contents(static::$tempFile, PHP_EOL . ']', FILE_APPEND);

        rename(static::$tempFile, static::$finalFile);

        // Add ContentLength JSON
        file_put_contents(
            str_replace('.json', '.length.json', static::$finalFile),
            filesize(static::$finalFile)
        );

        $systemsPowerplayModel->getAdapter()->closeConnection();
        unset($systemsPowerplayModel, $systemsHidesModel, $systems);

        static::endLog();
        return;
    }
}