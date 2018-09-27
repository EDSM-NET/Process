<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Elite;

use         Process\Process;

class Status extends Process
{
    static public function run()
    {
        $cacheKey               = 'EDSM_View_Helper_Elite_ServerStatus';
        $body                   = null;
        
        $result                 = array();
        $result['lastUpdate']   = date('Y-m-d H:i:s');
        $result['type']         = 'info';
        $result['message']      = 'Unknown';
        
        if(APPLICATION_ENV == 'production')
        {
            try
            { 
                $client     = new \Zend_Http_Client('http://hosting.zaonce.net/launcher-status/status.json');
                $response   = $client->request();
                $body       = $response->getBody();
                $body       = \Zend_Json::decode($body);
            }
            catch(\Zend_Json_Exception $e)
            {
                static::log('<span class="text-info">Elite\Status:</span> <span class="text-danger">' . $e->getMessage() . '</span>');
                static::getDatabaseFileCache()->save($result, $cacheKey);
                
                unset($client, $response, $body, $result, $cacheKey);
                
                return false; // Will force asking on next loop
            }
            catch(\Zend_Exception $e)
            {
                $registry = \Zend_Registry::getInstance();
                    
                if($registry->offsetExists('sentryClient'))
                {
                    $sentryClient = $registry->offsetGet('sentryClient');
                    $sentryClient->captureException($e);
                }
                
                return false; // Will force asking on next loop
            }
            
            if(is_array($body) && array_key_exists('text', $body) && array_key_exists('status', $body))
            {
                if($body['status'] == '0')
                {
                    $result['type']    = 'danger';
                    $result['status']  = $body['status'];
                }   
                elseif($body['status'] == '1')
                {
                    $result['type']    = 'warning';
                    $result['status']  = $body['status'];
                }
                elseif($body['status'] == '2')
                {
                    $result['type']    = 'success';
                    $result['status']  = $body['status'];
                }
                
                $result['message'] = $body['text'];
            }
            
            unset($client, $response);
            
            static::log('<span class="text-info">Elite\Status:</span> <span class="text-' . $result['type'] . '">' . $result['message'] . '</span>');
            static::getDatabaseFileCache()->save($result, $cacheKey);
        }
        
        unset($cacheKey, $body, $result);
        
        return;
    }
}