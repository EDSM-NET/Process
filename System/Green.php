<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\System;

use         Process\Process;

class Green extends Process
{
    static private $reset = false;
    static private $limit = 100000;
    
    static public function run()
    {
        $systemsModel       = new \Models_Systems;
        $systemsHidesModel  = new \Models_Systems_Hides;
        $needLoop           = true;
        
        if(static::$reset === true)
        {
            $greenToCheck       = $systemsModel->fetchAll(
                $systemsModel->select()
                             ->from($systemsModel, array('id'))
                             ->where('isGreen IS NOT NULL')
                             ->where(
                                 'id NOT IN(?)',
                                 new \Zend_Db_Expr($systemsHidesModel->select()->from($systemsHidesModel, array('refSystem')))
                             )
                             ->limit(static::$limit)
            );
            
            if(!is_null($greenToCheck) && count($greenToCheck) > 0)
            {
                $greenToCheck = $greenToCheck->toArray();
                
                foreach($greenToCheck AS $system)
                {
                    $system = \EDSM_System::getInstance($system['id']);
                    
                    if($system->isValid())
                    {
                        $systemsModel->updateById(
                            $system->getId(),
                            array('isGreen' => new \Zend_Db_Expr('NULL')),
                            false
                        );
                    }
                    
                    \EDSM_System::destroyInstance($system->getId());
                    unset($system);
                }
                
                static::log('<span class="text-info">System\Green:</span> Reset ' . \Zend_Locale_Format::toNumber(count($greenToCheck)) . ' systems');
            }
        }
        else
        {
            $greenToCheck       = $systemsModel->fetchAll(
                $systemsModel->select()
                             ->from($systemsModel, array('id'))
                             ->where('isGreen IS NULL')
                             ->where(
                                 'id NOT IN(?)',
                                 new \Zend_Db_Expr($systemsHidesModel->select()->from($systemsHidesModel, array('refSystem')))
                             )
                             ->limit(static::$limit)
            );
            
            if(!is_null($greenToCheck) && count($greenToCheck) > 0)
            {
                $greenToCheck = $greenToCheck->toArray();
                
                foreach($greenToCheck AS $system)
                {
                    $system = \EDSM_System::getInstance($system['id']);
                    
                    if($system->isValid())
                    {
                        $system->isGreen(true); // Force field update
                    }
                    
                    \EDSM_System::destroyInstance($system->getId());
                    unset($system);
                }
                
                static::log('<span class="text-info">System\Green:</span> Updated ' . \Zend_Locale_Format::toNumber(count($greenToCheck)) . ' systems');
            }   
        }
        
        unset($systemsModel, $systemsHidesModel, $greenToCheck);
        
        return;
    }
}