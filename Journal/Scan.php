<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Journal;

use         Process\Process;

class Scan extends Check
{
    static protected $limit         = 5000;
    static protected $lastModified  = array();

    protected static function getEntries()
    {
        $journalModels      = new \Models_Journal;
        $journalEntries     = $journalModels->select()
                                            ->limit(static::$limit)
                                            ->where('event = ?', 'Scan')
                                            ->orWhere('event = ?', 'SAAScanComplete')
                                            ->order('RAND()');

        return $journalModels->fetchAll($journalEntries);
    }
}