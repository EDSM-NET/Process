<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Record;

use         Process\Process;

class FarthestSol extends Process
{
    static private $cacheKey = 'Statistics_BodiesController_Farthest%GROUPNAME%Sol_%TYPE%';
    
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
            static::log('<span class="text-info">Record\FarthestSol:</span> <span class="text-danger">Unknown group ' . $group . '</span>');
            return;
        }
        
        if(is_null($typeName))
        {
            static::log('<span class="text-info">Record\FarthestSol:</span> <span class="text-danger">Unknown type ' . $type . '</span>');
            return;
        }
        
        // Make record query
        $select     = $systemsBodiesModel->select()
                                        ->from($systemsBodiesModel, array(
                                            'id'                    => $systemsBodiesModel->info('name') . '.id',
                                            'calculatedDistance'    => new \Zend_Db_Expr('SQRT(POW((' . $systemsModel->info('name') . '.x / 32), 2) + POW((' . $systemsModel->info('name') . '.y / 32), 2) + POW((' . $systemsModel->info('name') . '.z / 32), 2)) * 100')
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
        }
        
        static::log('<span class="text-info">Record\FarthestSol:</span> ' . $groupName . ' ' . $typeName);
        
        $systemsBodiesModel->getAdapter()->closeConnection();
        unset($group, $type, $groupName, $typeName);
        unset($systemsBodiesModel, $systemsBodiesSurfaceModel);
        unset($result);
        
        return;
    }
    
    static public function getName()
    {
        return 'RECORD\Farthest %1$s from Sol';
    }
    
    // Fake function for getText
    static private function ___translate___()
    {
        _('RECORD\Farthest %1$s from Sol');
    }
}