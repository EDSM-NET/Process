<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Record;

use         Process\Process;

class FarthestSystemColonia extends Process
{
    static private $cacheKey = 'Statistics_SystemsController_FarthestSystemColonia';

    static public function run()
    {
        $systemsModel               = new \Models_Systems;

        // Make record query
        $select = $systemsModel->select()
                               ->from(
                                   $systemsModel, array(
                                        'id',
                                        'calculatedDistance' => new \Zend_Db_Expr('SQRT(POW((x / 32) - (-9530.5), 2) + POW((y / 32) - (-910.28125), 2) + POW((z / 32) - (19808.125), 2)) * 100')
                                   ))
                               ->order('calculatedDistance DESC')
                               ->limit(3);
        $result     = $systemsModel->fetchAll($select);

        if(!is_null($result) && count($result) > 0)
        {
            $result = $result->toArray();
            static::getDatabaseFileCache()->save($result[0], static::$cacheKey);

            // Give badge to all retroactive users
            foreach($result AS $record)
            {
                $system             = \Component\System::getInstance($record['id']);
                $firstDiscoveredBy  = $system->getFirstDiscoveredBy();

                if(!is_null($firstDiscoveredBy))
                {
                    $firstDiscoveredBy = \Component\User::getInstance($firstDiscoveredBy['user']);
                    $firstDiscoveredBy->giveBadge(
                        7800,
                        array('type' => 'farthestSystemColonia', 'systemId' => $system->getId())
                    );
                }

                unset($system, $firstDiscoveredBy);
            }
        }

        static::log('<span class="text-info">Record\FarthestSystemColonia</span>');

        $systemsModel->getAdapter()->closeConnection();
        unset($systemsModel);
        unset($result);

        return;
    }

    static public function getName()
    {
        return 'RECORD\Farthest %1$s from Colonia';
    }

    // Fake function for getText
    static private function ___translate___()
    {
        _('RECORD\Farthest %1$s from Colonia');
    }
}