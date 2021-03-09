<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\System;

use         Process\Process;

class Elastic extends Process
{
    static protected $limit                         = 25000;
    static protected $elasticClient                 = false;
    static public $elasticConfig                    = false;

    static protected $systemsModel                  = null;
    static protected $systemsBodiesModel            = null;
    static protected $systemsBodiesCountModel       = null;
    static protected $systemsInElasticModel         = null;
    static protected $systemsInformationsModel      = null;

    public static function run($reset = false)
    {
        //self::reset();exit();
        if($reset === true)
        {
            return self::reset();
        }

        $limit                              = static::$limit;
        $elasticUpdate                      = 0;
        self::setupDatabaseModels();

        // Disable cache
        self::$systemsModel->disableCache();

        // Get Elastic client
        $client = self::getClient();
        $client->indices()->putSettings(['index' => static::$elasticConfig->systemIndex, 'body' => ['settings' => ['refresh_interval' => '3600s']]]);

        $select     = self::$systemsModel->select()
                        ->setIntegrityCheck(false)
                        ->from(
                            self::$systemsModel,
                            array(
                                                   self::$systemsModel->info('name') . '.*',
                                                   self::$systemsBodiesCountModel->info('name') . '.*',
                                'bodyCount'     => self::$systemsInformationsModel->info('name') . '.bodyCount',
                            )
                        )

                        ->joinLeft(self::$systemsInElasticModel->info('name'), self::$systemsModel->info('name') . '.id = ' . self::$systemsInElasticModel->info('name') . '.refSystem')
                        ->joinLeft(self::$systemsBodiesCountModel->info('name'), self::$systemsModel->info('name') . '.id = ' . self::$systemsBodiesCountModel->info('name') . '.refSystem')
                        ->joinLeft(self::$systemsInformationsModel->info('name'), self::$systemsModel->info('name') . '.id = ' . self::$systemsInformationsModel->info('name') . '.refSystem')
                        ->joinLeft(
                            self::$systemsBodiesModel->info('name'),
                            self::$systemsModel->info('name') . '.id = ' . self::$systemsBodiesModel->info('name') . '.refSystem AND ' . self::$systemsBodiesModel->info('name')  . '.`group` = 1 AND (' . self::$systemsBodiesModel->info('name')  . '.`distanceToArrival` = 0 OR ' . self::$systemsBodiesModel->info('name')  . '.`distanceToArrival` IS NULL)',
                            array(
                                'primaryStarId'     => self::$systemsBodiesModel->info('name') . '.id',
                                'primaryStarType'   => self::$systemsBodiesModel->info('name') . '.type',
                                'primaryStarName'   => self::$systemsBodiesModel->info('name') . '.name',
                            )
                        )

                        ->where(self::$systemsInElasticModel->info('name') . '.inElastic IS NULL')
                        ->where(self::$systemsModel->info('name') . '.x IS NOT NULL')
                        ->limit(static::$limit);

        $needToBeRefreshed      = self::$systemsModel->getAdapter()->fetchAll($select);

        if(count($needToBeRefreshed) > 0)
        {
            foreach($needToBeRefreshed AS $currentSystem)
            {
                $return = self::insertSystem($currentSystem['id'], $currentSystem);

                if($return === true)
                {
                    $elasticUpdate++;
                }
            }

            $client->indices()->putSettings(['index' => static::$elasticConfig->systemIndex, 'body' => ['settings' => ['refresh_interval' => '60s']]]);
            $client->indices()->refresh();
        }

        self::$systemsModel->enableCache();
        self::$systemsModel->getAdapter()->closeConnection();

        if($elasticUpdate > 0)
        {
            static::log('<span class="text-info">System\Elastic:</span> Updated ' . \Zend_Locale_Format::toNumber($elasticUpdate) . ' systems');

            if($elasticUpdate > 10)
            {
                return $elasticUpdate;
            }
        }

        return false; // Trigger background task sleep...
    }

    public static function insertSystem($currentSystemId, $currentSystemCache = null)
    {
        self::setupDatabaseModels();
        self::$systemsModel->disableCache();

        \Component\System::destroyInstance($currentSystemId);
        $currentSystem    = \Component\System::getInstance($currentSystemId, $currentSystemCache);

        self::deleteSystem($currentSystemId);
        $client         = self::getClient();

        // Generate elastic body
        $elasticBody    = [
            'systemId'                          => $currentSystemId,
            'systemName'                        => strtolower($currentSystem->getName()),

            'systemX'                           => $currentSystem->getX(),
            'systemY'                           => $currentSystem->getY(),
            'systemZ'                           => $currentSystem->getZ(),

            'isGreen'                           => $currentSystem->isGreen(),
            'bodyCount'                         => -1,

            'primaryStarId'                     => -1,
            'primaryStarType'                   => -1,
            'primaryStarName'                   => '',

            'updateTime'                        => $currentSystem->getUpdateTime(),
        ];

        if(!is_null($currentSystemCache) && array_key_exists('primaryStarId', $currentSystemCache) && $currentSystemCache['primaryStarId'] !== null)
        {
            $elasticBody['primaryStarId']       = $currentSystemCache['primaryStarId'];
            $elasticBody['primaryStarType']     = $currentSystemCache['primaryStarType'];
            $elasticBody['primaryStarName']     = $currentSystemCache['primaryStarName'];
        }

        if(!is_null($currentSystemCache) && array_key_exists('bodyCount', $currentSystemCache) && $currentSystemCache['bodyCount'] !== null)
        {
            $elasticBody['bodyCount']           = $currentSystemCache['bodyCount'];
        }

        // Insert a new version
        $response = $client->index(['index' => static::$elasticConfig->systemIndex, 'body' => $elasticBody]);

        // Check if it's ok
        if(is_array($response) && array_key_exists('result', $response) && $response['result'] === 'created')
        {
            try
            {
                self::$systemsInElasticModel->insert(['refSystem' => $currentSystem->getId(), 'inElastic' => 1]);
            }
            catch(\Zend_Db_Exception $ex){}

            self::$systemsModel->enableCache();
            return true;
        }

        self::$systemsModel->enableCache();
        return false;
    }

