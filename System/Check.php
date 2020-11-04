<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\System;

use         Process\Process;

class Check extends Process
{
    static public function run()
    {
        $systemsModel           = new \Models_Systems;
        $systemsHidesModel      = new \Models_Systems_Hides;
        $distancesModel         = new \Models_Distances;

        $select = $systemsModel->select()
                             ->from($systemsModel, array('id'))
                             ->limit(1)
                             ->where('countKnownRefs IS NULL OR countKnownRefs > 4')
                             ->where('coordinatesLocked = ?', 0)
                             ->where(
                                 'id NOT IN(?)',
                                 new \Zend_Db_Expr($systemsHidesModel->select()->from($systemsHidesModel, array('refSystem')))
                             )
                             ->order('lastTrilateration ASC')
                             ->order('id ASC');

        $currentSystem = $systemsModel->fetchRow($select);

        if(!is_null($currentSystem))
        {
            $system   = \Component\System::getInstance($currentSystem->id);

            static::log('<span class="text-info">System\Check:</span> ' . $system->getName() . ' #' . $currentSystem->id);

            if(is_null($system->getCountKnownRefs()))
            {
                $countKnownRefs = $systemsModel->countKnownRefs($currentSystem->id);
                $systemsModel->updateById($currentSystem->id, array('countKnownRefs' => $countKnownRefs), false);
                unset($countKnownRefs);
            }

            $return   = \EDSM_System_Coordinates::tryCalculate($system, true);

            $cacheKey = 'EDSM_System_Coordinates_tryCalculate_Verbose_' . $currentSystem->id;
            //static::getDatabaseCache()->remove($cacheKey);
            static::getDatabaseCache()->save($return, $cacheKey);
            unset($return['verbose']); // Remove verbose to save both caches

            $cacheKey = 'EDSM_System_Coordinates_tryCalculate_' . $currentSystem->id;
            //static::getDatabaseCache()->remove($cacheKey);
            static::getDatabaseCache()->save($return, $cacheKey);

            if(!is_null($return))
            {
                if($return['status'] === 'ok')
                {
                    if(is_null($system->getX()))
                    {
                        static::log('              <span class="text-success">Coordinates found!</span> ['
                            . ($return['coordinates']['x'] / 32) . ' / '
                            . ($return['coordinates']['y'] / 32) . ' / '
                            . ($return['coordinates']['z'] / 32) . ']');

                        $systemsModel->updateById(
                            $currentSystem->id,
                            array(
                                'x'                     => $return['coordinates']['x'],
                                'y'                     => $return['coordinates']['y'],
                                'z'                     => $return['coordinates']['z'],
                                'lastTrilateration'     => new \Zend_Db_Expr('NOW()'),
                            )
                        );

                        $distancesModel->removeReferencesTrilaterationCaches($currentSystem->id);
                    }
                    else
                    {
                        if($system->getX() != $return['coordinates']['x'] || $system->getY() != $return['coordinates']['y'] || $system->getZ() != $return['coordinates']['z'])
                        {
                            static::log('              Coordinates updated! ['
                                . '<span class="' . ( ($system->getX() == $return['coordinates']['x']) ? 'text-success' : 'text-danger' ) . '">' . ($return['coordinates']['x'] / 32) . '</span> / '
                                . '<span class="' . ( ($system->getY() == $return['coordinates']['y']) ? 'text-success' : 'text-danger' ) . '">' . ($return['coordinates']['y'] / 32) . '</span> / '
                                . '<span class="' . ( ($system->getZ() == $return['coordinates']['z']) ? 'text-success' : 'text-danger' ) . '">' . ($return['coordinates']['z'] / 32) . '</span>]');

                            $systemsModel->updateById(
                                $currentSystem->id,
                                array(
                                    'x'                     => $return['coordinates']['x'],
                                    'y'                     => $return['coordinates']['y'],
                                    'z'                     => $return['coordinates']['z'],
                                    'lastTrilateration'     => new \Zend_Db_Expr('NOW()'),
                                )
                            );

                            $distancesModel->removeReferencesTrilaterationCaches($currentSystem->id);
                        }
                        else
                        {
                            $systemsModel->updateById(
                                $currentSystem->id,
                                array(
                                    'lastTrilateration'     => new \Zend_Db_Expr('NOW()'),
                                ),
                                false
                            );
                        }
                    }

                    \Component\System::destroyInstance($currentSystem->id);

                    $systemsModel->getAdapter()->closeConnection();
                    unset($systemsModel, $systemsHidesModel, $distancesModel, $currentSystem, $system);
                    return;
                }
                else
                {
                    if(!is_null($system->getX()))
                    {
                        static::log('              <span class="text-danger">Coordinates invalid!</span> ['
                            . ($system->getX() / 32) . ' / '
                            . ($system->getY() / 32) . ' / '
                            . ($system->getZ() / 32) . ']');
                        //static::log('                => ' . $return['status']);
                        static::log('                => ' . $return['statusMessage']);

                        $systemsModel->updateById(
                            $currentSystem->id,
                            array(
                                'lastTrilateration'           => new \Zend_Db_Expr('NOW()'),
                            ),
                            false
                        );

                        \Component\System::destroyInstance($currentSystem->id);

                        unset($systemsModel, $systemsHidesModel, $distancesModel, $currentSystem, $system);
                        return;
                    }
                }
            }

            $systemsModel->updateById(
                $currentSystem->id,
                array(
                    'lastTrilateration' => new \Zend_Db_Expr('NOW()')
                ),
                false
            );

            \Component\System::destroyInstance($currentSystem->id);

            $systemsModel->getAdapter()->closeConnection();
            unset($systemsModel, $systemsHidesModel, $distancesModel, $currentSystem, $system);
        }

        return;
    }
}