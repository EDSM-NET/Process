<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Record;

use         Process\Process;

class Largest extends Process
{
    static private $cacheKey = 'Statistics_BodiesController_Largest%GROUPNAME%_%TYPE%';
    static private $exclude     = array(
        1 => array( // Stars
            91,     // Neutron stars
        ),
        2 => array( // Planets

        ),
    );

    static public function run()
    {
        list($group, $type) = func_get_args();

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
            static::log('<span class="text-info">Record\Largest:</span> <span class="text-danger">Unknown group ' . $group . '</span>');
            return;
        }

        if(is_null($typeName))
        {
            static::log('<span class="text-info">Record\Largest:</span> <span class="text-danger">Unknown type ' . $type . '</span>');
            return;
        }

        if(in_array($type, static::$exclude[$group]))
        {
            return;
        }

        // Make record query
        $result         = array();
        $elasticClient  = \Process\Body\Elastic::getClient();
        $elasticResults = $elasticClient->search([
            'index'     => $elasticIndex,
            'type'      => '_doc',
            'body'      => [
                'size'          => 3,
                'from'          => 0,
                'stored_fields' => [],
                '_source'       => ['bodyId'],
                'query'         => [
                    'bool' => [
                        'filter'        => [
                            array('term' => ['subType' => (int) $type]),
                        ]
                    ]
                ],
                'sort'          => ['radius' => ['order' => 'desc']]
            ]
        ]);

        if(is_array($elasticResults) && count($elasticResults['hits']['hits']) > 0)
        {
            foreach($elasticResults['hits']['hits'] AS $hit)
            {
                if(array_key_exists('bodyId', $hit['_source']))
                {
                    $result[] = array('id' => (int) $hit['_source']['bodyId']);
                }
                else
                {
                    $result[] = array('id' => (int) $hit['_id']);
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
                                array('type' => 'largest' . $groupName . '_' . $type, 'bodyId' => $body->getId())
                            );
                        }
                    }
                }

                unset($body, $bodyFirstScannedBy);
            }
        }

        static::log('<span class="text-info">Record\Largest:</span> ' . $groupName . ' ' . $typeName);

        unset($group, $type, $groupName, $typeName);
        unset($result);

        return;
    }

    static public function getName()
    {
        return 'RECORD\Largest %1$s';
    }

    // Fake function for getText
    static private function ___translate___()
    {
        _('RECORD\Largest %1$s');
    }
}