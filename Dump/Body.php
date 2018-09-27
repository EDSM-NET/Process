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
    
    static public function run()
    {
        static::log('Generating ' . str_replace(PUBLIC_PATH, '', static::$finalFile));
    
        if(file_exists(static::$tempFile))
        {
            unlink(static::$tempFile);
        }
        
        $systemsModel       = new \Models_Systems;
        $systemsHidesModel  = new \Models_Systems_Hides;
        $systemsBodiesModel = new \Models_Systems_Bodies;
        
        // Disable cache
        $systemsBodiesModel->disableCache();
        
        // Fill an array with all days from start to yesterday
        $startDate  = strtotime('2015-05-12');
        $endDate    = strtotime('yesterday');
        
        $loopDates  = array();
        
        while($startDate < $endDate)
        {
            $loopDates[]    = date('Y-m-d', $startDate);
            $startDate     += 86400; // Add 1 day
        }
        
        file_put_contents(static::$tempFile, '[' . PHP_EOL);
        $line = 0;
        
        foreach($loopDates AS $key => $date)
        {
            static::log('    - ' . \Zend_Locale_Format::toNumber($key / count($loopDates) * 100, array('precision' => 2)) . '%');
            
            $select     = $systemsModel->select()
                                       ->from($systemsModel, array('id', 'id64', 'name'))
                                       ->where('updatetime > ?', $date . ' 00:00:00')
                                       ->where('updatetime <= ?', date('Y-m-d', (strtotime($date) + 86400)) . ' 00:00:00')
                                       ->where(
                                           'id NOT IN(?)',
                                           new \Zend_Db_Expr($systemsHidesModel->select()->from($systemsHidesModel, array('refSystem')))
                                       )
                                       ->order('updatetime ASC');
            
            $systems    = $systemsModel->fetchAll($select);
            
            if(!is_null($systems))
            {
                $systems = $systems->toArray();
                
                foreach($systems AS $system)
                {
                    $select = $systemsBodiesModel->select()
                                                 ->where('refSystem = ?', $system['id']);
                    
                    $bodies = $systemsBodiesModel->fetchAll($select);
                    
                    if(!is_null($bodies))
                    {
                        $bodies = $bodies->toArray();
                        
                        foreach($bodies AS $body)
                        {
                            $body       = \EDSM_System_Body::getInstance($body['id'], $body);
                            $tmpBody    = $body->renderApiArray(true);
                            
                            $tmpBody['systemId']    = (int) $system['id'];
                            $tmpBody['systemId64']  = (!is_null($system['id64'])) ? (int) $system['id64'] : null;;
                            $tmpBody['systemName']  = $system['name'];
                            
                            //\Zend_Debug::dump($tmpBody);
                            //exit();
                            
                            if($line > 0)
                            {
                                file_put_contents(static::$tempFile, ',' . PHP_EOL, FILE_APPEND);
                            }
                            
                            file_put_contents(static::$tempFile, '    ' . \Zend_Json::encode($tmpBody), FILE_APPEND);
                            
                            $line++;
                        }
                    }
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
        
        $systemsModel->getAdapter()->closeConnection();
        
        // Enable cache
        $systemsBodiesModel->enableCache();
        
        unset($systemsModel, $systemsHidesModel, $systemsBodiesModel, $systems);
        
        static::endLog();
        return;
    }
}