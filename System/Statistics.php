<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\System;

use         Process\Process;

class Statistics extends Process
{
    static public function run()
    {
        $systemsModel       = new \Models_Systems;
        $systemsLogsModel   = new \Models_Systems_Logs;
        
        $countSystems = $systemsModel->countSystems(true);
        static::log('<span class="text-info">System\Statistics:</span> ' . \Zend_Locale_Format::toNumber($countSystems) . ' systems');
        
        $countSystemsHavingCoords = $systemsModel->countSystemsHavingCoords(true);
        static::log('<span class="text-info">System\Statistics:</span> ' . \Zend_Locale_Format::toNumber($countSystemsHavingCoords) . ' systems with coordinates');
        
        $countSystemsHavingLockedCoords = $systemsModel->countSystemsHavingLockedCoords(true);
        static::log('<span class="text-info">System\Statistics:</span> ' . \Zend_Locale_Format::toNumber($countSystemsHavingLockedCoords) . ' systems with locked coordinates');
        
        $countFlightLogs = $systemsLogsModel->countFlightLogs(true);
        static::log('<span class="text-info">System\Statistics:</span> ' . \Zend_Locale_Format::toNumber($countFlightLogs) . ' flight logs');
        
        $countFlightLogEntries = $systemsLogsModel->countFlightLogEntries(true);
        static::log('<span class="text-info">System\Statistics:</span> ' . \Zend_Locale_Format::toNumber($countFlightLogEntries) . ' flight log entries');
        
        unset($systemsModel, $countSystems, $countSystemsHavingCoords, $countSystemsHavingLockedCoords);
        unset($systemsLogsModel, $countFlightLogs, $countFlightLogEntries);
        
        return;
    }
}