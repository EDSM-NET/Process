<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Dump;

use         Process\Process;

use         Alias\System\State;
use         Alias\System\Happiness;

class SystemsPopulated extends Process
{
    static private $tempFile        = APPLICATION_PATH . '/Data/Temp/systemsPopulated.json';
    static private $finalFile       = PUBLIC_PATH . '/dump/systemsPopulated.json.gz';

    static private $countPerPages   = 5000;

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
        $factionsInfluencesModel    = new \Models_Factions_Influences;

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

            $dumpStr    = '';
            $systems    = $paginator->getCurrentItems()->toArray();

            foreach($systems AS $system)
            {
                $system     = \Component\System::getInstance($system['id']);

                if($system->isHidden() === false)
                {
                    $tmpSystem  = array();

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
                    $tmpSystem['state']         = $system->getFactionStateName();
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

                        $tmp['isPlayer'] = $controllingFaction->isPlayerFaction();

                        $tmpSystem['controllingFaction'] = $tmp;
                    }
                    else
                    {
                        // Skip allegiance without a faction
                        continue;
                    }

                    // Other factions
                    $factions                   = $factionsInfluencesModel->getByRefSystem($system->getId());

                    if(!is_null($factions) && count($factions) > 0)
                    {
                        $tmpSystem['factions'] = array();

                        foreach($factions AS $factionInfluence)
                        {
                            $faction                        = \EDSM_System_Station_Faction::getInstance($factionInfluence['refFaction']);

                            $tmp                            = array();

                            $tmp['id']                      = $faction->getId();
                            $tmp['name']                    = $faction->getName();

                            if(!is_null($faction->getAllegiance()))
                            {
                                $tmp['allegiance'] = $faction->getAllegianceName();
                            }

                            if(!is_null($faction->getGovernment()))
                            {
                                $tmp['government'] = $faction->getGovernmentName();
                            }

                            $tmp['influence']               = (float) $factionInfluence['influence'];
                            $tmp['state']                   = State::get($factionInfluence['state']);

                            $statesToExport = array(
                                'activeStates',
                                'recoveringStates',
                                'pendingStates',
                            );

                            foreach($statesToExport AS $currentStatesExport)
                            {
                                $tmp[$currentStatesExport]        = array();

                                if(!is_null($factionInfluence[$currentStatesExport]))
                                {
                                    $factionInfluence[$currentStatesExport]   = \Zend_Json::decode($factionInfluence[$currentStatesExport]);

                                    foreach($factionInfluence[$currentStatesExport] AS $state)
                                    {
                                        if(!array_key_exists('trend', $state))
                                        {
                                            $tmp[$currentStatesExport][] = array(
                                                'state' => State::get($state['state']),
                                            );
                                        }
                                        else
                                        {
                                            $tmp[$currentStatesExport][] = array(
                                                'state' => State::get($state['state']),
                                                'trend' => (int) $state['trend'],
                                            );
                                        }
                                    }
                                }
                            }

                            $tmp['happiness']               = Happiness::get($factionInfluence['happiness']);

                            $tmp['isPlayer']                = $faction->isPlayerFaction();
                            $tmp['lastUpdate']              = strtotime($factionInfluence['dateUpdated']);

                            $tmpSystem['factions'][]        = $tmp;
                        }
                    }

                    // Stations
                    $tmpSystem['stations'] = array();
                    $stations = $system->getStations();

                    foreach($stations AS $station)
                    {
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
                            $tmpBody    = $body->renderApiArray();

                            $tmpSystem['bodies'][] = $tmpBody;
                        }
                    }

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

        $systemsModel->getAdapter()->closeConnection();
        unset($systemsModel, $systemsHidesModel, $systems);

        static::endLog();
        return;
    }
}