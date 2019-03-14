<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Locale;

use         Process\Process;

class Update extends Process
{
    static private $oneSkyEndPoint  = 'https://platform.api.onesky.io/1/projects';
    static private $oneSkyProjectId = null;
    static private $oneSkyPublicKey = null;
    static private $oneSkySecretKey = null;

    static private $localesPath     = APPLICATION_PATH . '/Config/Locale';
    static private $baseLocale      = 'en';
    static private $locales         = null;
    static private $localesAliases  = [
        'en'    => null,
        'en_GB' => null,
        'pt'    => 'pt-PT',
        'zh'    => 'zh-CN',
    ];

    static public function run()
    {
        // Set up configuration
        static::$oneSkyProjectId    = \Zend_Registry::get('appConfig')->oneSky->projectId;
        static::$oneSkyPublicKey    = \Zend_Registry::get('appConfig')->oneSky->public;
        static::$oneSkySecretKey    = \Zend_Registry::get('appConfig')->oneSky->secret;

        static::$locales            = \Zend_Registry::get('appConfig')->resources->locale->available;

        // Import the latest en.po file
        $baseFile                   = static::$localesPath . '/' . static::$baseLocale . '.po';
        if(file_exists($baseFile))
        {
            $lastModifiedBaseFile   = filemtime($baseFile);
            $lastSendBaseFile       = APPLICATION_PATH . '/Data/Temp/ ' . sha1($baseFile);
            $lastSendBaseTime       = (file_exists($lastSendBaseFile)) ? file_get_contents($lastSendBaseFile) : ($lastModifiedBaseFile - 30);

            // Base file is newer, send it for translation...
            if($lastSendBaseTime < $lastModifiedBaseFile)
            {
                $timestamp  = time();
                $apiUri     = static::$oneSkyEndPoint . '/' . static::$oneSkyProjectId . '/files';
                $apiAuth    = '?api_key=' . static::$oneSkyPublicKey . '&timestamp=' . $timestamp . '&dev_hash=' . md5($timestamp . static::$oneSkySecretKey);
                $client     = new \Zend_Http_Client($apiUri . $apiAuth);

                $client->setFileUpload($baseFile, 'file');
                $client->setParameterPost('file_format', 'GNU_PO');
                $client->setParameterPost('locale', static::$baseLocale);
                $client->setParameterPost('is_keeping_all_strings', false);
                $client->setParameterPost('is_allow_translation_same_as_original', true);

                $response = $client->request('POST');

                if($response->getStatus() == 201)
                {
                    file_put_contents($lastSendBaseFile, $lastModifiedBaseFile);
                }
            }
        }

        // Try downloading each file
        foreach(static::$locales AS $currentLocale)
        {
            $fileName = static::$localesPath . '/' . $currentLocale . '.mo';

            if(array_key_exists($currentLocale, static::$localesAliases))
            {
                if(is_null(static::$localesAliases[$currentLocale]))
                {
                    continue;
                }
                else
                {
                    $currentLocale = static::$localesAliases[$currentLocale];
                }
            }

            $timestamp  = time();
            $apiUri     = static::$oneSkyEndPoint . '/' . static::$oneSkyProjectId . '/translations';
            $apiAuth    = '?api_key=' . static::$oneSkyPublicKey . '&timestamp=' . $timestamp . '&dev_hash=' . md5($timestamp . static::$oneSkySecretKey);
            $client     = new \Zend_Http_Client($apiUri . $apiAuth);
            $client->setHeaders('Content-type','application/json');

            $exportFileName = str_replace(static::$localesPath . '/', '', $fileName);
            $exportFileName = str_replace('.mo', '.po', $exportFileName);

            $json       = \Zend_Json::encode(array(
                'locale'            => $currentLocale,
                'source_file_name'  => static::$baseLocale . '.po',
                'export_file_name'  => $exportFileName,
            ));

            \Zend_Debug::dump($apiUri . $apiAuth);
            \Zend_Debug::dump($json);

            $client->setRawData($json, 'application/json');

            $response = $client->request('POST');

            \Zend_Debug::dump($response);
            exit();
        }
        //$client     = new \Zend_Http_Client(static::$oneSkyEndPoint);
        //$response   = $client->request();
        //$body       = $response->getBody();

        //$lastModified               = static::getLastApiCalls();

        return;
    }
}