<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Elite;

use         Process\Process;

class Market extends Process
{
    public static $sendCompleteStats = true;

    static public function run()
    {
        $stationsModel              = new \Models_Stations;
        $stationsServicesModel      = new \Models_Stations_Services();

        $yesterdayDate  = date('Y-m-d', strtotime('yesterday'));
        $tweet          = array();
        $tweet[]        = 'Galactic Stations update coverage: #EliteDangerous';

        // Complete stats are sent at night with our CRON job
        if(self::$sendCompleteStats === true)
        {
            // Get the total station count
            /*
            $stationsTotal  = $stationsModel->fetchRow(
                $stationsModel->select()->from($stationsModel, array(
                    'total' => new \Zend_Db_Expr('COUNT(1)')
                ))
                ->where('name NOT LIKE "Rescue Ship - %"')
            );

            $stationsTotal  = $stationsTotal->total;
            */

            // Get the total station count
            $stationsTotal  = $stationsModel->fetchRow(
                $stationsModel->select()->from($stationsModel, array(
                    'total' => new \Zend_Db_Expr('COUNT(1)')
                ))
                ->joinInner($stationsServicesModel->info('name'), $stationsServicesModel->info('name') . '.refStation = ' . $stationsModel->info('name') . '.id', null)
                //->where('marketUpdateTime IS NOT NULL')
                ->where($stationsServicesModel->info('name') . '.refService = ?', 11)
                ->where($stationsModel->info('name') . '.name NOT LIKE "Rescue Ship - %"')
            );

            $stationsTotal  = $stationsTotal->total;
            $tweet[]        = '- ' . \Zend_Locale_Format::toNumber($stationsTotal) . ' stations registered.';

            // Foreach each needed time, get total updated
            $stationsUpdate             = array();
            $stationsUpdate['1 WEEK']   = 'last week';
            $stationsUpdate['2 WEEK']   = 'last two weeks';
            $stationsUpdate['1 MONTH']  = 'last month';

            foreach($stationsUpdate AS $interval => $value)
            {
                $stationsCurrent  = $stationsModel->fetchRow(
                    $stationsModel->select()->from($stationsModel, array(
                        'total' => new \Zend_Db_Expr('COUNT(1)')
                    ))
                    ->joinInner($stationsServicesModel->info('name'), $stationsServicesModel->info('name') . '.refStation = ' . $stationsModel->info('name') . '.id', null)
                    //->where('marketUpdateTime >= DATE_SUB(?, INTERVAL ' . $interval . ')', $yesterdayDate)
                    ->where($stationsModel->info('name') . '.updateTime >= DATE_SUB(?, INTERVAL ' . $interval . ')', $yesterdayDate)
                    ->where($stationsServicesModel->info('name') . '.refService = ?', 11)
                    //->where('marketUpdateTime IS NOT NULL')
                    ->where($stationsModel->info('name') . '.name NOT LIKE "Rescue Ship - %"')
                );
                $stationsCurrent  = $stationsCurrent->total;

                $tweet[]        = '- ' . \Zend_Locale_Format::toNumber($stationsCurrent / $stationsTotal * 100, array('precision' => 2)) . '% updated since ' . $value . '.';
            }
        }

        // Select oldest updated, so users can go visit it!
        /*
        $stationOldest  = $stationsModel->fetchRow(
            $stationsModel->select()
                          ->where('marketUpdateTime IS NOT NULL')
                          ->where('name NOT LIKE "Rescue Ship - %"')
                          ->order('marketUpdateTime ASC')
                          ->limit(1)
        );
        */
        $stationOldest              = $stationsModel->fetchRow(
            $stationsModel->select()
                          ->setIntegrityCheck(false)
                          ->from($stationsModel, array('id'))
                          ->joinInner($stationsServicesModel->info('name'), $stationsServicesModel->info('name') . '.refStation = ' . $stationsModel->info('name') . '.id', null)
                          ->where($stationsModel->info('name') . '.name NOT LIKE ?', 'Rescue Ship - %')
                          ->where($stationsModel->info('name') . '.type != ?', 31)
                          ->where($stationsServicesModel->info('name') . '.refService = ?', 11)
                          //->where('marketUpdateTime IS NOT NULL')
                          //->order('marketUpdateTime ASC')
                          ->order($stationsModel->info('name') . '.updateTime ASC')
                          ->limit(1)
        );

        $stationOldest  = $stationOldest->toArray();
        $stationOldest  = \EDSM_System_Station::getInstance($stationOldest['id']);

        $tweet[]        = '- Oldest station to update: "' . $stationOldest->getName()
                          . '" in "' . $stationOldest->getSystem()->getName() . '", '
                          . \Zend_Locale_Format::toNumber(round((strtotime($yesterdayDate) - strtotime($stationOldest->getUpdateTime())) / (60 * 60 * 24))) . ' days ago...';

        if(self::$sendCompleteStats === false)
        {
            $tweet[]        = 'https://www.edsm.net/en/system/stations/id/' . $stationOldest->getSystem()->getId() . '/name/' . urlencode($stationOldest->getSystem()->getName()) . '/details/idS/' . $stationOldest->getId() . '/nameS/' . urlencode($stationOldest->getName());
        }

        // Set oldest ID in cache so we can use it on EDDN to resend another tweet when oldest is updated!
        $cacheKey               = sha1('\Process\Elite\Market::$oldestStationId');
        static::getDatabaseFileCache()->save($stationOldest->getId(), $cacheKey);

        // Send tweet if it's ok from main process
        if(static::$sendTweet === true)
        {
            $return = \EDSM_Api_Tweet::status(implode(PHP_EOL, $tweet));
        }

        unset($stationsModel, $tweet);

        return;
    }
}