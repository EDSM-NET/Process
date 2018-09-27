<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\User;
use         Process\Process;

class Report extends Process
{
    static private $period          = 14; // In days
    static private $sendReportOn    = array(1, 15);
    
    static private $days            = array();
    static private $users           = array();
    
    static public function run()
    {
        // Make a list of the last period days dates
        for($i = static::$period; $i > 0; $i--)
        {
            static::$days[(static::$period - $i + 1)] = date('Y-m-d', strtotime($i . ' DAYS AGO'));
        }
        
        // Select all users having any activity for the last week
        $usersModel = new \Models_Users;
        $users      = $usersModel->fetchAll(
            $usersModel->select()
                       ->where('dateLastActivity >= ?', static::$days[1])
                       ->where('dateLastActivity <= NOW()')
                       ->where('receiveEmailWeeklyReports = ?', 1)
                       ->where('nbFlightLogs > ?', 0)
                       ->order('dateLastActivity DESC')
        );
        unset($usersModel);
        
        if(!is_null($users) && count($users) > 0)
        {
            static::log('Create ' . \Zend_Locale_Format::toNumber(count($users)) . ' users reports');
            static::$users = $users->toArray();
            unset($users);
                        
            if(APPLICATION_DEBUG !== true)
            {
                static::fillDatabase();
                return;
            }
            
            if(date('N') == 1 && date('W') % 2 == 0 || APPLICATION_DEBUG === true) // Send on Monday every 2 weeks
            {
                static::sendReports();
            }
        }
        
        return;
    }
    
    static private function fillDatabase()
    {
        $usersReportsModel          = new \Models_Users_Reports;
        $systemsLogsModel           = new \Models_Systems_Logs;
        $systemsBodiesModel         = new \Models_Systems_Bodies;
        $systemsBodiesUsersModel    = new \Models_Systems_Bodies_Users;
        
        foreach(static::$users AS $userToHandle)
        {
            static::log('    - Generate ' . $userToHandle['commanderName'] . ' statistics');
            
            $reports[$userToHandle['id']] = array(); // Store all reports values
            
            // Populate the last period days of activity
            foreach(static::$days AS $dayKey => $dateReport)
            {
                $update                 = array();
                $update['refUser']      = $userToHandle['id'];
                $update['dateReport']   = $dateReport;
                
                // Calculate flight logs count
                $select = $systemsLogsModel->select()
                                           ->from($systemsLogsModel, array(
                                                'totalFlightLogs'   => new \Zend_Db_Expr('COUNT(1)'),
                                                'totalFuel'         => new \Zend_Db_Expr('SUM(fuelUsed)'),
                                                'totalDistance'     => new \Zend_Db_Expr('SUM(jumpDistance)'),
                                           ))
                                           ->where('user = ?', $userToHandle['id'])
                                           ->where('DATE(dateVisited) = ?', $dateReport);
                
                $values                     = $systemsLogsModel->fetchRow($select)->toArray();
                
                $update['nbFlightLogs']     = (int) $values['totalFlightLogs'];
                $update['nbFuel']           = (float) $values['totalFuel'];
                $update['nbDistance']       = (float) $values['totalDistance'];
                
                // Calculate bodies scan
                $select = $systemsBodiesUsersModel->select()
                                                   ->from($systemsBodiesUsersModel, array(
                                                        'totalScan'         => new \Zend_Db_Expr('COUNT(1)'),
                                                   ))
                                                   ->setIntegrityCheck(false)
                                                   ->joinInner($systemsBodiesModel->info('name'), 'refBody = id', null)
                                                   ->where($systemsBodiesUsersModel->info('name') . '.refUser = ?', $userToHandle['id'])
                                                   ->where('`group` = ?', 1)
                                                   ->where('DATE(dateScanned) = ?', $dateReport);
                
                $values                     = $systemsBodiesUsersModel->fetchRow($select)->toArray();
                $update['nbScannedStars']   = (int) $values['totalScan'];
                
                $select = $systemsBodiesUsersModel->select()
                                                   ->from($systemsBodiesUsersModel, array(
                                                        'totalScan'         => new \Zend_Db_Expr('COUNT(1)'),
                                                   ))
                                                   ->setIntegrityCheck(false)
                                                   ->joinInner($systemsBodiesModel->info('name'), 'refBody = id', null)
                                                   ->where($systemsBodiesUsersModel->info('name') . '.refUser = ?', $userToHandle['id'])
                                                   ->where('`group` = ?', 2)
                                                   ->where('DATE(dateScanned) = ?', $dateReport);
                
                $values                     = $systemsBodiesUsersModel->fetchRow($select)->toArray();
                $update['nbScannedPlanets'] = (int) $values['totalScan'];
                
                // Save
                $currentReport                          = $usersReportsModel->fetchRow(
                    $usersReportsModel->select()->where('refUser = ?', $userToHandle['id'])
                                                ->where('dateReport = ?', $dateReport)
                );
                
                if(!is_null($currentReport))
                {
                    $usersReportsModel->updateById($currentReport['id'], $update);
                }
                else
                {
                    $usersReportsModel->insert($update);
                }
            }
        }
        
        unset($usersReportsModel, $systemsLogsModel, $systemsBodiesModel, $systemsBodiesUsersModel);
    }
    
