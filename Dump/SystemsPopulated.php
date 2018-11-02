<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Dump;

use         Process\Process;

class SystemsPopulated extends Process
{
    static private $tempFile        = APPLICATION_PATH . '/Data/Temp/systemsPopulated.json';
    static private $finalFile       = PUBLIC_PATH . '/dump/systemsPopulated.json';

    static private $countPerPages   = 1000;

    static public function run()
    {
        static::log('Generating ' . str_replace(PUBLIC_PATH, '', static::$finalFile));

        if(file_exists(static::$tempFile))
        {
            unlink(static::$tempFile);
        }

        $systemsModel               = new \Models_Systems;
        $systemsInformationsModel   = new \Models_Systems_Informations;
        $systemsHidesModel          = new \Models_Systems_Hides;

        $select       = $systemsModel->select()
                                     ->from($systemsModel, array('id'))
                                     ->setIntegrityCheck(false)
                                     ->joinInner($systemsInformationsModel->info('name'), 'id = refSystem', null)
                                     ->where('updatetime <= ?', date('Y-m-d', strtotime('yesterday')) . ' 23:59:59')
                                     ->where('allegiance != ?', 7)
                                     ->where('allegiance != ?', 8)
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

            $systems = $paginator->getCurrentItems()->toArray();

            foreach($systems AS $system)
            {
                $system     = \Component\System::getInstance($system['id']);

                if($system->isHidden() === false)
                {
                    $tmpSystem  = array();

                    $id64 = $system->getId64();

                    if(is_null($id64))
                    {
                        $id64 = $system->getId64FromEDTS();

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

                    $tmpSystem['name'] = $system->getName();

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
                    $tmpSystem['economy']       = $system->getEconomyName();
                    $tmpSystem['security']      = $system->getSecurityName();
                    $tmpSystem['population']    = $system->getPopulation();

                    $controllingFaction = $system->getFaction();
                    if(!is_null($controllingFaction))
                    {
                        $tmp = array(
                            'id'    => $controllingFaction->getId(),
                            'name'  => $controllingFaction->getName(),
                        );

                        if(!is_null($controllingFaction->getAllegiance()))
                        {
                            $tmp['allegiance'] = $controllingFaction->getAllegianceName();
                        }

                        if(!is_null($controllingFaction->getGovernment()))
                        {
                            $tmp['government'] = $controllingFaction->getGovernmentName();
                        }

                        $tmpSystem['controllingFaction'] = $tmp;
                    }
                    else
                    {
                        // Skip allegiance without a faction
                        //\Zend_Debug::dump($tmpSystem, 'MISS');
                        continue;
                    }

                    // Stations
                    $tmpSystem['stations'] = array();
                    $stations = $system->getStations();

                    foreach($stations AS $station)
                    {
                        $station    = \EDSM_System_Station::getInstance($station['id']);
                        $tmpStation = $station->renderApiArray();

                        $tmpSystem['stations'][] = $tmpStation;
                    }


                    // Bodies
                    $tmpSystem['bodies']    = array();
                    $bodies                 = $system->getBodies();

                    if(!is_null($bodies) && count($bodies) > 0)
                    {
                        foreach($bodies AS $body)
                        {
                            $body       = \EDSM_System_Body::getInstance($body['id']);
                            $tmpBody    = $body->renderApiArray();

                            $tmpSystem['bodies'][] = $tmpBody;
                        }
                    }

                    $tmpSystem['date'] = $system->getUpdateTime();

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

        $systemsModel->getAdapter()->closeConnection();
        unset($systemsModel, $systemsHidesModel, $systems);

        static::endLog();
        return;
    }
}