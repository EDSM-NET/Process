<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Record;

use         Process\Process;

class FarthestSystemColoniaVisited extends Process
{
    static private $cacheKey = 'Statistics_SystemsController_FarthestSystemColoniaVisited';

    static public function run()
    {
        // Make record query
        $result         = array();
        $sortSystem     = \Component\System::getInstance(3384966);
        $elasticClient  = \Process\Body\Elastic::getClient();
        $elasticResults = $elasticClient->search([
            'index'     => \Process\Body\Elastic::$elasticConfig->bodyIndex,
            'type'      => '_doc',
            'body'      => [
                'size'          => 3,
                'from'          => 0,
                'stored_fields' => [],
                '_source'       => ['bodyId', 'systemName'],
                /*
                'query'         => [
                    'bool' => [
                        'filter'        => [
                            array('term' => ['mainType' => (int) $group]),
                            array('term' => ['subType' => (int) $type]),
                        ]
                    ]
                ],
                */
                'collapse'      => ['field' => 'systemName'],
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
            $systemsModel               = new \Models_Systems;

            foreach($elasticResults['hits']['hits'] AS $hit)
            {
                $systemId = $systemsModel->getByName($hit['_source']['systemName']);

                if(array_key_exists('bodyId', $hit['_source']))
                {
                    $result[] = array('id' => $systemId['id'], 'calculatedDistance' => (float) sqrt($hit['sort'][0]) * 100);
                }
                else
                {
                    $result[] = array('id' => $systemId['id'], 'calculatedDistance' => (float) sqrt($hit['sort'][0]) * 100);
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
                        array('type' => 'farthestSystemColoniaVisited', 'systemId' => $system->getId())
                    );
                }

                unset($system, $firstDiscoveredBy);
            }
        }

        static::log('<span class="text-info">Record\FarthestSystemColoniaVisited</span>');

        $systemsModel->getAdapter()->closeConnection();
        unset($systemsModel);
        unset($result);

        return;
    }

    static public function getName()
    {
        return 'RECORD\Farthest %1$s from Colonia';
    }

    // Fake function for getText
    static private function ___translate___()
    {
        _('RECORD\Farthest %1$s from Colonia');
    }
}