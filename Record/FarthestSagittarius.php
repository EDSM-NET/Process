<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Record;

use         Process\Process;

class FarthestSagittarius extends Process
{
    static private $cacheKey = 'Statistics_BodiesController_Farthest%GROUPNAME%Sagittarius_%TYPE%';
    
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
            static::log('<span class="text-info">Record\FarthestSagittarius:</span> <span class="text-danger">Unknown group ' . $group . '</span>');
            return;
        }
        
        if(is_null($typeName))
        {
            static::log('<span class="text-info">Record\FarthestSagittarius:</span> <span class="text-danger">Unknown type ' . $type . '</span>');
            return;
        }
        
        // Make record query
        $select     = $systemsBodiesModel->select()
                                        ->from($systemsBodiesModel, array(
                                            'id'                    => $systemsBodiesModel->info('name') . '.id',
                                            'calculatedDistance'    => new \Zend_Db_Expr('SQRT(POW((' . $systemsModel->info('name') . '.x / 32) - (25.21875), 2) + POW((' . $systemsModel->info('name') . '.y / 32) - (-20.90625), 2) + POW((' . $systemsModel->info('name') . '.z / 32) - (25899.96875), 2)) * 100')
                                        ))
                                        ->setIntegrityCheck(false)
                                        ->joinInner($systemsModel->info('name'), 'refSystem = ' . $systemsModel->info('name') . '.id', null)
                                        ->where('`group` = ?', $group)
                                        ->where('`type` = ?', $type)
                                        ->order('calculatedDistance DESC')
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
                        array('type' => 'farthest' . $groupName . 'Sagittarius_' . $type, 'bodyId' => $body->getId())
                    );
                }
                
                unset($body, $bodyFirstScannedBy);
            }
        }
        
        static::log('<span class="text-info">Record\FarthestSagittarius:</span> ' . $groupName . ' ' . $typeName);
        
        $systemsBodiesModel->getAdapter()->closeConnection();
        unset($group, $type, $groupName, $typeName);
        unset($systemsBodiesModel, $systemsBodiesSurfaceModel);
        unset($result);
        
        return;
    }
    
    static public function getName()
    {
        return 'RECORD\Farthest %1$s from Sagittarius A*';
    }
    
    // Fake function for getText
    static private function ___translate___()
    {
        _('RECORD\Farthest %1$s from Sagittarius A*');
    }
}