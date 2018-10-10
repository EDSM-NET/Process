<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Record;

use         Process\Process;

class HighestSystemVisited extends Process
{
    static private $cacheKey = 'Statistics_SystemsController_HighestSystemVisited';

    static public function run()
    {
        $systemsModel               = new \Models_Systems;

        // Make record query
        $select = $systemsModel->select()
                               ->from($systemsModel, array('id'))
                               ->where('y > 100000')
                               ->where('firstDiscoveredBy IS NOT NULL')
                               ->order('y DESC')
                               ->limit(3);
        $result     = $systemsModel->fetchAll($select);

        if(!is_null($result) && count($result) > 0)
        {
            $result = $result->toArray();
            static::getDatabaseFileCache()->save($result[0], static::$cacheKey);

            // Give badge to all retroactive users
            foreach($result AS $record)
            {
                $system             = \EDSM_System::getInstance($record['id']);
                $firstDiscoveredBy  = $system->getFirstDiscoveredBy();

                if(!is_null($firstDiscoveredBy))
                {
                    $firstDiscoveredBy = \Component\User::getInstance($firstDiscoveredBy['user']);
                    $firstDiscoveredBy->giveBadge(
                        7800,
                        array('type' => 'highestSystemVisited', 'systemId' => $system->getId())
                    );
                }

                unset($system, $firstDiscoveredBy);
            }
        }

        static::log('<span class="text-info">Record\HighestSystemVisited</span>');

        $systemsModel->getAdapter()->closeConnection();
        unset($systemsModel);
        unset($result);

        return;
    }

    static public function getName()
    {
        return 'RECORD\Highest %1$s';
    }

    // Fake function for getText
    static private function ___translate___()
    {
        _('RECORD\Highest %1$s');
    }
}