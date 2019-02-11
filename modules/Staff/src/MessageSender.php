<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Gibbon\Module\Staff;

use Gibbon\Contracts\Comms\Mailer as MailerContract;
use Gibbon\Contracts\Comms\SMS as SMSContract;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Domain\System\NotificationGateway;

/**
 * MessageSender
 *
 * @version v18
 * @since   v18
 */
class MessageSender
{
    protected $notificationGateway;
    protected $userGateway;
    protected $settings;
    protected $mail;
    protected $sms;
    protected $via;

    public function __construct(SettingGateway $settingGateway, MailerContract $mail, SMSContract $sms, NotificationGateway $notificationGateway, UserGateway $userGateway)
    {
        $this->settings = [
            'absoluteURL' => $settingGateway->getSettingByScope('System', 'absoluteURL'),
        ];
        $this->notificationGateway = $notificationGateway;
        $this->userGateway = $userGateway;
        $this->mail = $mail;
        $this->sms = $sms;
    }

    public function send(array $recipients, Message $message)
    {
        $via = $message->via();
        $result = [];

        // Get the user data per gibbonPersonID
        $userGateway = &$this->userGateway;
        $recipients = array_map(function ($gibbonPersonID) use (&$userGateway) {
            return $userGateway->getByID($gibbonPersonID);
        }, array_filter($recipients));

        // Send SMS
        if (in_array('sms', $via) && !empty($this->sms)) {
            $phoneNumbers = array_map(function ($person) {
                return ($person['phone1CountryCode'] ?? '').($person['phone1'] ?? '');
            }, $recipients);

            $sent = $this->sms
                ->content($message->toSMS()."\n".'['.$this->settings['absoluteURL'].']')
                ->send($phoneNumbers);

            $result['sms'] = is_array($sent) ? count($sent) : $sent;
        }

        // Send Mail
        if (in_array('mail', $via) && !empty($this->mail)) {
            $this->mail->setDefaultSender($message->toMail()['subject']);
            $this->mail->renderBody('mail/message.twig.html', $message->toMail());

            foreach ($recipients as $person) {
                if (empty($person['email'])) continue;

                $this->mail->clearAllRecipients();
                $this->mail->AddAddress($person['email']);

                if ($this->mail->Send()) {
                    $result['mail'] = ($result['mail'] ?? 0) + 1;
                }
            }
        }

        // Add database notification
        if (in_array('database', $via)) {
            foreach ($recipients as $person) {
                $notification = $message->toDatabase() + ['gibbonPersonID' => $person['gibbonPersonID']];
                $row = $this->notificationGateway->selectNotificationByStatus($notification, 'New')->fetch();

                if (!empty($row)) {
                    $this->notificationGateway->updateNotificationCount($row['gibbonNotificationID'], $row['count']+1);
                } else {
                    $this->notificationGateway->insertNotification($notification);
                }

                $result['database'] = ($result['database'] ?? 0) + 1;
            }
        }

        return $result;
    }
}