    private static function sendReports()
    {
        $usersReportsModel  = new \Models_Users_Reports;
        $usersBadgesModel   = new \Models_Users_Badges;
        $usersFriendsModel  = new \Models_Users_Friends;
        
        // Loop again, find friends and compare values!
        foreach(static::$users AS $userToHandle)
        {
            // Get the fresh reports
            $reports = $usersReportsModel->fetchAll(
                $usersReportsModel->select()->where('refUser = ?', $userToHandle['id'])
                                            ->where('dateReport >= ?', static::$days[1])
                                            ->where('dateReport <= ?', end(static::$days))
            )->toArray();
            
            $variables = array(
                'currentReportUser'             => \EDSM_User::getInstance($userToHandle['id']),
                
                'firstDate'                     => static::$days[1],
                'lastDate'                      => end(static::$days),
                
                'totalFlightLogs'               => 0,
                'betterFlightLogs'              => 0,
                'worstFlightLogs'               => 999999999999999,
                'betterFlightLogsDate'          => null,
                'worstFlightLogsDate'           => null,
                
                'totalFuel'                     => 0,
                'betterFuel'                    => 0,
                'worstFuel'                     => 999999999999999,
                
                'totalDistance'                 => 0,
                'betterDistance'                => 0,
                'worstDistance'                 => 999999999999999,
                
                'totalScannedStars'             => 0,
                'betterScannedStars'            => 0,
                'worstScannedStars'             => 999999999999999,
                
                'totalScannedPlanets'           => 0,
                'betterScannedPlanets'          => 0,
                'worstScannedPlanets'           => 999999999999999,
                
                'topFriends'                    => array(),
                'lastBadges'                    => array(),
            );
            
            // Fill the values
            foreach($reports AS $report)
            {
                $variables['totalFlightLogs']      += $report['nbFlightLogs'];
                $variables['totalFuel']            += $report['nbFuel'];
                $variables['totalDistance']        += $report['nbDistance'];
                $variables['totalScannedStars']    += $report['nbScannedStars'];
                $variables['totalScannedPlanets']  += $report['nbScannedPlanets'];
                
                $variables['betterFlightLogs']      = max($variables['betterFlightLogs'], $report['nbFlightLogs']);
                $variables['betterFuel']            = max($variables['betterFuel'], $report['nbFuel']);
                $variables['betterDistance']        = max($variables['betterDistance'], $report['nbDistance']);
                $variables['betterScannedStars']    = max($variables['betterScannedStars'], $report['nbScannedStars']);
                $variables['betterScannedPlanets']  = max($variables['betterScannedPlanets'], $report['nbScannedPlanets']);
                
                $variables['worstFlightLogs']       = min($variables['worstFlightLogs'], $report['nbFlightLogs']);
                $variables['worstFuel']             = min($variables['worstFuel'], $report['nbFuel']);
                $variables['worstDistance']         = min($variables['worstDistance'], $report['nbDistance']);
                $variables['worstScannedStars']     = min($variables['worstScannedStars'], $report['nbScannedStars']);
                $variables['worstScannedPlanets']   = min($variables['worstScannedPlanets'], $report['nbScannedPlanets']);
                
                if($variables['betterFlightLogs'] == $report['nbFlightLogs'])
                {
                    $variables['betterFlightLogsDate'] = $report['dateReport'];
                }
                if($variables['worstFlightLogs'] == $report['nbFlightLogs'])
                {
                    $variables['worstFlightLogsDate'] = $report['dateReport'];
                }
            }
            
            $variables['averageFlightLogs']         = round($variables['totalFlightLogs'] / 7);
            $variables['averageFuel']               = $variables['totalFuel'] / 7;
            $variables['averageDistance']           = $variables['totalDistance'] / 7;
            $variables['averageScannedStars']       = round($variables['totalScannedStars'] / 7);
            $variables['averageScannedPlanets']     = round($variables['totalScannedPlanets'] / 7);
            
            // Skip user with 0ly
            if($variables['totalDistance'] == 0)
            {
                static::log('    - Skip ' . $userToHandle['commanderName'] . ' report');
                continue;
            }
            else
            {
                static::log('    - Send ' . $userToHandle['commanderName'] . ' report');
            }
            
            // Find friends
            $topFriends                             = array();
            $topFriends[]                           = $userToHandle['id'];
            $friends                                = $usersFriendsModel->getFriendsByRefUser($userToHandle['id']);
            
            foreach($friends AS $friend)
            {
                if($friend['refUser'] != $userToHandle['id'] && !in_array($friend['refUser'], $topFriends))
                {
                    $topFriends[] = $friend['refUser'];
                }
                if($friend['refFriend'] != $userToHandle['id'] && !in_array($friend['refFriend'], $topFriends))
                {
                    $topFriends[] = $friend['refFriend'];
                }
            }
            unset($friends);
            
            if(count($topFriends) > 0)
            {
                // Find top 5 distances by SUM last reports
                $results                                = $usersReportsModel->fetchAll(
                    $usersReportsModel->select()
                                      ->from($usersReportsModel, array('refUser', 'totalDistance' => new \Zend_Db_Expr('SUM(nbDistance)')))
                                      ->where('refUser IN (?)', new \Zend_Db_Expr(implode(',', $topFriends)))
                                      ->where('dateReport >= ?', static::$days[1])
                                      ->where('dateReport <= ?', end(static::$days))
                                      ->group('refUser')
                                      ->order('totalDistance DESC')
                                      ->limit(5)
                );
                
                if(!is_null($results))
                {
                    $results = $results->toArray();
                    
                    foreach($results AS $result)
                    {
                        $variables['topFriends'][$result['refUser']] = $result['totalDistance'];
                    }
                }
                
                unset($results);
            }
            
            // Find last unlocked badges
            $results                                = $usersBadgesModel->fetchAll(
                $usersBadgesModel->select()
                                  ->where('refUser = ?', $userToHandle['id'])
                                  ->where('DATE(dateObtained) >= ?', static::$days[1])
                                  ->where('DATE(dateObtained) <= ?', end(static::$days))
                                  ->order('dateObtained DESC')
                                  ->limit(6)
            );
            
            if(!is_null($results))
            {
                $variables['lastBadges'] = $results->toArray();
            }
            
            unset($results);
            
            // Send report email
            try
            {
                $mail = new \EDSM_Mail();
                $mail->setLanguage( ((!is_null($userToHandle['language'])) ? $userToHandle['language'] : 'en') );
                $mail->setTemplate('fortnightlyReport.phtml');
                $mail->setVariables($variables);
                $mail->setSubject($mail->getView()->translate('EMAIL\Fortnightly Report'));
                
                if(APPLICATION_DEBUG === true)
                {
                    $mail->addTo('anthor.net@gmail.com');
                    echo $mail->send(false);
                    exit();
                }
                else
                {
                    $mail->addTo($userToHandle['email']);
                    $mail->send();
                    $mail->closeConnection();
                }
            } 
            catch(Exception $e)
            {
                // Do nothing, user will see it next time!
            }
        }
        
        unset($usersReportsModel, $usersBadgesModel, $usersFriendsModel);
    }
}