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

    public static function run()
    {
        if(APPLICATION_DEBUG === true)
        {
            //self::reset();
            static::$limit = 50;
        }

        $limit                              = static::$limit;
        $elasticUpdate                      = 0;
        self::setupDatabaseModels();

        // Disable cache
        self::$systemsBodiesModel->disableCache();

        // Get Elastic client
        $client = self::getClient();
        $client->indices()->putSettings([
            'index' => static::$elasticConfig->bodyIndex,
            'body'  => ['settings'  => ['refresh_interval'  => '3600s']]
        ]);

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

                        ->joinLeft(self::$systemsBodiesOrbitalModel->info('name'), self::$systemsBodiesModel->info('name') . '.id = ' . self::$systemsBodiesOrbitalModel->info('name') . '.refBody')
                        ->joinLeft(self::$systemsBodiesSurfaceModel->info('name'), self::$systemsBodiesModel->info('name') . '.id = ' . self::$systemsBodiesSurfaceModel->info('name') . '.refBody')
                        ->joinLeft(self::$systemsBodiesParentsModel->info('name'), self::$systemsBodiesModel->info('name') . '.id = ' . self::$systemsBodiesParentsModel->info('name') . '.refBody')

                        ->where('inElastic = ?', 0)
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

            $client->indices()->putSettings([
                'index' => static::$elasticConfig->bodyIndex,
                'body'  => ['settings'  => ['refresh_interval'  => '600s']]
            ]);
            //$client->indices()->refresh(array('index' => static::$elasticConfig->bodyIndex));
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

    public static function insertBody($currentBodyId, $currentBody = null)
    {
        self::setupDatabaseModels();
        self::$systemsBodiesModel->disableCache();

        \EDSM_System_Body::destroyInstance($currentBodyId);
        $currentBody    = \EDSM_System_Body::getInstance($currentBodyId, $currentBody);

        if(!is_null($currentBody->getType()))
        {
            $client         = self::getClient();
            $currentSystem  = $currentBody->getSystem();

            // Silently delete the old document if it exists...
            try
            {
                $client->delete([
                    'index'     => static::$elasticConfig->bodyIndex,
                    'type'      => '_doc',
                    'id'        => $currentBodyId
                ]);
            }
            catch(\Elasticsearch\Common\Exceptions\Missing404Exception $ex){}

            // System d'ont exists, delete the body...
            if(is_null($currentSystem))
            {
                self::$systemsBodiesModel->deleteById($currentBodyId);
            }
            else
            {
                // Generate elastic body
                $elasticBody    = [
                    'bodyId'                            => $currentBodyId,
                    'bodyName'                          => strtolower($currentBody->getName()),
                    'systemName'                        => strtolower($currentSystem->getName()),

                    'systemX'                           => $currentSystem->getX(),
                    'systemY'                           => $currentSystem->getY(),
                    'systemZ'                           => $currentSystem->getZ(),

                    'mainType'                          => ( ($currentBody->getMainType() == 'Star') ? 1 : 2 ),
                    'subType'                           => $currentBody->getType(),
                    'distanceToArrival'                 => $currentBody->getDistanceToArrival(),

                    'isMainStar'                        => $currentBody->isMainStar(),
                    'isLandable'                        => ( ($currentBody->getMainType() == 'Star') ? $currentBody->isScoopable() : $currentBody->isLandable() ),

                    'age'                               => $currentBody->getAge(),
                    'spectralClass'                     => $currentBody->getSpectralClass(),
                    'luminosity'                        => $currentBody->getLuminosity(),
                    'absoluteMagnitude'                 => $currentBody->getAbsoluteMagnitude(),

                    'haveBeltsOrRings'                  => false,

                    'gravity'                           => $currentBody->getGravity(),
                    'mass'                              => $currentBody->getMass(),
                    'radius'                            => $currentBody->getRadius(),

                    'terraformingState'                 => $currentBody->getTerraformState(),
                    'atmosphereType'                    => $currentBody->getAtmosphere(),
                    'volcanismType'                     => $currentBody->getVolcanism(),
                    'reserveLevel'                      => $currentBody->getReserveLevel(),

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

                // Belts?
                if($elasticBody['mainType'] == 1)
                {
                    $haveBelts = $currentBody->getBelts();

                    if(!is_null($haveBelts) && count($haveBelts) > 0)
                    {
                        $elasticBody['haveBeltsOrRings'] = true;
                    }
                }

                // Rings?
                if($elasticBody['mainType'] == 2)
                {
                    $haveRings = $currentBody->getRings();

                    if(!is_null($haveRings) && count($haveRings) > 0)
                    {
                        $elasticBody['haveBeltsOrRings'] = true;
                    }
                }

                // Materials
                $currentBodyMaterials   = $currentBody->getMaterials(true);
                if(!is_null($currentBodyMaterials) && count($currentBodyMaterials) > 0)
                {
                    $elasticBody['materials'] = array();

                    foreach($currentBodyMaterials AS $id => $currentMaterial)
                    {
                        $elasticBody['materials'][] = array('id' => $id, 'value' => $currentMaterial['value']);
                    }
                }

                // Atmosphere composition
                $currentBodyAtmosphereComposition   = $currentBody->getAtmosphereComposition();
                if(!is_null($currentBodyAtmosphereComposition) && count($currentBodyAtmosphereComposition) > 0)
                {
                    $elasticBody['atmosphereComposition'] = array();

                    foreach($currentBodyAtmosphereComposition AS $id => $currentComposition)
                    {
                        $elasticBody['atmosphereComposition'][] = array('id' => $id, 'value' => $currentComposition);
                    }
                }

                // Solid composition
                $currentBodySolidComposition   = $currentBody->getSolidComposition();
                if(!is_null($currentBodySolidComposition) && count($currentBodySolidComposition) > 0)
                {
                    $elasticBody['solidComposition'] = array();

                    foreach($currentBodySolidComposition AS $id => $currentComposition)
                    {
                        $elasticBody['solidComposition'][] = array('id' => $id, 'value' => $currentComposition);
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
                $response = $client->index([
                    'index'     => static::$elasticConfig->bodyIndex,
                    'type'      => '_doc',
                    'id'        => $currentBody->getId(),
                    'body'      => $elasticBody,
                ]);
                //\Zend_Debug::dump($response, 'INSERT');

                // Check if it's ok

                try
                {
                    $response = $client->get([
                        'index'     => static::$elasticConfig->bodyIndex,
                        'type'      => '_doc',
                        'id'        => $currentBody->getId()
                    ]);
                }
                catch(\Elasticsearch\Common\Exceptions\Missing404Exception $ex){$response = null;}


                if(is_array($response) && array_key_exists('found', $response) && $response['found'] === true)
                {
                    self::$systemsBodiesModel->updateById($currentBody->getId(), ['inElastic' => 1]);
                    self::$systemsBodiesModel->enableCache();
                    return true;
                }
            }
        }

        self::$systemsBodiesModel->enableCache();

        return false;
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
        }
    }

    public static function getClient()
    {
        if(static::$elasticClient === false)
        {
            require_once LIBRARY_PATH . '/React/Promise/functions_include.php';

            static::$elasticConfig  = \Zend_Registry::get('appConfig')->elacticSearch;
            static::$elasticClient  = \Elasticsearch\ClientBuilder::create()
                                        ->setHosts([static::$elasticConfig->host])
                                        ->build();
        }

        return static::$elasticClient;
    }

    protected function reset()
    {
        $client                 = self::getClient();
        $systemsBodiesModel     = new \Models_Systems_Bodies;

        try
        {
            $client->indices()->delete(['index' => static::$elasticConfig->bodyIndex]);
        }
        catch(\Elasticsearch\Common\Exceptions\Missing404Exception $ex){}

        $client->indices()->create([
            'index' => static::$elasticConfig->bodyIndex,
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
                    '_doc'  => [
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

                            'mainType'              => ['type' => 'byte'],
                            'subType'               => ['type' => 'short'],
                            'distanceToArrival'     => ['type' => 'integer'],

                            'isMainStar'            => ['type' => 'boolean'],
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
            ]
        ]);

        $systemsBodiesModel->update(
            ['inElastic' => 0],
            $systemsBodiesModel->getAdapter()->quoteInto('inElastic = ?', 1)
        );
    }
}