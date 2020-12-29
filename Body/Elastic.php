<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Body;

use         Process\Process;

class Elastic extends Process
{
    static protected $limit                         = 25000;
    static protected $elasticClient                 = false;
    static public $elasticConfig                    = false;

    static protected $systemsBodiesModel            = null;
    static protected $systemsBodiesOrbitalModel     = null;
    static protected $systemsBodiesSurfaceModel     = null;
    static protected $systemsBodiesParentsModel     = null;
    static protected $systemsBodiesUsersModel       = null;
    static protected $systemsBodiesUsersSAAModel    = null;
    static protected $systemsBodiesInElasticModel   = null;

    public static function run($reset = false)
    {
        //self::reset();exit();
        if($reset === true)
        {
            return self::reset();
        }

        $elasticUpdate = 0;
        self::setupDatabaseModels();

        // Disable cache
        self::$systemsBodiesModel->disableCache();

        // Get Elastic client
        $client = self::getClient();
        $client->indices()->putSettings(['index' => static::$elasticConfig->bodyStarIndex, 'body' => ['settings' => ['refresh_interval' => '3600s']]]);
        $client->indices()->putSettings(['index' => static::$elasticConfig->bodyPlanetIndex, 'body' => ['settings' => ['refresh_interval' => '3600s']]]);

        $select     = self::$systemsBodiesModel->select()
                        ->setIntegrityCheck(false)
                        ->from(
                            self::$systemsBodiesModel,
                            array(
                                self::$systemsBodiesModel->info('name') . '.*',
                                self::$systemsBodiesOrbitalModel->info('name') . '.*',
                                self::$systemsBodiesSurfaceModel->info('name') . '.*',
                                self::$systemsBodiesParentsModel->info('name') . '.*',
                            )
                        )

                        ->joinLeft(self::$systemsBodiesInElasticModel->info('name'), self::$systemsBodiesModel->info('name') . '.id = ' . self::$systemsBodiesInElasticModel->info('name') . '.refBody')
                        ->joinLeft(self::$systemsBodiesOrbitalModel->info('name'), self::$systemsBodiesModel->info('name') . '.id = ' . self::$systemsBodiesOrbitalModel->info('name') . '.refBody')
                        ->joinLeft(self::$systemsBodiesSurfaceModel->info('name'), self::$systemsBodiesModel->info('name') . '.id = ' . self::$systemsBodiesSurfaceModel->info('name') . '.refBody')
                        ->joinLeft(self::$systemsBodiesParentsModel->info('name'), self::$systemsBodiesModel->info('name') . '.id = ' . self::$systemsBodiesParentsModel->info('name') . '.refBody')

                        ->where(self::$systemsBodiesInElasticModel->info('name') . '.inElastic IS NULL')
                        ->limit(static::$limit);

        $needToBeRefreshed      = self::$systemsBodiesModel->getAdapter()->fetchAll($select);

        if(count($needToBeRefreshed) > 0)
        {
            foreach($needToBeRefreshed AS $currentBody)
            {
                $return = self::insertBody($currentBody['id'], $currentBody);

                if($return === true)
                {
                    $elasticUpdate++;
                }
            }

            $client->indices()->putSettings(['index' => static::$elasticConfig->bodyStarIndex, 'body' => ['settings' => ['refresh_interval' => '60s']]]);
            $client->indices()->putSettings(['index' => static::$elasticConfig->bodyPlanetIndex, 'body' => ['settings' => ['refresh_interval' => '60s']]]);
            $client->indices()->refresh();
        }

        self::$systemsBodiesModel->enableCache();
        self::$systemsBodiesModel->getAdapter()->closeConnection();

        if($elasticUpdate > 0)
        {
            static::log('<span class="text-info">Body\Elastic:</span> Updated ' . \Zend_Locale_Format::toNumber($elasticUpdate) . ' celestial bodies');

            if($elasticUpdate > 10)
            {
                return $elasticUpdate;
            }
        }

        return false; // Trigger background task sleep...
    }

