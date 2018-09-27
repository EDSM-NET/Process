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
    static private $finalFile       = PUBLIC_PATH . '/dump/bodies7days.json';
    
    static private $countPerPages   = 25000;
    
    static public function run()
    {
        static::log('Generating ' . str_replace(PUBLIC_PATH, '', static::$finalFile));
    
        if(file_exists(static::$tempFile))
        {
            unlink(static::$tempFile);
        }
        
        $systemsBodiesModel = new \Models_Systems_Bodies;
        
        file_put_contents(static::$tempFile, '[' . PHP_EOL);
        
        $select     = $systemsBodiesModel->select()
                                         ->where('dateUpdated >= DATE(DATE_SUB(NOW(), INTERVAL 7 DAY))')
                                         ->where('dateUpdated <= ?', date('Y-m-d', strtotime('yesterday')) . ' 23:59:59')
                                         ->order('dateUpdated ASC');
        
        $adapter    = new \Zend_Paginator_Adapter_DbTableSelect($select);
        $paginator  = new \Zend_Paginator($adapter);
        $paginator->setItemCountPerPage( static::$countPerPages );
        
        $pageCount  = $paginator->count();
        $key        = 0;
        
        for($i = 1; $i <= $pageCount; $i++)
        {
            static::log('    - ' . \Zend_Locale_Format::toNumber($i / $pageCount * 100, array('precision' => 2)) . '%');
            
            $paginator->setCurrentPageNumber( $i );
            
            $bodies = $paginator->getCurrentItems()->toArray();
            
            foreach($bodies AS $body)
            {
                $body       = \EDSM_System_Body::getInstance($body['id'], $body);
                $system     = $body->getSystem();
                
                if($system->isHidden() === false)
                {
                    $tmpBody    = $body->renderApiArray(true);
                    
                    $tmpBody['systemId']    = $system->getId();
                    $tmpBody['systemId64']  = $system->getId64();
                    $tmpBody['systemName']  = $system->getName();
                    
                    //\Zend_Debug::dump($tmpBody);
                    //exit();
                    
                    if($key > 0)
                    {
                        file_put_contents(static::$tempFile, ',' . PHP_EOL, FILE_APPEND);
                    }
                    
                    file_put_contents(static::$tempFile, '    ' . \Zend_Json::encode($tmpBody), FILE_APPEND);
                    
                    $key++;
                }
            }
        }
        
        file_put_contents(static::$tempFile, PHP_EOL . ']', FILE_APPEND);
        
        rename(static::$tempFile, static::$finalFile);
        
        // Add ContentLength JSON
        file_put_contents(
            str_replace('.json', '.length.json', static::$finalFile),
            filesize(static::$finalFile)
        );
        
        $systemsBodiesModel->getAdapter()->closeConnection();
        
        unset($systemsBodiesModel, $bodies);
        
        static::endLog();
        return;
    }
}