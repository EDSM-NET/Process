<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Dump;

use         Process\Process;

class BodyLastWeek extends Process
{
    static private $tempFile        = APPLICATION_PATH . '/Data/Temp/bodies7days.json';
    static private $finalFile       = PUBLIC_PATH . '/dump/bodies7days.json.gz';

    static private $limit           = 50000;

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
        if(defined('CRON_BODY_MAXID'))
        {
            $maxIdNow  = $maxId = CRON_BODY_MAXID;
        }
        else
        {
            $maxId  = $systemsBodiesModel->fetchRow(
                $systemsBodiesModel->select()->order('id DESC')->limit(1)
            );
            $maxIdNow  = $maxId = $maxId->id;
        }

        // Store last maxId
        $yesterdayKey                   = date('Y-m-d', strtotime('yesterday'));
        $fileMaxIds                     = APPLICATION_PATH . '/Data/Cache/BodyLastWeekId.json';
        $arrayMaxIds                    = \Zend_Json::decode(file_get_contents($fileMaxIds));

        if(!array_key_exists($yesterdayKey, $arrayMaxIds))
        {
            $arrayMaxIds[$yesterdayKey]     = $maxIdNow;
            reset($arrayMaxIds);
        }

        // Get first array maxId
        $currentId  = $arrayMaxIds[key($arrayMaxIds)];

        // Remove extra day
        if(count($arrayMaxIds) > 7)
        {
            unset($arrayMaxIds[key($arrayMaxIds)]);
        }

        // Save
        file_put_contents($fileMaxIds, \Zend_Json::encode($arrayMaxIds));

        // Start temp file
        file_put_contents(static::$tempFile, '[' . PHP_EOL);
        $line       = 0;
        $total      = $maxIdNow - $currentId;

        while($currentId < $maxIdNow)
        {
            $startTime      = time();

            static::log('    - ' . \Zend_Locale_Format::toNumber( ($total - ($maxIdNow - $currentId)) / $total * 100, array('precision' => 2)) . '% (Current ID: ' . $currentId . ')');

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
                                             ->joinLeft($systemsHidesModel->info('name'), $systemsModel->info('name') . '.id = ' . $systemsHidesModel->info('name') . '.refSystem', null)
                                             ->where($systemsHidesModel->info('name') . '.refSystem IS NULL')

                                             ->joinLeft($systemsBodiesOrbitalModel->info('name'), $systemsBodiesModel->info('name') . '.id = ' . $systemsBodiesOrbitalModel->info('name') . '.refBody')
                                             ->joinLeft($systemsBodiesSurfaceModel->info('name'), $systemsBodiesModel->info('name') . '.id = ' . $systemsBodiesSurfaceModel->info('name') . '.refBody')
                                             ->joinLeft($systemsBodiesParentsModel->info('name'), $systemsBodiesModel->info('name') . '.id = ' . $systemsBodiesParentsModel->info('name') . '.refBody')

                                             ->where($systemsBodiesModel->info('name') . '.id > ?', $currentId)
                                             ->where($systemsBodiesModel->info('name') . '.id <= ?', $maxIdNow)
                                             ->order($systemsBodiesModel->info('name') . '.id ASC')
                                             ->limit(static::$limit);

            $bodies     = $systemsBodiesModel->getAdapter()->fetchAll($select);

            static::log('        - Query: ' . \Zend_Locale_Format::toNumber(time() - $startTime) . 's');

            if(!is_null($bodies))
            {
                $dumpStr = '';

                foreach($bodies AS $body)
                {
                    $currentId  = $body['id'];
                    $refSystem  = $body['refSystem'];
                    $systemId64 = $body['systemId64'];
                    $systemName = $body['systemName'];

                    $body       = \EDSM_System_Body::getInstance($body['id'], $body);
                    $tmpBody    = $body->renderApiArray();

                    $tmpBody['systemId']    = (int) $refSystem;
                    $tmpBody['systemId64']  = (!is_null($systemId64)) ? (int) $systemId64 : null;
                    $tmpBody['systemName']  = $systemName;

                    //\Zend_Debug::dump($tmpBody);
                    //exit();

                    if($line > 0)
                    {
                        $dumpStr .= ',' . PHP_EOL;
                    }

                    $dumpStr .= '    ' . \Zend_Json::encode($tmpBody);

                    $line++;
                }

                file_put_contents(static::$tempFile, $dumpStr, FILE_APPEND);
            }

            static::log('        - Total: ' . \Zend_Locale_Format::toNumber(time() - $startTime) . 's');
        }

        file_put_contents(static::$tempFile, PHP_EOL . ']', FILE_APPEND);

        $dest = static::$tempFile . '.gz';
        $mode = 'wb9';
        if($fp_out = gzopen($dest, $mode))
        {
            if($fp_in = fopen(static::$tempFile,'rb'))
            {
                while (!feof($fp_in))
                {
                    gzwrite($fp_out, fread($fp_in, 1024 * 512));
                }
                fclose($fp_in);
            }
            gzclose($fp_out);
        }

        unlink(static::$tempFile);
        rename($dest, static::$finalFile);

        // Add ContentLength JSON
        file_put_contents(
            str_replace('.json.gz', '.length.json.gz', static::$finalFile),
            filesize(static::$finalFile)
        );

        $systemsBodiesModel->getAdapter()->closeConnection();

        unset($systemsBodiesModel, $bodies);

        static::endLog();
        return;
    }
}