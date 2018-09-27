<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Record;

use         Process\Process;

class MostInSystem extends Process
{
    static private $cacheKey = 'Statistics_BodiesController_MostInSystem%GROUPNAME%_%TYPE%';
    static private $exclude     = array(
        1 => array( // Stars
            91,     // Neutron stars
        ),
        2 => array( // Planets
        
        ),
    );
    
    static public function run()
    {
        list($group, $type)         = func_get_args();
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
            static::log('<span class="text-info">Record\MostInSystem:</span> <span class="text-danger">Unknown group ' . $group . '</span>');
            return;
        }
        
        if(is_null($typeName))
        {
            static::log('<span class="text-info">Record\MostInSystem:</span> <span class="text-danger">Unknown type ' . $type . '</span>');
            return;
        }
        
        if(in_array($type, static::$exclude[$group]))
        {
            return;
        }
        
        // Make record query
        $select     = $systemsBodiesModel->select()
                                        ->from($systemsBodiesModel, array(
                                            'totalType' => new \Zend_Db_Expr('COUNT(id)'),
                                            'refSystem',
                                        ))
                                        ->where('`group` = ?', $group)
                                        ->where('`type` = ?', $type)
                                        ->order('totalType DESC')
                                        ->order('refSystem ASC')
                                        ->group('refSystem')
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
                $system             = \EDSM_System::getInstance($record['refSystem']);
                $firstDiscoveredBy  = $system->getFirstDiscoveredBy();
                
                if(!is_null($firstDiscoveredBy))
                {
                    $firstDiscoveredBy = \EDSM_User::getInstance($firstDiscoveredBy['user']);
                    $firstDiscoveredBy->giveBadge(
                        7800, 
                        array('type' => 'mostInSystem' . $groupName . '_' . $type, 'systemId' => $system->getId())
                    );
                }
                
                unset($system, $firstDiscoveredBy);
            }
        }
        
        static::log('<span class="text-info">Record\MostInSystem:</span> ' . $groupName . ' ' . $typeName);
        
        $systemsBodiesModel->getAdapter()->closeConnection();
        unset($group, $type, $groupName, $typeName);
        unset($systemsBodiesModel, $systemsBodiesSurfaceModel);
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