<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Record;

use         Process\Process;

class Lowest extends Process
{
    static private $cacheKey = 'Statistics_BodiesController_Lowest%GROUPNAME%_%TYPE%';

    static public function run()
    {
        list($group, $type) = func_get_args();
        $elasticClient      = \Process\Body\Elastic::getClient();

        if($group == 1)
        {
            $elasticIndex   = \Process\Body\Elastic::$elasticConfig->bodyStarIndex;
            $groupName      = 'Star';
            $typeName       = \Alias\Body\Star\Type::get($type);
        }
        elseif($group == 2)
        {
            $elasticIndex   = \Process\Body\Elastic::$elasticConfig->bodyPlanetIndex;
            $groupName      = 'Planet';
            $typeName       = \Alias\Body\Planet\Type::get($type);
        }
        else
        {
            static::log('<span class="text-info">Record\Lowest:</span> <span class="text-danger">Unknown group ' . $group . '</span>');
            return;
        }

        if(is_null($typeName))
        {
            static::log('<span class="text-info">Record\Lowest:</span> <span class="text-danger">Unknown type ' . $type . '</span>');
            return;
        }

        // Make record query
        $result         = array();
        $elasticResults = $elasticClient->search([
            'index'     => $elasticIndex,
            'body'      => [
                'size'          => 3,
                'from'          => 0,
                'stored_fields' => [],
                '_source'       => ['bodyId', 'systemY'],
                'query'         => [
                    'bool' => [
                        'filter'        => [
                            array('term' => ['subType' => (int) $type]),
                        ]
                    ]
                ],
                'sort'          => ['systemY' => ['order' => 'asc']]
            ]
        ]);

        if(is_array($elasticResults) && count($elasticResults['hits']['hits']) > 0)
        {
            foreach($elasticResults['hits']['hits'] AS $hit)
            {
                if(array_key_exists('bodyId', $hit['_source']))
                {
                    $result[] = array('id' => (int) $hit['_source']['bodyId'], 'y' => (int) $hit['_source']['systemY']);
                }
                else
                {
                    $result[] = array('id' => (int) $hit['_id'], 'y' => (int) $hit['_source']['systemY']);
                }
            }
        }

        if(count($result) > 0)
        {
            $cacheKey   = str_replace(
                array('%GROUPNAME%', '%TYPE%'),
                array($groupName, $type),
                static::$cacheKey
            );
            static::getDatabaseFileCache()->save($result[0], $cacheKey);

            // Give badge to all retroactive users
            foreach($result AS $record)
            {
                $body               = \EDSM_System_Body::getInstance($record['id']);
                $bodyFirstScannedBy = $body->getFirstScannedBy();

                if(!is_null($bodyFirstScannedBy))
                {
                    foreach($bodyFirstScannedBy AS $firstScannedBy)
                    {
                        if($firstScannedBy instanceof \Component\User)
                        {
                            $firstScannedBy->giveBadge(
                                7800,
                                array('type' => 'lowest' . $groupName . '_' . $type, 'bodyId' => $body->getId())
                            );
                        }
                    }
                }

                unset($body, $bodyFirstScannedBy);
            }
        }

        static::log('<span class="text-info">Record\Lowest:</span> ' . $groupName . ' ' . $typeName);

        unset($group, $type, $groupName, $typeName);
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