<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\Journal;

use         Process\Process;

class Check extends Process
{
    static protected $limit         = 15000;
    static protected $lastModified  = array();

    static public function run()
    {
        $journalModels      = new \Models_Journal;
        $journalEntries     = self::getEntries();

        $nbSkip             = 0;
        $nbDeleted          = 0;
        $nbDiscarded        = 0;

        foreach($journalEntries AS $entry)
        {
            $entry = $entry->toArray();
            $entry['message'] = \Zend_Json::decode($entry['message']);

            if(!in_array($entry['event'], \Journal\Discard::$events))
            {
                $event              = $entry['event'];
                $eventClass         = '\Journal\Event\\' . $event;

                $classFile = LIBRARY_PATH . '/Journal/Event/' . $event . '.php';
                if(!is_file($classFile))
                {
                    // Switch to default class
                    $eventClass = '\Journal\Event';
                }
                else
                {
                    // Check if reload signal is needed
                    if(!array_key_exists($classFile, static::$lastModified))
                    {
                        static::$lastModified[$classFile] = filemtime($classFile);
                    }
                    else
                    {
                        if(filemtime($classFile) > static::$lastModified[$classFile])
                        {
                            static::sendReloadSignal();
                            return;
                        }
                    }
                }

                // Call the event class
                $eventClass::resetReturnMessage();
                $eventClass::setUser(\Component\User::getInstance($entry['refUser']));
                $eventClass::setSoftware($entry['refSoftware']);

                if(!is_null($entry['gameState']))
                {
                    $entry['gameState'] = \Zend_Json::decode($entry['gameState']);
                    $eventClass::setGameState($entry['gameState']);
                }

                $json               = $entry['message'];
                $json['event']      = $event;
                $json['timestamp']  = $entry['dateEvent'];

                $return             = $eventClass::run($json);

                if($eventClass != 'Journal\Event' && $eventClass::isOK() === true && in_array($return['msgnum'], [100, 101, 102]))
                {
                    $nbDeleted++;
                    $journalModels->deleteById($entry['id']);
                }
                else
                {
                    $nbSkip++;
                    //\Zend_Debug::dump($return);
                }
            }
            else
            {
                $nbDiscarded++;
                $journalModels->deleteById($entry['id']);
            }
        }

        if($nbSkip > 0)
        {
            static::log('<span class="text-info">' . str_replace('\Process\\', '', static::class) . ':</span> Skipped ' . \Zend_Locale_Format::toNumber($nbSkip) . ' events');
        }
        if($nbDeleted > 0)
        {
            static::log('<span class="text-info">' . str_replace('\Process\\', '', static::class) . ':</span> Reparsed ' . \Zend_Locale_Format::toNumber($nbDeleted) . ' events');
        }
        if($nbDiscarded > 0)
        {
            static::log('<span class="text-info">' . str_replace('\Process\\', '', static::class) . ':</span> Deleted ' . \Zend_Locale_Format::toNumber($nbDiscarded) . ' discarded events');
        }

        unset($journalModels, $journalEntries);

        return;
    }

    protected static function getEntries()
    {
        $journalModels      = new \Models_Journal;
        $journalEntries     = $journalModels->select()
                                            ->limit(static::$limit)
                                            ->where('event != ?', 'Scan')
                                            ->where('event != ?', 'SAAScanComplete')
                                            ->order('RAND()');

        return $journalModels->fetchAll($journalEntries);
    }
}