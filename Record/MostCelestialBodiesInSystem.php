<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Record;

use         Process\Process;

class MostCelestialBodiesInSystem extends Process
{
    static private $cacheKey = 'Statistics_SystemsController_MostCelestialBodiesInSystem';

    static public function run()
    {
        $systemsBodiesModel         = new \Models_Systems_Bodies;

        // Make record query
        $select = $systemsBodiesModel->select()
                                   ->from(
                                       $systemsBodiesModel,
                                       array('refSystem', 'nbBodies' => new \Zend_Db_Expr('COUNT(id)'))
                                   )
                                   ->group('refSystem')
                                   ->order('nbBodies DESC')
                                   ->limit(3);
        $result     = $systemsBodiesModel->fetchAll($select);

        if(!is_null($result) && count($result) > 0)
        {
            $result = $result->toArray();
            static::getDatabaseFileCache()->save($result[0], static::$cacheKey);

            // Give badge to all retroactive users
            foreach($result AS $record)
            {
                $system             = \EDSM_System::getInstance($record['refSystem']);
                $firstDiscoveredBy  = $system->getFirstDiscoveredBy();

                if(!is_null($firstDiscoveredBy))
                {
                    $firstDiscoveredBy = \Component\User::getInstance($firstDiscoveredBy['user']);
                    $firstDiscoveredBy->giveBadge(
                        7800,
                        array('type' => 'mostCelestialBodiesInSystem', 'systemId' => $system->getId())
                    );
                }

                unset($system, $firstDiscoveredBy);
            }
        }

        static::log('<span class="text-info">Record\MostCelestialBodiesInSystem</span>');

        $systemsBodiesModel->getAdapter()->closeConnection();
        unset($systemsBodiesModel);
        unset($result);

        return;
    }

    static public function getName()
    {
        return 'RECORD\The most %1$s in system';
    }

    // Fake function for getText
    static private function ___translate___()
    {
        _('RECORD\The most %1$s in system');
    }
}