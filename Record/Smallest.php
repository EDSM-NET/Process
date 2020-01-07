<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Record;

use         Process\Process;

class Smallest extends Process
{
    static private $cacheKey = 'Statistics_BodiesController_Smallest%GROUPNAME%_%TYPE%';
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
            $groupName  = 'Star';
            $typeName   = \Alias\Body\Star\Type::get($type);
        }
        elseif($group == 2)
        {
            $groupName = 'Planet';
            $typeName   = \Alias\Body\Planet\Type::get($type);
        }
        else
        {
            static::log('<span class="text-info">Record\Smallest:</span> <span class="text-danger">Unknown group ' . $group . '</span>');
            return;
        }

        if(is_null($typeName))
        {
            static::log('<span class="text-info">Record\Smallest:</span> <span class="text-danger">Unknown type ' . $type . '</span>');
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
            'index'     => \Process\Body\Elastic::$elasticConfig->bodyIndex,
            'type'      => '_doc',
            'body'      => [
                'size'          => 3,
                'from'          => 0,
                'stored_fields' => [],
                '_source'       => ['bodyId'],
                'query'         => [
                    'bool' => [
                        'filter'        => [
                            array('term' => ['mainType' => (int) $group]),
                            array('term' => ['subType' => (int) $type]),
                            array('range' => ['radius' => ['gt' => 0]]),
                        ]
                    ]
                ],
                'sort'          => ['radius' => ['order' => 'asc']]
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

                if(!is_null($bodyFirstScannedBy) && $bodyFirstScannedBy instanceof \Component\User)
                {
                    $bodyFirstScannedBy->giveBadge(
                        7800,
                        array('type' => 'smallest' . $groupName . '_' . $type, 'bodyId' => $body->getId())
                    );
                }

                unset($body, $bodyFirstScannedBy);
            }
        }

        static::log('<span class="text-info">Record\Smallest:</span> ' . $groupName . ' ' . $typeName);

        unset($group, $type, $groupName, $typeName);
        unset($result);

        return;
    }

    static public function getName()
    {
        return 'RECORD\Smallest %1$s';
    }

    // Fake function for getText
    static private function ___translate___()
    {
        _('RECORD\Smallest %1$s');
    }
}