    public static function insertBody($currentBodyId, $currentBodyCache = null)
    {
        self::setupDatabaseModels();
        self::$systemsBodiesModel->disableCache();

        \EDSM_System_Body::destroyInstance($currentBodyId);
        $currentBody = \EDSM_System_Body::getInstance($currentBodyId, $currentBodyCache);

        if(!is_null($currentBody->getType()))
        {
            self::deleteBody($currentBodyId, $currentBody->getMainType());

            $currentSystem  = $currentBody->getSystem();

            // System don't exists, delete the body...
            if(is_null($currentSystem))
            {
                self::$systemsBodiesModel->deleteById($currentBodyId);
            }
            elseif($currentBody->getMainType() !== null)
            {
                $client         = self::getClient();

                // Generate elastic body
                $elasticIndex   = ($currentBody->getMainType() === 'Star') ? static::$elasticConfig->bodyStarIndex : static::$elasticConfig->bodyPlanetIndex;
                $elasticBody    = [
                    'bodyId'                            => $currentBodyId,
                    'bodyName'                          => strtolower($currentBody->getName()),
                    'systemName'                        => strtolower($currentSystem->getName()),

                    'systemX'                           => $currentSystem->getX(),
                    'systemY'                           => $currentSystem->getY(),
                    'systemZ'                           => $currentSystem->getZ(),

                    'subType'                           => $currentBody->getType(),
                    'distanceToArrival'                 => $currentBody->getDistanceToArrival(),

                    'isLandable'                        => ( ($currentBody->getMainType() == 'Star') ? $currentBody->isScoopable() : $currentBody->isLandable() ),

                    'age'                               => $currentBody->getAge(),
                    'spectralClass'                     => $currentBody->getSpectralClass(),
                    'luminosity'                        => $currentBody->getLuminosity(),
                    'absoluteMagnitude'                 => $currentBody->getAbsoluteMagnitude(),

                    'haveBeltsOrRings'                  => false,

                    'mass'                              => $currentBody->getMass(),
                    'radius'                            => $currentBody->getRadius(),

                    'surfaceTemperature'                => $currentBody->getSurfaceTemperature(),
                    'surfacePressure'                   => $currentBody->getSurfacePressure(),

                    'orbitalPeriod'                     => $currentBody->getOrbitalPeriod(),
                    'semiMajorAxis'                     => $currentBody->getSemiMajorAxis(),
                    'orbitalEccentricity'               => $currentBody->getOrbitalEccentricity(),
                    'orbitalInclination'                => $currentBody->getOrbitalInclination(),
                    'argOfPeriapsis'                    => $currentBody->getArgOfPeriapsis(),
                    'rotationalPeriod'                  => $currentBody->getRotationalPeriod(),
                    'rotationalPeriodTidallyLocked'     => $currentBody->getRotationalPeriodTidallyLocked(),
                    'axialTilt'                         => $currentBody->getAxisTilt(),
                ];

                if($currentBody->getMainType() === 'Star')
                {
                    $elasticBody['isMainStar']  = $currentBody->isMainStar();
                }
                if($currentBody->getMainType() === 'Planet')
                {
                    $elasticBody['gravity']             = $currentBody->getGravity();

                    $elasticBody['terraformingState']   = $currentBody->getTerraformState();
                    $elasticBody['atmosphereType']      = $currentBody->getAtmosphere();
                    $elasticBody['volcanismType']       = $currentBody->getVolcanism();
                    $elasticBody['reserveLevel']        = $currentBody->getReserveLevel();
                }

                // Belts?
                if($currentBody->getMainType() === 'Star')
                {
                    $haveBelts = $currentBody->getBelts();

                    if(!is_null($haveBelts) && count($haveBelts) > 0)
                    {
                        $elasticBody['haveBeltsOrRings'] = true;
                    }
                }

                // Rings?
                if($currentBody->getMainType() === 'Planet')
                {
                    $haveRings = $currentBody->getRings();

                    if(!is_null($haveRings) && count($haveRings) > 0)
                    {
                        $elasticBody['haveBeltsOrRings'] = true;
                    }
                }

                // Materials
                if($currentBody->getMainType() === 'Planet')
                {
                    $currentBodyMaterials   = $currentBody->getMaterials(true);
                    if(!is_null($currentBodyMaterials) && count($currentBodyMaterials) > 0)
                    {
                        $elasticBody['materials'] = array();

                        foreach($currentBodyMaterials AS $id => $currentMaterial)
                        {
                            $elasticBody['materials'][] = array('id' => $id, 'value' => $currentMaterial['value']);
                        }
                    }
                }

                // Atmosphere composition
                if($currentBody->getMainType() === 'Planet')
                {
                    $currentBodyAtmosphereComposition   = $currentBody->getAtmosphereComposition();
                    if(!is_null($currentBodyAtmosphereComposition) && count($currentBodyAtmosphereComposition) > 0)
                    {
                        $elasticBody['atmosphereComposition'] = array();

                        foreach($currentBodyAtmosphereComposition AS $id => $currentComposition)
                        {
                            $elasticBody['atmosphereComposition'][] = array('id' => $id, 'value' => $currentComposition);
                        }
                    }
                }

                // Solid composition
                if($currentBody->getMainType() === 'Planet')
                {
                    $currentBodySolidComposition   = $currentBody->getSolidComposition();
                    if(!is_null($currentBodySolidComposition) && count($currentBodySolidComposition) > 0)
                    {
                        $elasticBody['solidComposition'] = array();

                        foreach($currentBodySolidComposition AS $id => $currentComposition)
                        {
                            $elasticBody['solidComposition'][] = array('id' => $id, 'value' => $currentComposition);
                        }
                    }
                }

                // Scanning
                $firstScanned   = $currentBody->getFirstScannedBy();
                $haveScanned    = array();
                if(!is_null($firstScanned))
                {
                    $elasticBody['firstScanned']    = (int) $firstScanned[0]->getId(); //TODO: Handle multiple?
                    $haveScanned[]                  = $elasticBody['firstScanned'];
                }

                $usersScans = self::$systemsBodiesUsersModel->getByRefBody($currentBody->getId());
                if(!is_null($usersScans) && count($usersScans) > 0)
                {
                    foreach($usersScans AS $userScan)
                    {
                        if(!in_array((int) $userScan['refUser'], $haveScanned))
                        {
                            $haveScanned[] = (int) $userScan['refUser'];
                        }
                    }
                }

                if(count($haveScanned) > 0)
                {
                    $elasticBody['haveScanned'] = $haveScanned;
                }

                // Mapping
                $firstMapped    = $currentBody->getFirstMappedBy();
                $haveMapped     = array();
                if(!is_null($firstMapped))
                {
                    $elasticBody['firstMapped']     = (int) $firstMapped->getId();
                    $haveMapped[]                   = $elasticBody['firstMapped'];
                }

                $usersMaps = self::$systemsBodiesUsersSAAModel->getByRefBody($currentBody->getId());
                if(!is_null($usersMaps) && count($usersMaps) > 0)
                {
                    foreach($usersMaps AS $userMap)
                    {
                        if(!in_array((int) $userMap['refUser'], $haveMapped))
                        {
                            $haveMapped[] = (int) $userMap['refUser'];
                        }
                    }
                }

                if(count($haveMapped) > 0)
                {
                    $elasticBody['haveMapped'] = $haveMapped;
                }

                // Insert a new version
                $response = $client->index(['index' => $elasticIndex, 'body' => $elasticBody]);

                // Check if it's ok
                if(is_array($response) && array_key_exists('result', $response) && $response['result'] === 'created')
                {
                    try
                    {
                        self::$systemsBodiesInElasticModel->insert(['refBody' => $currentBody->getId(), 'inElastic' => 1]);
                    }
                    catch(\Zend_Db_Exception $ex){}

                    self::$systemsBodiesModel->enableCache();
                    return true;
                }
            }
        }

        self::$systemsBodiesModel->enableCache();
        return false;
    }

