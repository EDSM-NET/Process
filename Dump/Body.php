<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Dump;

use         Process\Process;

class Body extends Process
{
    static private $tempFile        = APPLICATION_PATH . '/Data/Temp/bodies.json';
    static private $finalFile       = PUBLIC_PATH . '/dump/bodies.json';

    static private $limit           = 250000;

    static public function run()
    {
        static::log('Generating ' . str_replace(PUBLIC_PATH, '', static::$finalFile));

        if(file_exists(static::$tempFile))
        {
            unlink(static::$tempFile);
        }

        $systemsModel               = new \Models_Systems;
        $systemsHidesModel          = new \Models_Systems_Hides;

        $systemsBodiesModel         = new \Models_Systems_Bodies;
        $systemsBodiesOrbitalModel  = new \Models_Systems_Bodies_Orbital;
        $systemsBodiesSurfaceModel  = new \Models_Systems_Bodies_Surface;
        $systemsBodiesParentsModel  = new \Models_Systems_Bodies_Parents;

        // Disable cache
        $systemsBodiesModel->disableCache();

        // Get current max id
        $maxId  = $systemsBodiesModel->fetchRow(
            $systemsBodiesModel->select()->order('id DESC')->limit(1)
        );
        $maxId  = $maxId->id;

        // Start temp file
        file_put_contents(static::$tempFile, '[' . PHP_EOL);
        $line       = 0;
        $currentId  = 0;

        while($currentId < $maxId)
        {
            $startTime      = time();

            static::log('    - ' . \Zend_Locale_Format::toNumber($currentId / $maxId * 100, array('precision' => 2)) . '% (Current ID: ' . $currentId . ')');

            $select     = $systemsBodiesModel->select()
                                             ->setIntegrityCheck(false)
                                             ->from(
                                                 $systemsBodiesModel,
                                                 array(
                                                     $systemsBodiesModel->info('name') . '.*',
                                                     $systemsBodiesOrbitalModel->info('name') . '.*',
                                                     $systemsBodiesSurfaceModel->info('name') . '.*',
                                                     $systemsBodiesParentsModel->info('name') . '.*',
                                                     'systemId64'   => $systemsModel->info('name') . '.id64',
                                                     'systemName'   => $systemsModel->info('name') . '.name',
                                                 )
                                             )
                                             ->joinInner($systemsModel->info('name'), $systemsBodiesModel->info('name') . '.refSystem = ' . $systemsModel->info('name') . '.id', null)
                                             ->joinLeft($systemsHidesModel->info('name'), $systemsModel->info('name') . '.id = ' . $systemsHidesModel->info('name') . '.refSystem')
                                             ->where($systemsHidesModel->info('name') . '.refSystem IS NULL')

                                             ->joinLeft($systemsBodiesOrbitalModel->info('name'), $systemsBodiesModel->info('name') . '.id = ' . $systemsBodiesOrbitalModel->info('name') . '.refBody')
                                             ->joinLeft($systemsBodiesSurfaceModel->info('name'), $systemsBodiesModel->info('name') . '.id = ' . $systemsBodiesSurfaceModel->info('name') . '.refBody')
                                             ->joinLeft($systemsBodiesParentsModel->info('name'), $systemsBodiesModel->info('name') . '.id = ' . $systemsBodiesParentsModel->info('name') . '.refBody')

                                             ->where($systemsBodiesModel->info('name') . '.id > ?', $currentId)
                                             ->where($systemsBodiesModel->info('name') . '.id <= ?', $maxId)
                                             ->order($systemsBodiesModel->info('name') . '.id ASC')
                                             ->limit(static::$limit);

            $bodies     = $systemsBodiesModel->getAdapter()->fetchAll($select);

            static::log('        - Query: ' . \Zend_Locale_Format::toNumber(time() - $startTime) . 's');

            if(!is_null($bodies))
            {
                foreach($bodies AS $body)
                {
                    $currentId  = $body['id'];
                    $refSystem  = $body['refSystem'];
                    $systemId64 = $body['systemId64'];
                    $systemName = $body['systemName'];

                    $body       = \EDSM_System_Body::getInstance($body['id'], $body);
                    //$tmpBody    = $body->renderApiArray(true);
                    $tmpBody    = $body->renderApiArray();

                    $tmpBody['systemId']    = (int) $refSystem;
                    $tmpBody['systemId64']  = (!is_null($systemId64)) ? (int) $systemId64 : null;
                    $tmpBody['systemName']  = $systemName;

                    if($line > 0)
                    {
                        file_put_contents(static::$tempFile, ',' . PHP_EOL, FILE_APPEND);
                    }

                    file_put_contents(static::$tempFile, '    ' . \Zend_Json::encode($tmpBody), FILE_APPEND);

                    $line++;
                }
            }

            static::log('        - Total: ' . \Zend_Locale_Format::toNumber(time() - $startTime) . 's');
            //exit();
        }

        file_put_contents(static::$tempFile, PHP_EOL . ']', FILE_APPEND);

        rename(static::$tempFile, static::$finalFile);

        // Add ContentLength JSON
        file_put_contents(
            str_replace('.json', '.length.json', static::$finalFile),
            filesize(static::$finalFile)
        );

        $systemsModel->getAdapter()->closeConnection();

        // Enable cache
        $systemsBodiesModel->enableCache();

        unset($systemsModel, $systemsHidesModel, $systemsBodiesModel, $systems);

        static::endLog();
        return;
    }
}