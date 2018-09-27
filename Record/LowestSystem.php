<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Record;

use         Process\Process;

class LowestSystem extends Process
{
    static private $cacheKey = 'Statistics_SystemsController_LowestSystem';
    
    static public function run()
    {
        $systemsModel               = new \Models_Systems;
        
        // Make record query
        $select = $systemsModel->select()
                               ->from($systemsModel, array('id'))
                               ->where('y < -549000')
                               ->order('y ASC')
                               ->limit(3);
        $result     = $systemsModel->fetchAll($select);
        
        if(!is_null($result) && count($result) > 0)
        {
            $result = $result->toArray();
            static::getDatabaseFileCache()->save($result[0], static::$cacheKey);
        }
        
        static::log('<span class="text-info">Record\LowestSystem</span>');
        
        $systemsModel->getAdapter()->closeConnection();
        unset($systemsModel);
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