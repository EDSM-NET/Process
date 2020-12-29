<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Record;

use         Process\Process;

class FarthestSystemSolVisited extends Process
{
    static private $cacheKey = 'Statistics_SystemsController_FarthestSystemSolVisited';

    static public function run()
    {
        // Make record query
        $result         = array();
        $sortSystem     = \Component\System::getInstance(27);
        $elasticClient  = \Process\Body\Elastic::getClient();
        $elasticResults = $elasticClient->search([
            'index'     => \Process\Body\Elastic::$elasticConfig->systemIndex,
            'body'      => [
                'size'          => 3,
                'from'          => 0,
                'stored_fields' => [],
                '_source'       => ['systemId'],
                'sort'          => ['_script' => [
                    'type'          => 'number',
                    'order'         => 'desc',
                    'script'        => [
                        'lang'          => 'painless',
                        'source'        => 'double x = (doc["systemX"].value / 32 - params.sortSystemX);double y = (doc["systemY"].value / 32 - params.sortSystemY);double z = (doc["systemZ"].value / 32 - params.sortSystemZ);double distance = (x*x+y*y+z*z);return distance;',
                        'params'        => [
                            'sortSystemX'   => $sortSystem->getX() / 32,
                            'sortSystemY'   => $sortSystem->getY() / 32,
                            'sortSystemZ'   => $sortSystem->getZ() / 32,
                        ]
                    ]
                ]]
            ]
        ]);

        if(is_array($elasticResults) && count($elasticResults['hits']['hits']) > 0)
        {
            foreach($elasticResults['hits']['hits'] AS $hit)
            {
                if(array_key_exists('systemId', $hit['_source']))
                {
                    $result[] = array('id' => $hit['_source']['systemId'], 'calculatedDistance' => (float) sqrt($hit['sort'][0]) * 100);
                }
            }
        }

        if(count($result) > 0)
        {
            static::getDatabaseFileCache()->save($result[0], static::$cacheKey);

            // Give badge to all retroactive users
            foreach($result AS $record)
            {
                $system             = \Component\System::getInstance($record['id']);
                $firstDiscoveredBy  = $system->getFirstDiscoveredBy();

                if(!is_null($firstDiscoveredBy))
                {
                    $firstDiscoveredBy = \Component\User::getInstance($firstDiscoveredBy['user']);
                    $firstDiscoveredBy->giveBadge(
                        7800,
                        array('type' => 'farthestSystemSolVisited', 'systemId' => $system->getId())
                    );
                }

                unset($system, $firstDiscoveredBy);
            }
        }

        static::log('<span class="text-info">Record\FarthestSystemSolVisited</span>');
        unset($result);

        return;
    }

    static public function getName()
    {
        return 'RECORD\Farthest %1$s from Sol';
    }

    // Fake function for getText
    static private function ___translate___()
    {
        _('RECORD\Farthest %1$s from Sol');
    }
}