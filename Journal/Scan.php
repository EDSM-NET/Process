<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Journal;

use         Process\Process;

class Scan extends Check
{
    static protected $limit         = 2500;

    protected static function getEntries()
    {
        $limit              = static::$limit;
        $results            = array();
        $journalModels      = new \Models_Journal;
        $usersModel         = new \Models_Users;

        // Prioritary users
        if($limit > 0)
        {
            $journalEntries     = $journalModels->select()
                                                ->limit($limit * 2)
                                                ->setIntegrityCheck(false)
                                                ->from($journalModels, array($journalModels->info('name') . '.*'))
                                                ->joinInner($usersModel->info('name'), $journalModels->info('name') . '.refUser = ' . $usersModel->info('name') . '.id', null)
                                                ->where('event = ?', 'Scan')
                                                ->where($usersModel->info('name') . '.waitScanBodyFromEDDN = ?', 0);
            $resultsTmp            = $journalModels->fetchAll($journalEntries);

            if(!is_null($resultsTmp) && count($resultsTmp) > 0)
            {
                //$limit   -= count($resultsTmp);
                static::log('<span class="text-info">' . str_replace('Process\\', '', static::class) . ':</span> Selected ' . \Zend_Locale_Format::toNumber(count($resultsTmp)) . ' prioritary scan events');
                $results = array_merge($results, $resultsTmp->toArray());
            }
            unset($resultsTmp);
        }

        // Oldest scans
        if($limit > 0)
        {
            $journalEntries     = $journalModels->select()
                                                ->limit($limit)
                                                ->where('dateEvent < DATE_SUB(NOW(), INTERVAL 1 MONTH)')
                                                ->where('event = ?', 'Scan');
            $resultsTmp            = $journalModels->fetchAll($journalEntries);

            if(!is_null($resultsTmp) && count($resultsTmp) > 0)
            {
                //$limit   -= count($resultsTmp);
                static::log('<span class="text-info">' . str_replace('Process\\', '', static::class) . ':</span> Selected ' . \Zend_Locale_Format::toNumber(count($resultsTmp)) . ' old scan events');

                $results = array_merge($results, $resultsTmp->toArray());
            }
            unset($resultsTmp);
        }

        // Randomly reparse
        if($limit > 0)
        {

            $journalEntries     = $journalModels->select()
                                                ->limit($limit)
                                                ->where('event = ?', 'Scan')
                                                ->orWhere('event = ?', 'SAAScanComplete')
                                                ->order('RAND()');
            $resultsTmp            = $journalModels->fetchAll($journalEntries);

            if(!is_null($resultsTmp) && count($resultsTmp) > 0)
            {
                $results = array_merge($results, $resultsTmp->toArray());
            }
            unset($resultsTmp);
        }

        return $results;
    }
}