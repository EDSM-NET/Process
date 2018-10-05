<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace Process;

abstract class Process
{
    public static $sendTweet            = false;
    
    protected static $databaseCache     = null;
    protected static $databaseFileCache = null;
    
    abstract static function run();
    
    protected static function log($str)
    {
        // Old CRON compatibility
        if(function_exists('echoLog'))
        {
            return echoLog($str);
        }
        
        return \EDSM_Api_Logger::log($str);
    }
    
    protected static function endLog()
    {
        static::log('');
        static::log('');
        
        static::log('----------------------------------------------------------------------------------------------------------------');
        
        static::log('');
        static::log('');
    }
    
    protected static function sendReloadSignal()
    {
        static::log('');
        static::log('<span class="text-danger">Reload signal (' . APPLICATION_ENV . ')</span>');
        exit();
    }
    
    protected static function getDatabaseCache()
    {
        if(is_null(static::$databaseCache))
        {
            $bootstrap              = \Zend_Registry::get('Zend_Application');
            $cacheManager           = $bootstrap->getResource('cachemanager');
            
            static::$databaseCache  = $cacheManager->getCache('database');
            
            unset($bootstrap, $cacheManager);
        }
        
        return static::$databaseCache;
    }
    
    protected static function getDatabaseFileCache()
    {
        if(is_null(static::$databaseFileCache))
        {
            $bootstrap              = \Zend_Registry::get('Zend_Application');
            $cacheManager           = $bootstrap->getResource('cachemanager');
            
            static::$databaseFileCache  = $cacheManager->getCache('databaseFile');
            
            unset($bootstrap, $cacheManager);
        }
        
        return static::$databaseFileCache;
    }
    
    public static function setSendTweetStatus($bool)
    {
        self::$sendTweet = $bool;
    }
}