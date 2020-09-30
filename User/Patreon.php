<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Process\User;
use         Process\Process;

class Patreon extends Process
{
    use \Config\Secret\User\Patreon;

    static public function run()
    {
        $csv = file(APPLICATION_PATH . '/Config/Secret/User/detailed-patronage.csv');

        $usersModel             = new \Models_Users;
        $usersDonationsModel    = new \Models_Users_Donations;

        $missingEmails          = array();

        unset($csv[0]);

        foreach($csv AS $line)
        {
            $line = str_getcsv(trim($line));

            if(empty($line[3]))
            {
                // Aliases email?
                if(array_key_exists($line[1], self::$emailsAliases))
                {
                    $currentUser = array('id' => self::$emailsAliases[$line[1]]);
                }
                else
                {
                    // Find user
                    $currentUser = $usersModel->getByEmail($line[1]);
                }

                if(!is_null($currentUser))
                {
                    $currentDate    = $line[2];
                    $amount         = (float) str_replace('$', '', $line[4]);

                    if($amount > 0)
                    {
                        try
                        {
                            $insert = array(
                                'refUser'       => $currentUser['id'],
                                'amount'        => $amount,
                                'type'          => 'patreon',
                                'dateDonation'  => $currentDate,
                            );

                            $usersDonationsModel->insert($insert);

                            $user       = \Component\User::getInstance($currentUser['id']);

                            if($user->getRole() != 'guest')
                            {
                                $user->giveBadge(9500);
                            }
                        }
                        catch(\Zend_Db_Exception $e)
                        {
                            if(strpos($e->getMessage(), '1062 Duplicate') !== false) // This one is already in our system!
                            {

                            }
                            else
                            {
                                if(defined('APPLICATION_SENTRY') && APPLICATION_SENTRY === true)
                                {
                                    \Sentry\captureException($e);
                                }
                            }
                        }
                    }
                }
                else
                {
                    if(!in_array($line[1], $missingEmails))
                    {
                        $missingEmails[] = $line[1];

                        if(defined('APPLICATION_SENTRY') && APPLICATION_SENTRY === true)
                        {
                            \Sentry\State\Hub::getCurrent()->configureScope(function (\Sentry\State\Scope $scope) use ($line): void {
                                $scope->setExtra('patreon', $line);
                            });
                            \Sentry\captureMessage('Patreon user not found?');
                        }
                    }
                }
            }
        }
    }
}