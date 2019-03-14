<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Record;

use         Process\Process;

class Hottest extends Process
{
    static private $cacheKey    = 'Statistics_BodiesController_Hottest%GROUPNAME%_%TYPE%';
    static private $exclude     = array(
        1 => array( // Stars
            92, 93, // Black holes
        ),
        2 => array( // Planets

        ),
    );

    static public function run()
    {
        list($group, $type)         = func_get_args();
        $systemsBodiesModel         = new \Models_Systems_Bodies;
        $systemsBodiesSurfaceModel  = new \Models_Systems_Bodies_Surface;

        if($group == 1)
        {
            $groupName  = 'Star';
            $typeName   = \Alias\Body\Star\Type::get($type);
        }
        elseif($group == 2)
        {
            $groupName = 'Planet';
            $typeName   = \Alias\Body\Planet\Type::get($type);
        }
        else
        {
            static::log('<span class="text-info">Record\Hottest:</span> <span class="text-danger">Unknown group ' . $group . '</span>');
            return;
        }

        if(is_null($typeName))
        {
            static::log('<span class="text-info">Record\Hottest:</span> <span class="text-danger">Unknown type ' . $type . '</span>');
            return;
        }

        if(in_array($type, static::$exclude[$group]))
        {
            return;
        }

        // Make record query
        $select     = $systemsBodiesModel->select()
                                        ->from($systemsBodiesModel, array(
                                            'id',
                                        ))
                                        ->setIntegrityCheck(false)
                                        ->joinInner(
                                            $systemsBodiesSurfaceModel->info('name'),
                                            $systemsBodiesSurfaceModel->info('name') . '.refBody = ' . $systemsBodiesModel->info('name') . '.id',
                                            null
                                        )
                                        ->where('`group` = ?', $group)
                                        ->where('`type` = ?', $type)
                                        ->where('surfaceTemperature IS NOT NULL')
                                        ->where('dateUpdated > ?', '2016-11-01 23:59:59')
                                        ->order('surfaceTemperature DESC')
                                        ->limit(3);
        $result     = $systemsBodiesModel->fetchAll($select);

        if(!is_null($result) && count($result) > 0)
        {
            $cacheKey   = str_replace(
                array('%GROUPNAME%', '%TYPE%'),
                array($groupName, $type),
                static::$cacheKey
            );

            $result = $result->toArray();
            static::getDatabaseFileCache()->save($result[0], $cacheKey);

            // Give badge to all retroactive users
            foreach($result AS $record)
            {
                $body               = \EDSM_System_Body::getInstance($record['id']);
                $bodyFirstScannedBy = $body->getFirstScannedBy();

                if(!is_null($bodyFirstScannedBy) && $bodyFirstScannedBy instanceof \Component\User)
                {
                    $bodyFirstScannedBy->giveBadge(
                        7800,
                        array('type' => 'hottest' . $groupName . '_' . $type, 'bodyId' => $body->getId())
                    );
                }

                unset($body, $bodyFirstScannedBy);
            }
        }

        static::log('<span class="text-info">Record\Hottest:</span> ' . $groupName . ' ' . $typeName);

        $systemsBodiesModel->getAdapter()->closeConnection();
        unset($group, $type, $groupName, $typeName);
        unset($systemsBodiesModel, $systemsBodiesSurfaceModel);
        unset($result);

        return;
    }

    static public function getName()
    {
        return 'RECORD\Hottest %1$s';
    }

    // Fake function for getText
    static private function ___translate___()
    {
        _('RECORD\Hottest %1$s');
    }
}