    public static function getBodyDocumentId($bodyId, $bodyType = null)
    {
        $elasticClient = self::getClient();

        if(is_null($bodyType) || $bodyType === 1 || $bodyType === 'Star')
        {
            try
            {
                $response = $elasticClient->search([
                    'index' => static::$elasticConfig->bodyStarIndex,
                    'body'      => [
                        'size'          => 1,
                        'stored_fields' => [],
                        '_source'       => ['bodyId'],
                        'query'         => ['bool' => ['filter' => ['term' => ['bodyId' => (int) $bodyId]]]]
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
        }

        if(is_null($bodyType) || $bodyType === 2 || $bodyType === 'Planet')
        {
            try
            {
                $response = $elasticClient->search([
                    'index' => static::$elasticConfig->bodyPlanetIndex,
                    'body'      => [
                        'size'          => 1,
                        'stored_fields' => [],
                        '_source'       => ['bodyId'],
                        'query'         => ['bool' => ['filter' => ['term' => ['bodyId' => (int) $bodyId]]]]
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
        }

        return null;
    }

    public static function deleteBody($bodyId, $bodyType = null)
    {
        $elasticClient      = self::getClient();
        $currentDocument    = self::getBodyDocumentId($bodyId, $bodyType);

        if(!is_null($currentDocument))
        {
            if(is_null($bodyType) || $bodyType === 1 || $bodyType === 'Star')
            {
                try
                {
                    $elasticClient->delete([
                        'index'     => static::$elasticConfig->bodyStarIndex,
                        'id'        => $bodyId
                    ]);
                }
                catch(\Elasticsearch\Common\Exceptions\NoNodesAvailableException $ex){}
                catch(\Elasticsearch\Common\Exceptions\Missing404Exception $ex){}
            }

            if(is_null($bodyType) || $bodyType === 2 || $bodyType === 'Planet')
            {
                try
                {
                    $elasticClient->delete([
                        'index'     => static::$elasticConfig->bodyPlanetIndex,
                        'id'        => $bodyId
                    ]);
                }
                catch(\Elasticsearch\Common\Exceptions\NoNodesAvailableException $ex){}
                catch(\Elasticsearch\Common\Exceptions\Missing404Exception $ex){}
            }
        }
    }

    private static function setupDatabaseModels()
    {
        if(is_null(self::$systemsBodiesModel))
        {
            self::$systemsBodiesModel           = new \Models_Systems_Bodies;
            self::$systemsBodiesOrbitalModel    = new \Models_Systems_Bodies_Orbital;
            self::$systemsBodiesSurfaceModel    = new \Models_Systems_Bodies_Surface;
            self::$systemsBodiesParentsModel    = new \Models_Systems_Bodies_Parents;
            self::$systemsBodiesUsersModel      = new \Models_Systems_Bodies_Users;
            self::$systemsBodiesUsersSAAModel   = new \Models_Systems_Bodies_UsersSAA;
            self::$systemsBodiesInElasticModel  = new \Models_Systems_Bodies_InElastic;
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
            $client->indices()->delete(['index' => static::$elasticConfig->bodyStarIndex]);
        }
        catch(\Elasticsearch\Common\Exceptions\Missing404Exception $ex){}
        try
        {
            $client->indices()->delete(['index' => static::$elasticConfig->bodyPlanetIndex]);
        }
        catch(\Elasticsearch\Common\Exceptions\Missing404Exception $ex){}

        $client->indices()->create([
            'index' => static::$elasticConfig->bodyStarIndex,
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
                        'bodyId'                => ['type' => 'integer'],
                        'bodyName'              => [
                            'type'                  => 'keyword',
                            'fields'                => [
                                'keywordAutoComplete'   => ['type' => 'text', 'analyzer' => 'autocomplete', 'search_analyzer' => 'standard'],
                            ],
                        ],
                        'systemName'            => [
                            'type'                  => 'keyword',
                            'fields'                => [
                                'keywordAutoComplete'   => ['type' => 'text', 'analyzer' => 'autocomplete', 'search_analyzer' => 'standard'],
                            ],
                        ],

                        'systemX'               => ['type' => 'double'],
                        'systemY'               => ['type' => 'double'],
                        'systemZ'               => ['type' => 'double'],

                        'subType'               => ['type' => 'short'],
                        'distanceToArrival'     => ['type' => 'integer'],

                        'isMainStar'            => ['type' => 'boolean'],
                        'isLandable'            => ['type' => 'boolean'],
                        'haveBeltsOrRings'      => ['type' => 'boolean'],

                        'age'                   => ['type' => 'integer'],
                        'absoluteMagnitude'     => ['type' => 'double'],

                        'mass'                  => ['type' => 'double'],
                        'radius'                => ['type' => 'double'],

                        'surfaceTemperature'                => ['type' => 'double'],
                        'surfacePressure'                   => ['type' => 'double'],

                        'orbitalPeriod'                     => ['type' => 'double'],
                        'semiMajorAxis'                     => ['type' => 'double'],
                        'orbitalEccentricity'               => ['type' => 'double'],
                        'orbitalInclination'                => ['type' => 'double'],
                        'argOfPeriapsis'                    => ['type' => 'double'],
                        'rotationalPeriod'                  => ['type' => 'double'],
                        'rotationalPeriodTidallyLocked'     => ['type' => 'boolean'],
                        'axialTilt'                         => ['type' => 'double'],

                        'firstScanned'          => ['type' => 'integer'],
                        'haveScanned'           => ['type' => 'integer'], // array
                        'firstMapped'           => ['type' => 'integer'],
                        'haveMapped'            => ['type' => 'integer'], // array
                    ]
                ]
            ]
        ]);

        $client->indices()->create([
            'index' => static::$elasticConfig->bodyPlanetIndex,
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
                        'bodyId'                => ['type' => 'integer'],
                        'bodyName'              => [
                            'type'                  => 'keyword',
                            'fields'                => [
                                'keywordAutoComplete'   => ['type' => 'text', 'analyzer' => 'autocomplete', 'search_analyzer' => 'standard'],
                            ],
                        ],
                        'systemName'            => [
                            'type'                  => 'keyword',
                            'fields'                => [
                                'keywordAutoComplete'   => ['type' => 'text', 'analyzer' => 'autocomplete', 'search_analyzer' => 'standard'],
                            ],
                        ],

                        'systemX'               => ['type' => 'double'],
                        'systemY'               => ['type' => 'double'],
                        'systemZ'               => ['type' => 'double'],

                        'subType'               => ['type' => 'short'],
                        'distanceToArrival'     => ['type' => 'integer'],

                        'isLandable'            => ['type' => 'boolean'],
                        'haveBeltsOrRings'      => ['type' => 'boolean'],

                        'age'                   => ['type' => 'integer'],
                        'absoluteMagnitude'     => ['type' => 'double'],

                        'gravity'               => ['type' => 'float'],
                        'mass'                  => ['type' => 'double'],
                        'radius'                => ['type' => 'double'],

                        'terraformingState'     => ['type' => 'byte'],
                        'atmosphereType'        => ['type' => 'short'],
                        'volcanismType'         => ['type' => 'short'],
                        'reserveLevel'          => ['type' => 'short'],

                        'surfaceTemperature'                => ['type' => 'double'],
                        'surfacePressure'                   => ['type' => 'double'],

                        'orbitalPeriod'                     => ['type' => 'double'],
                        'semiMajorAxis'                     => ['type' => 'double'],
                        'orbitalEccentricity'               => ['type' => 'double'],
                        'orbitalInclination'                => ['type' => 'double'],
                        'argOfPeriapsis'                    => ['type' => 'double'],
                        'rotationalPeriod'                  => ['type' => 'double'],
                        'rotationalPeriodTidallyLocked'     => ['type' => 'boolean'],
                        'axialTilt'                         => ['type' => 'double'],

                        'materials'             => [
                            'type'                  => 'nested',
                            'properties'            => [
                                'id'                    => ['type' => 'short'],
                                'value'                 => ['type' => 'scaled_float', 'scaling_factor' => 100],
                            ]
                        ],
                        'atmosphereComposition' => [
                            'type'                  => 'nested',
                            'properties'            => [
                                'id'                    => ['type' => 'short'],
                                'value'                 => ['type' => 'scaled_float', 'scaling_factor' => 100],
                            ]
                        ],
                        'solidComposition'      => [
                            'type'                  => 'nested',
                            'properties'            => [
                                'id'                    => ['type' => 'short'],
                                'value'                 => ['type' => 'scaled_float', 'scaling_factor' => 100],
                            ]
                        ],

                        'firstScanned'          => ['type' => 'integer'],
                        'haveScanned'           => ['type' => 'integer'], // array
                        'firstMapped'           => ['type' => 'integer'],
                        'haveMapped'            => ['type' => 'integer'], // array
                    ]
                ]
            ]
        ]);

        $systemsBodiesInElasticModel = new \Models_Systems_Bodies_InElastic;
        $systemsBodiesInElasticModel->getAdapter()->query('TRUNCATE TABLE ' . $systemsBodiesInElasticModel->info('name'));
    }
}