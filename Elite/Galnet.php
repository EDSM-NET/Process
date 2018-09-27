<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Elite;

use         Process\Process;

class Galnet extends Process
{
    static private $url = 'https://community.elitedangerous.com/galnet';
    
    static public function run()
    {
        $added                  = 0;
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
            require_once(LIBRARY_PATH . '/simple_html_dom.php');
            
            $timelineModel  = new \Models_Timeline;
            
            $html = new \simple_html_dom();
            $html->load($body);
            
            $mainBlock      = $html->find('#block-system-main', 0);
            $articles       = $mainBlock->find('.article');
            
            foreach($articles AS $article)
            {
                $title          = $article->find('h3', 0);
                $releaseDate    = $article->find('div', 0);
                
                $article        = trim(str_replace(
                    array(
                        $title->outertext(),
                        $releaseDate->outertext()
                    ),
                    array(
                        '', ''
                    ),
                    $article->innertext()
                ));
                
                $title          = trim($title->text());
                $releaseDate    = trim($releaseDate->find('.small', 0)->text());
                
                $article        = str_replace('<br />', PHP_EOL, $article);
                $article        = strip_tags($article);
                
                $releaseDate    = explode(' ', $releaseDate);
                
                $releaseDate[1] = str_replace(
                    array('JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'),
                    array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'),
                    $releaseDate[1]
                );
                
                $releaseDate    = $releaseDate[2] . '-' . $releaseDate[1] . '-' . str_pad($releaseDate[0], 2, '0', STR_PAD_LEFT);
                
                // Find current article
                $query  = $timelineModel->select()
                                        ->where('category = ?', 'galnet')
                                        ->where('name = ?', $title)
                                        ->where('description = ?', $article)
                                        ->where('releaseDate > ?', new \Zend_Db_Expr('DATE_SUB("' . $releaseDate . '", INTERVAL 1 WEEK)'));
                $articleExists = $timelineModel->fetchRow($query);
                
                if(is_null($articleExists))
                {
                    $releaseDate = $releaseDate;
                    
                    $timelineModel->insert(array(
                        'category'      => 'galnet',
                        'name'          => $title,
                        'description'   => $article,
                        'releaseDate'   => $releaseDate,
                    ));
                    
                    static::log('<span class="text-info">Elite\GalNet:</span> <span class="text-success">Added: ' . $title . '</span>');
                    
                    $added++;
                }
                /*
                else
                {
                    // Assume next articles are already in the database
                    break;
                }
                */
            }
            
            unset($timelineModel);
        }
        
        static::log('<span class="text-info">Elite\GalNet:</span> <span class="text-info">Added ' . $added . ' news</span>');
        
        return;
    }
}
