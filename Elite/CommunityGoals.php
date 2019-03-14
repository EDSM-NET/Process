<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Elite;

use         Process\Process;

class CommunityGoals extends Process
{
    static private $url = 'https://elitedangerous-website-backend-production.elitedangerous.com/api/initiatives/list?_format=json&lang=en';

    static public function run()
    {
        $added                  = 0;
        $updated                = 0;
        $body                   = null;
        $res                    = array();

        if(APPLICATION_ENV == 'production')
        {
            try
            {
                $client     = new \Zend_Http_Client(static::$url);
                $response   = $client->request();
                $body       = $response->getBody();
            }
            catch(Exception $e)
            {
            	$body = null;
            }
        }

        if(!is_null($body))
        {
            $communityGoalsModel        = new \Models_CommunityGoals;

            try
            {
                $body = \Zend_Json::decode($body);
            }
            catch(\Zend_Json_Exception $ex)
            {
                $body = null;
                static::log('<span class="text-info">Elite\CommunityGoals:</span> <span class="text-danger">Invalid JSON, retrying later...</span>');
                return;
            }

            if(is_array($body) && array_key_exists('activeInitiatives', $body))
            {
                foreach($body['activeInitiatives'] AS $cg)
                {
                    // Find current cg
                    $query      = $communityGoalsModel->select()
                                                      ->where('id = ?', (int) $cg['id']);
                    $cgExists   = $communityGoalsModel->fetchRow($query);

                    if(is_null($cgExists))
                    {

                    }
                    else
                    {
                        $updateArray    = array();

                        if($cg['bulletin'] != $cgExists['description'])
                        {
                            $updateArray['description'] = $cg['bulletin'];
                        }

                        if(count($updateArray) > 0)
                        {
                            $communityGoalsModel->updateById($cgExists['id'], $updateArray);
                            $updated++;
                        }
                    }
                }
            }

            unset($timelineModel);
        }

        //static::log('<span class="text-info">Elite\CommunityGoals:</span> <span class="text-info">Added ' . $added . ' community goals</span>');

        if($updated > 0)
        {
            static::log('<span class="text-info">Elite\CommunityGoals:</span> <span class="text-info">Updated ' . $updated . ' community goals</span>');
        }

        return;
    }
}
