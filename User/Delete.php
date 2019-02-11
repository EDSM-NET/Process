<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\User;

use         Process\Process;

class Delete extends Process
{
    static public function run()
    {
        if(!defined('Process_User_Delete_noGivingBadge'))
        {
            define('Process_User_Delete_noGivingBadge', true);
        }

        $usersModel = new \Models_Users;
        $users      = $usersModel->fetchAll(
            $usersModel->select()->where('isMarkedForClearing = ?', 1)->orWhere('isMarkedForDeletion = ?', 1)
        );

        if(!is_null($users) && count($users) > 0)
        {
            static::log('Clearing/Deleting users');

            $users = $users->toArray();

            foreach($users AS $userToHandle)
            {
                // Delete codex reports
                $model  = new \Models_Codex_Reports;
                $model->deleteByRefUser($userToHandle['id']);
                unset($model);

                // Delete codex traits
                $model  = new \Models_Codex_Traits;
                $model->deleteByRefUser($userToHandle['id']);
                unset($model);

                // Delete exploration values
                $model  = new \Models_Users_Exploration_Values;
                $model->deleteByRefUser($userToHandle['id']);
                unset($model);

                // Delete all user alerts
                $model  = new \Models_Users_Alerts;
                $values = $model->getByRefUser($userToHandle['id']);

                if(!is_null($values) && count($values) > 0)
                {
                    foreach($values AS $value)
                    {
                        $model->deleteById($value['id']);
                    }
                }
                unset($model, $values);

                // Delete all user badges
                $model  = new \Models_Users_Badges;
                $values = $model->getByRefUser($userToHandle['id']);

                if(!is_null($values) && count($values) > 0)
                {
                    foreach($values AS $value)
                    {
                        $model->deleteById($value['id']);
                    }
                }
                unset($model, $values);

                // Delete all user cargo
                $model  = new \Models_Users_Cargo;
                $values = $model->getByRefUser($userToHandle['id']);

                if(!is_null($values) && count($values) > 0)
                {
                    foreach($values AS $value)
                    {
                        $model->deleteById($value['id']);
                    }
                }
                unset($model, $values);

                // Delete all user community goals
                $model  = new \Models_Users_CommunityGoals;
                $values = $model->getByRefUser($userToHandle['id']);

                if(!is_null($values) && count($values) > 0)
                {
                    foreach($values AS $value)
                    {
                        $model->deleteById($value['id']);
                    }
                }
                unset($model, $values);

                // Delete all user credits
                $model  = new \Models_Users_Credits;
                $values = $model->fetchAll($model->select()->from($model, array('id'))->where('refUser = ?', $userToHandle['id']));

                if(!is_null($values) && count($values) > 0)
                {
                    foreach($values AS $value)
                    {
                        $model->deleteById($value['id']);
                    }
                }
                unset($model, $values);

                // Delete all user crimes
                $model  = new \Models_Users_Crimes;
                $values = $model->getByRefUser($userToHandle['id']);

                if(!is_null($values) && count($values) > 0)
                {
                    foreach($values AS $value)
                    {
                        $model->deleteById($value['id']);
                    }
                }
                unset($model, $values);

                // Delete all user data
                $model  = new \Models_Users_Data;
                $values = $model->getByRefUser($userToHandle['id']);

                if(!is_null($values) && count($values) > 0)
                {
                    foreach($values AS $value)
                    {
                        $model->deleteById($value['id']);
                    }
                }
                unset($model, $values);

                // Delete all user deaths
                $model  = new \Models_Users_Deaths;
                $values = $model->getByRefUser($userToHandle['id']);

                if(!is_null($values) && count($values) > 0)
                {
                    foreach($values AS $value)
                    {
                        $model->deleteById($value['id']);
                    }
                }
                unset($model, $values);

                // Delete all user diaries
                $model  = new \Models_Users_Diaries;
                $values = $model->getByRefUser($userToHandle['id']);

                if(!is_null($values) && count($values) > 0)
                {
                    foreach($values AS $value)
                    {
                        $model->deleteById($value['id']);
                    }
                }
                unset($model, $values);

                // Delete all user engineers
                $model  = new \Models_Users_Engineers;
                $model->deleteByRefUser($userToHandle['id']);
                unset($model);

                // Delete all user friends
                $model  = new \Models_Users_Friends;
                $values = $model->fetchAll($model->select()->from($model, array('refUser', 'refFriend'))->where('refUser = ?', $userToHandle['id'])->orWhere('refFriend = ?', $userToHandle['id']));

                if(!is_null($values) && count($values) > 0)
                {
                    foreach($values AS $value)
                    {
                        $model->deleteByRefUserAndRefFriend($value['refUser'], $value['refFriend']);
                    }
                }
                unset($model, $values);

                // Delete user token
                $model  = new \Models_Users_FrontierAuth;
                $model->deleteByRefUser($userToHandle['id']);
                unset($model);

                // Delete all user links (ONLY ON DELETE)
                if($userToHandle['isMarkedForDeletion'] == 1)
                {
                    $model  = new \Models_Users_Links;
                    $values = $model->fetchAll($model->select()->from($model, array('refUser', 'refLink'))->where('refUser = ?', $userToHandle['id'])->orWhere('refLink = ?', $userToHandle['id']));

                    if(!is_null($values) && count($values) > 0)
                    {
                        foreach($values AS $value)
                        {
                            $model->deleteByRefUserAndRefLink($value['refUser'], $value['refLink']);
                        }
                    }
                    unset($model, $values);
                }

                // Delete all user materials
                $model  = new \Models_Users_Materials;
                $values = $model->getByRefUser($userToHandle['id']);

                if(!is_null($values) && count($values) > 0)
                {
                    foreach($values AS $value)
                    {
                        $model->deleteById($value['id']);
                    }
                }
                unset($model, $values);

                // Delete all user missions
                $model  = new \Models_Users_Missions;
                $values = $model->getByRefUser($userToHandle['id']);

                if(!is_null($values) && count($values) > 0)
                {
                    foreach($values AS $value)
                    {
                        $model->deleteById($value['id']);
                    }
                }
                unset($model, $values);

                // Delete all user reports
                $model  = new \Models_Users_Reports;
                $values = $model->fetchAll($model->select()->from($model, array('id'))->where('refUser = ?', $userToHandle['id']));

                if(!is_null($values) && count($values) > 0)
                {
                    foreach($values AS $value)
                    {
                        $model->deleteById($value['id']);
                    }
                }
                unset($model, $values);

                // Delete all user ranks
                $model  = new \Models_Users_Ranks;
                $model->deleteByRefUser($userToHandle['id']);
                unset($model);

                // Delete all user reputations
                $model  = new \Models_Users_Reputations;
                $model->deleteByRefUser($userToHandle['id']);
                unset($model);

                // Delete all user ships
                $model  = new \Models_Users_Ships;
                $model2 = new \Models_Users_Ships_Modules;
                $values = $model->getByRefUser($userToHandle['id']);

                if(!is_null($values) && count($values) > 0)
                {
                    foreach($values AS $value)
                    {
                        $model2->deleteByRefShip($value['id']);
                        $model->deleteById($value['id']);
                    }
                }
                unset($model, $model2, $values);

                // Delete all user statistics
                $model  = new \Models_Users_Statistics;
                $model->deleteByRefUser($userToHandle['id']);
                unset($model);

                // Delete all user technology brokers
                $model  = new \Models_Users_TechnologyBrokers;
                $model->deleteByRefUser($userToHandle['id']);
                unset($model);

                // Delete all user flight logs
                $model  = new \Models_Systems_Logs;
                $values = $model->fetchAll($model->select()->from($model, array('id'))->where('user = ?', $userToHandle['id']));

                if(!is_null($values) && count($values) > 0)
                {
                    foreach($values AS $value)
                    {
                        $model->deleteById($value['id']);
                    }
                }
                unset($model, $values);

                // Delete all user body scans
                $model  = new \Models_Systems_Bodies_Users;
                $values = $model->getByRefUser($userToHandle['id']);

                if(!is_null($values) && count($values) > 0)
                {
                    foreach($values AS $value)
                    {
                        $model->deleteByRefBodyAndRefUser($value['refBody'], $userToHandle['id']);
                    }
                }
                unset($model, $values);

                // Delete all user body SAA
                $model  = new \Models_Systems_Bodies_UsersSAA;
                $values = $model->getByRefUser($userToHandle['id']);

                if(!is_null($values) && count($values) > 0)
                {
                    foreach($values AS $value)
                    {
                        $model->deleteByRefBodyAndRefUser($value['refBody'], $userToHandle['id']);
                    }
                }
                unset($model, $values);

                // FINAL
                if($userToHandle['isMarkedForDeletion'] == 1)
                {
                    // Delete users fingerprint
                    $model  = new \Models_Users_Fingerprint;
                    $model->deleteByRefUser($userToHandle['id']);
                    unset($model);

                    $usersModel->deleteById($userToHandle['id']);

                    // Send delete email
                    try
                    {
                        $mail = new \EDSM_Mail();
                        $mail->setLanguage( ((!is_null($userToHandle['language'])) ? $userToHandle['language'] : 'en') );
                        $mail->setTemplate('delete.phtml');
                        $mail->setVariables(array(
                            'commander'   => $userToHandle['commanderName'],
                        ));

                        $mail->addTo($userToHandle['email']);

                        $mail->setSubject($mail->getView()->translate('EMAIL\Account deleted'));
                        $mail->send();
                        $mail->closeConnection();
                    }
                    catch(Exception $e)
                    {
                        // Do nothing, user is gone anyway :)
                    }

                    static::log('    - ' . $userToHandle['commanderName'] . ' deleted.');
                }
                elseif($userToHandle['isMarkedForClearing'] == 1)
                {
                    $usersModel->updateById($userToHandle['id'], array('isMarkedForClearing' => 0));

                    // Send delete email
                    try
                    {
                        $mail = new \EDSM_Mail();
                        $mail->setLanguage( ((!is_null($userToHandle['language'])) ? $userToHandle['language'] : 'en') );
                        $mail->setTemplate('clear.phtml');
                        $mail->setVariables(array(
                            'commander'   => $userToHandle['commanderName'],
                        ));

                        $mail->addTo($userToHandle['email']);

                        $mail->setSubject($mail->getView()->translate('EMAIL\Save cleared'));
                        $mail->send();
                        $mail->closeConnection();
                    }
                    catch(Exception $e)
                    {
                        // Do nothing, user will see when he comes back :)
                    }

                    static::log('    - ' . $userToHandle['commanderName'] . ' cleared.');
                }

                $usersModel->getAdapter()->closeConnection();
            }
        }

        unset($usersModel);

        return;
    }
}