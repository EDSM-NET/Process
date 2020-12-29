<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Record;

use         Process\Process;

class Lightest extends Process
{
    static private $cacheKey = 'Statistics_BodiesController_Lightest%GROUPNAME%_%TYPE%';

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
            static::log('<span class="text-info">Record\Lightest:</span> <span class="text-danger">Unknown group ' . $group . '</span>');
            return;
        }

        if(is_null($typeName))
        {
            static::log('<span class="text-info">Record\Lightest:</span> <span class="text-danger">Unknown type ' . $type . '</span>');
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
                '_source'       => ['bodyId', 'mass'],
                'query'         => [
                    'bool' => [
                        'filter'        => [
                            array('term' => ['subType' => (int) $type]),
                            array('range' => ['mass' => ['gt' => 0]]),
                        ]
                    ]
                ],
                'sort'          => ['mass' => ['order' => 'asc']]
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

        if(APPLICATION_DEBUG === true)
        {
            \Zend_Debug::dump($elasticResults['hits']['hits']);
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
                                array('type' => 'lightest' . $groupName . '_' . $type, 'bodyId' => $body->getId())
                            );
                        }
                    }
                }

                unset($body, $bodyFirstScannedBy);
            }
        }

        static::log('<span class="text-info">Record\Lightest:</span> ' . $groupName . ' ' . $typeName);

        unset($group, $type, $groupName, $typeName);
        unset($result);

        return;
    }

    static public function getName()
    {
        return 'RECORD\Lightest %1$s';
    }

    // Fake function for getText
    static private function ___translate___()
    {
        _('RECORD\Lightest %1$s');
    }
}