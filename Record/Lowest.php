<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Record;

use         Process\Process;

class Lowest extends Process
{
    static private $cacheKey = 'Statistics_BodiesController_Lowest%GROUPNAME%_%TYPE%';
    
    static public function run()
    {
        list($group, $type)         = func_get_args();
        $systemsModel               = new \Models_Systems;
        $systemsBodiesModel         = new \Models_Systems_Bodies;
        
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
            static::log('<span class="text-info">Record\Lowest:</span> <span class="text-danger">Unknown group ' . $group . '</span>');
            return;
        }
        
        if(is_null($typeName))
        {
            static::log('<span class="text-info">Record\Lowest:</span> <span class="text-danger">Unknown type ' . $type . '</span>');
            return;
        }
        
        // Make record query
        $select     = $systemsBodiesModel->select()
                                        ->from($systemsBodiesModel, array(
                                            'id', $systemsModel->info('name') . '.y',
                                        ))
                                        ->setIntegrityCheck(false)
                                        ->joinInner($systemsModel->info('name'), 'refSystem = ' . $systemsModel->info('name') . '.id', null)
                                        ->where('`group` = ?', $group)
                                        ->where('`type` = ?', $type)
                                        ->where($systemsModel->info('name') . '.y IS NOT NULL')
                                        ->order($systemsModel->info('name') . '.y ASC')
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
                
                if(!is_null($bodyFirstScannedBy) && $bodyFirstScannedBy instanceof \EDSM_User)
                {
                    $bodyFirstScannedBy->giveBadge(
                        7800, 
                        array('type' => 'lowest' . $groupName . '_' . $type, 'bodyId' => $body->getId())
                    );
                }
                
                unset($body, $bodyFirstScannedBy);
            }
        }
        
        static::log('<span class="text-info">Record\Lowest:</span> ' . $groupName . ' ' . $typeName);
        
        $systemsBodiesModel->getAdapter()->closeConnection();
        unset($group, $type, $groupName, $typeName);
        unset($systemsBodiesModel, $systemsBodiesSurfaceModel);
        unset($result);
        
        return;
    }
    
    static public function getName()
    {
        return 'RECORD\Lowest %1$s';
    }
    
    // Fake function for getText
    static private function ___translate___()
    {
        _('RECORD\Lowest %1$s');
    }
}