    public static function getSystemDocumentId($systemId)
    {
        $elasticClient = self::getClient();

        try
        {
            $response = $elasticClient->search([
                'index' => static::$elasticConfig->systemIndex,
                'body'      => [
                    'size'          => 1,
                    'stored_fields' => [],
                    '_source'       => ['bodyId'],
                    'query'         => ['bool' => ['filter' => ['term' => ['systemId' => (int) $systemId]]]]
                ]
            ]);

            if(is_array($response) && array_key_exists('hits', $response))
            {
                if($response['hits']['total']['value'] > 0 && count($response['hits']['hits']) > 0)
                {
                    return $response['hits']['hits'][0]['_id'];
                }
            }
        }
        catch(\Elasticsearch\Common\Exceptions\NoNodesAvailableException $ex){}
        catch(\Elasticsearch\Common\Exceptions\Missing404Exception $ex){}

        return null;
    }

    public static function deleteSystem($systemId)
    {
        $elasticClient      = self::getClient();
        $currentDocument    = self::getSystemDocumentId($systemId);

        if(!is_null($currentDocument))
        {
            try
            {
                $elasticClient->delete([
                    'index'     => static::$elasticConfig->systemIndex,
                    'id'        => $systemId
                ]);
            }
            catch(\Elasticsearch\Common\Exceptions\NoNodesAvailableException $ex){}
            catch(\Elasticsearch\Common\Exceptions\Missing404Exception $ex){}
        }
    }

    private static function setupDatabaseModels()
    {
        if(is_null(self::$systemsModel))
        {
            self::$systemsModel                 = new \Models_Systems;
            self::$systemsBodiesModel           = new \Models_Systems_Bodies;
            self::$systemsBodiesCountModel      = new \Models_Systems_Bodies_Count;
            self::$systemsInformationsModel     = new \Models_Systems_Informations;
            self::$systemsInElasticModel        = new \Models_Systems_InElastic;
        }
    }

    public static function getClient()
    {
        if(static::$elasticClient === false)
        {
            require_once LIBRARY_PATH . '/React/Promise/functions_include.php';

            static::$elasticConfig  = \Zend_Registry::get('appConfig')->elasticSearch;
            static::$elasticClient  = \Elasticsearch\ClientBuilder::create()
                                        ->setHosts([static::$elasticConfig->host])
                                        ->build();
        }

        return static::$elasticClient;
    }

    protected static function reset()
    {
        $client = self::getClient();

        try
        {
            $client->indices()->delete(['index' => static::$elasticConfig->systemIndex]);
        }
        catch(\Elasticsearch\Common\Exceptions\Missing404Exception $ex){}

        $client->indices()->create([
            'index' => static::$elasticConfig->systemIndex,
            'body'  => [
                'settings'  => [
                    'refresh_interval'  => '600s',
                    'analysis'          => [
                        'filter'            => [
                            'autocomplete_filter' => [
                                'type'              => 'edge_ngram',
                                'min_gram'          => 1,
                                'max_gram'          => 20,
                            ],
                        ],
                        'analyzer'          => [
                            'autocomplete'      => [
                                'type'              => 'custom',
                                'tokenizer'         => 'standard',
                                'filter'            => ['autocomplete_filter'],
                            ]
                        ]
                    ]
                ],
                'mappings'  => [
                    'properties'    => [
                        'systemId'              => ['type' => 'integer'],
                        'systemName'            => [
                            'type'                  => 'keyword',
                            'fields'                => [
                                'keywordAutoComplete'   => ['type' => 'text', 'analyzer' => 'autocomplete', 'search_analyzer' => 'standard'],
                            ],
                        ],

                        'systemX'               => ['type' => 'double'],
                        'systemY'               => ['type' => 'double'],
                        'systemZ'               => ['type' => 'double'],

                        'isGreen'               => ['type' => 'boolean'],
                        'bodyCount'             => ['type' => 'short', 'null_value' => -1],

                        'primaryStarId'         => ['type' => 'integer', 'null_value' => -1],
                        'primaryStarType'       => ['type' => 'short', 'null_value' => -1],

                        'updateTime'            => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss'],
                    ]
                ]
            ]
        ]);

        $systemsInElasticModel = new \Models_Systems_InElastic;
        $systemsInElasticModel->getAdapter()->query('TRUNCATE TABLE ' . $systemsInElasticModel->info('name'));
    }
}