<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Journal;

use         Process\Process;

class ApproachSettlement extends Process
{
    static protected $limit         = 10;

    static public function run()
    {
        $journalModels      = new \Models_Journal;
        $journalEntries     = self::getEntries();

        foreach($journalEntries AS $entry)
        {
            $entry['message'] = \Zend_Json::decode($entry['message']);

            // Find current station
            $stationsModel  = new \Models_Stations;
            $station        = $stationsModel->getByMarketId($entry['message']['MarketID']);

            if(!is_null($station))
            {
                $station            = \EDSM_System_Station::getInstance($station['id']);
                $haveCoordinates    = $station->getCoordinates();

                if(is_null($haveCoordinates))
                {
                    // Find all entries with the same MarketId
                    $selectOthers = $journalModels->select()
                                                  ->where('event = ?', 'ApproachSettlement')
                                                  ->where('message LIKE \'%"MarketID":?%\'', $entry['message']['MarketID']);
                    $results            = $journalModels->fetchAll($selectOthers);

                    if(count($results) > 0)
                    {
                        $results            = $results->toArray();
                        $tempCoordinates    = array();

                        foreach($results AS $result)
                        {
                            $result['message'] = \Zend_Json::decode($result['message']);

                            if($result['message']['MarketID'] == $entry['message']['MarketID'])
                            {
                                if(array_key_exists('Latitude', $entry['message']) && array_key_exists('Longitude', $entry['message']))
                                {
                                    $tempCoordinates[]  = array(
                                        'bodyLatitude'      => $entry['message']['Latitude'],
                                        'bodyLongitude'     => $entry['message']['Longitude'],
                                    );
                                }
                            }
                        }

                        if(count($tempCoordinates) >= 50)
                        {
                            $tempCoordinates    = static::getCenter($tempCoordinates);

                            $stationsModel->updateById($station->getId(), $tempCoordinates);

                            foreach($results AS $result)
                            {
                                $journalModels->deleteByRefUserEventAndDateEvent($result['refUser'], $result['event'], $result['dateEvent']);
                            }

                            static::log('<span class="text-info">' . str_replace('Process\\', '', static::class) . ':</span> Saved ' . $station->getName() . ' (#' . $station->getId() . ') coordinates');
                        }
                    }
                }
            }
        }

        unset($journalModels, $journalEntries);

        return;
    }

    private static function getCenter($coords)
    {
        $count_coords = count($coords);
        $xcos=0.0;
        $ycos=0.0;
        $zsin=0.0;

        foreach($coords as $lnglat)
        {
            $lat = $lnglat['bodyLatitude'] * pi() / 180;
            $lon = $lnglat['bodyLongitude'] * pi() / 180;

            $acos = cos($lat) * cos($lon);
            $bcos = cos($lat) * sin($lon);
            $csin = sin($lat);
            $xcos += $acos;
            $ycos += $bcos;
            $zsin += $csin;
        }

        $xcos /= $count_coords;
        $ycos /= $count_coords;
        $zsin /= $count_coords;
        $lon = atan2($ycos, $xcos);
        $sqrt = sqrt($xcos * $xcos + $ycos * $ycos);
        $lat = atan2($zsin, $sqrt);

        return array(
            'bodyLatitude'  => round($lat * 180 / pi(), 6),
            'bodyLongitude' => round($lon * 180 / pi(), 6),
        );
    }

    private static function getEntries()
    {
        $journalModels      = new \Models_Journal;
        $journalEntries     = $journalModels->select()
                                            ->limit(static::$limit)
                                            ->where('event = ?', 'ApproachSettlement')
                                            ->order('RAND()');

        $results            = $journalModels->fetchAll($journalEntries);

        if(!is_null($results) && count($results) > 0)
        {
            return $results->toArray();
        }

        return array();
    }
}