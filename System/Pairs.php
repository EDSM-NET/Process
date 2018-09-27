<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\System;

use         Process\Process;

class Pairs extends Process
{
    static public function run()
    {
        $systemsModel       = new \Models_Systems;
        $systemsHidesModel  = new \Models_Systems_Hides;
        
        $cacheKey           = 'SystemsController_pairsAction';
        $result             = static::getDatabaseCache()->load($cacheKey);
        
        $select             = $systemsModel->select()
                                            ->setIntegrityCheck(false)
                                            ->from(
                                                array('local' => $systemsModel->info('name')),
                                                array(
                                                    'idA' => 'local.id',
                                                    'idB' => 'remote.id'
                                                )
                                            )
                                            ->joinInner(
                                             array('remote' => $systemsModel->info('name')), 
                                             'local.id < remote.id',
                                             null
                                         )
                                         ->where('local.x IS NOT NULL')
                                         ->where('remote.x IS NOT NULL', 0)
                                         ->where('local.x = remote.x')
                                         ->where('local.y = remote.y')
                                         ->where('local.z = remote.z');
        
        $result             = $systemsModel->fetchAll($select)->toArray();
        static::getDatabaseCache()->save($result, $cacheKey);
            
        static::log('<span class="text-info">System\Pairs:</span> Found ' . \Zend_Locale_Format::toNumber(count($result)) . ' systems');
        
        unset($systemsModel, $systemsHidesModel, $cacheKey, $result);
        
        return;
    }
}