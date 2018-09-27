<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\System;

use         Process\Process;

class Featured extends Process
{
    static private $limit = 20;
    
    static public function run()
    {
        $systemsModel           = new \Models_Systems;
        $systemsHidesModel      = new \Models_Systems_Hides;
        $systemsFeaturedModel   = new \Models_Systems_Featured;
        
        $nbAdded                = 0;
        $featuredSystems        = $systemsFeaturedModel->getFeatured();
        
        while(is_null($featuredSystems) || count($featuredSystems) < static::$limit)
        {
            $select = $systemsModel->select()
                                     ->from($systemsModel, array('id'))
                                     ->limit(1)
                                     ->where('x IS NULL')
                                     ->where('coordinatesLocked = ?', 0)
                                     ->where(
                                         'id NOT IN(?)',
                                         new \Zend_Db_Expr($systemsHidesModel->select()->from($systemsHidesModel, array('refSystem')))
                                     )
                                     ->where(
                                         'id NOT IN(?)',
                                         new \Zend_Db_Expr($systemsFeaturedModel->select()->from($systemsFeaturedModel, array('refSystem')))
                                     )
                                     ->order('countKnownRefs DESC')
                                     ->order('RAND()');
                         
            $newSystem = $systemsModel->fetchRow($select);
            
            if(!is_null($newSystem))
            {
                $systemsFeaturedModel->insert(array(
                    'refSystem'     => $newSystem->id,
                    'featuredAt'    => new \Zend_Db_Expr('NOW()'),
                ));
                $nbAdded++;
            }
            else
            {
                break;
            }
            
            $featuredSystems = $systemsFeaturedModel->getFeatured();
        }
        
        if($nbAdded > 0)
        {
            static::log('<span class="text-info">System\Featured:</span> Added ' . \Zend_Locale_Format::toNumber($nbAdded) . ' systems');
        }
        
        unset($systemsModel, $systemsHidesModel, $systemsFeaturedModel, $nbAdded, $featuredSystems, $newSystem);
        
        return;
    }
}