<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Elite;

use         Process\Process;

class Galnet extends Process
{
    static private $url = 'https://cms.zaonce.net/en-GB/jsonapi/node/galnet_article?sort=-published_at';

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
            catch(\Zend_Http_Client_Adapter_Exception $e)
            {
            	$body = null;
            }
        }

        if(!is_null($body))
        {
            $timelineModel  = new \Models_Timeline;

            try
            {
                $body = \Zend_Json::decode($body);
            }
            catch(\Zend_Json_Exception $ex)
            {
                $body = null;
                static::log('<span class="text-info">Elite\GalNet:</span> <span class="text-danger">Invalid JSON, retrying later...</span>');
                return;
            }

            if(is_array($body) && array_key_exists('data', $body))
            {
                foreach($body['data'] AS $article)
                {
                    if($article['type'] === 'node--galnet_article' && array_key_exists('attributes', $article))
                    {
                        $article['attributes']['title']   = trim($article['attributes']['title']);

                        $releaseDate        = explode(' ', $article['attributes']['field_galnet_date']);
                        $releaseDate[1]     = str_replace(
                            array('JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'),
                            array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'),
                            $releaseDate[1]
                        );
                        $releaseDate    = $releaseDate[2] . '-' . $releaseDate[1] . '-' . str_pad($releaseDate[0], 2, '0', STR_PAD_LEFT);

                        // Find current article
                        $query  = $timelineModel->select()
                                                ->where('category = ?', 'galnet')
                                                ->where('name = ?', $article['attributes']['title'])
                                                ->where('releaseDate = ?', $releaseDate);
                        $articleExists = $timelineModel->fetchRow($query);

                        if(is_null($articleExists))
                        {
                            $releaseDate = $releaseDate;

                            $timelineModel->insert(array(
                                'category'      => 'galnet',
                                'name'          => $article['attributes']['title'],
                                'description'   => $article['attributes']['body']['value'],
                                'image'         => $article['attributes']['field_galnet_image'],
                                'slug'          => $article['attributes']['field_slug'],
                                'releaseDate'   => $releaseDate,
                            ));

                            static::log('<span class="text-info">Elite\GalNet:</span> <span class="text-success">Added: ' . $article['attributes']['title'] . '</span>');

                            $added++;
                        }
                        else
                        {
                            $updateArray    = array();

                            if($article['attributes']['body']['value'] != $articleExists['description'])
                            {
                                $updateArray['description'] = $article['attributes']['body']['value'];
                            }
                            if($article['attributes']['field_galnet_image'] != $articleExists['image'])
                            {
                                $updateArray['image'] = $article['attributes']['field_galnet_image'];
                            }
                            if($article['attributes']['field_slug'] != $articleExists['slug'])
                            {
                                $updateArray['slug'] = $article['attributes']['field_slug'];
                            }

                            if(count($updateArray) > 0)
                            {
                                $timelineModel->updateById($articleExists['id'], $updateArray);
                                $updated++;
                            }
                        }
                    }
                }
            }

            unset($timelineModel);
        }

        static::log('<span class="text-info">Elite\GalNet:</span> <span class="text-info">Added ' . $added . ' news</span>');

        if($updated > 0)
        {
            static::log('<span class="text-info">Elite\GalNet:</span> <span class="text-info">Updated ' . $updated . ' news</span>');
        }

        return;
    }
}
