<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Dump;

use         Process\Process;

class Codex extends Process
{
    static private $tempFile        = APPLICATION_PATH . '/Data/Temp/codex.json';
    static private $finalFile       = PUBLIC_PATH . '/dump/codex.json.gz';

    static private $countPerPages   = 50000;

    static public function run()
    {
        static::log('Generating ' . str_replace(PUBLIC_PATH, '', static::$finalFile));

        if(file_exists(static::$tempFile))
        {
            unlink(static::$tempFile);
        }

        $codexModel         = new \Models_Codex;
        $codexReportModel   = new \Models_Codex_Reports;

        $select             = $codexReportModel->select()
                                               ->setIntegrityCheck(false)
                                                ->from(
                                                    $codexReportModel,
                                                    array(
                                                        $codexReportModel->info('name') . '.*',
                                                        $codexModel->info('name') . '.refRegion',
                                                        $codexModel->info('name') . '.refType',
                                                    )
                                                )
                                                ->joinInner($codexModel->info('name'), $codexReportModel->info('name') . '.refCodex = ' . $codexModel->info('name') . '.id', null)
                                                ->order('reportedOn ASC');

        file_put_contents(static::$tempFile, '[' . PHP_EOL);

        $adapter    = new \Zend_Paginator_Adapter_DbTableSelect($select);
        $paginator  = new \Zend_Paginator($adapter);
        $paginator->setItemCountPerPage( static::$countPerPages );

        $pageCount  = $paginator->count();
        $key        = 0;

        for($i = 1; $i <= $pageCount; $i++)
        {
            static::log('    - ' . \Zend_Locale_Format::toNumber($i / $pageCount * 100, array('precision' => 2)) . '%');

            $paginator->setCurrentPageNumber( $i );

            $dumpStr        = '';
            $codexReports   = $paginator->getCurrentItems()->toArray();

            foreach($codexReports AS $codexReport)
            {
                $system     = \Component\System::getInstance($codexReport['refSystem']);

                if($system->isHidden() === false)
                {
                    $tmpCodex = array();

                    $tmpCodex['systemId']    = (int) $codexReport['refSystem'];
                    $tmpCodex['systemId64']  = $system->getId64();
                    $tmpCodex['systemName']  = $system->getName();
                    $tmpCodex['region']      = \Alias\Codex\Region::get((int) $codexReport['refRegion']);
                    $tmpCodex['type']        = \Alias\Codex\Type::getToFd((int) $codexReport['refType']);
                    $tmpCodex['name']        = \Alias\Codex\Type::get((int) $codexReport['refType']);
                    $tmpCodex['reportedOn']  = $codexReport['reportedOn'];

                    if($key > 0)
                    {
                        $dumpStr .= ',' . PHP_EOL;
                    }

                    $dumpStr .= '    ' . \Zend_Json::encode($tmpCodex);

                    $key++;
                }
            }

            file_put_contents(static::$tempFile, $dumpStr, FILE_APPEND);
        }

        file_put_contents(static::$tempFile, PHP_EOL . ']', FILE_APPEND);

        $dest = static::$tempFile . '.gz';
        $mode = 'wb9';
        $error = false;
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

        $codexModel->getAdapter()->closeConnection();
        unset($codexModel, $codexReportModel);

        static::endLog();
        return;
    }
}