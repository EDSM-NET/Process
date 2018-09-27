<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Record;

use         Process\Process;

class HighestSystem extends Process
{
    static private $cacheKey = 'Statistics_SystemsController_HighestSystem';
    
    static public function run()
    {
        $systemsModel               = new \Models_Systems;
        
        // Make record query
        $select = $systemsModel->select()
                               ->from($systemsModel, array('id'))
                               ->where('y > 170000')
                               ->order('y DESC')
                               ->limit(3);
        $result     = $systemsModel->fetchAll($select);
        
        if(!is_null($result) && count($result) > 0)
        {
            $result = $result->toArray();
            static::getDatabaseFileCache()->save($result[0], static::$cacheKey);
        }
        
        static::log('<span class="text-info">Record\HighestSystem</span>');
        
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