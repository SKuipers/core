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
use Gibbon\Contracts\Services\Session as SessionContract;
use Gibbon\View\View;

/**
 * MessageSender
 *
 * @version v18
 * @since   v18
 */
class MessageSender
{
    protected $mail;
    protected $sms;
    protected $view;
    protected $data;

    public function __construct(MailerContract $mail, SMSContract $sms)
    {
        $this->mail = $mail;
        $this->sms = $sms;
    }

    public function setView($templateEngine, SessionContract $session)
    {
        $this->view = new View($templateEngine);
        $this->data = [
            'systemName'       => $session->get('systemName'),
            'organisationName' => $session->get('organisationName'),
            'organisationEmail' => $session->get('organisationEmail'),
        ];
        
        return $this;
    }

    public function send(array $recipients, Message $message)
    {
        $via = $message->via();
        $result = false;

        if (in_array('mail', $via) && !empty($this->mail)) {
            $body = $this->view->fetchFromTemplate('mail/message.twig.html', array_merge($this->data, [
                'greeting' => $message->toSubject(),
                'body'     => $message->toMail(),
                'button'   => $message->toLink(),
            ]));

            $this->mail->Subject = $message->toSubject();
            $this->mail->Body = $body;
            $this->mail->SetFrom($this->data['organisationEmail'], $this->data['organisationName']);
            $this->mail->clearAllRecipients();

            foreach ($recipients as $person) {
                if (!empty($person['email'])) {
                    $this->mail->AddBcc($person['email']);
                }
            }

            $result = $this->mail->Send();
        }

        if (in_array('sms', $via) && !empty($this->sms)) {
            $phoneNumbers = array_filter(array_map(function ($person) {
                return ($person['phone1CountryCode'] ?? '').($person['phone1'] ?? '');
            }, $recipients));

            $result = $this->sms
                ->content($message->toSMS())
                ->send($phoneNumbers);
        }

        return !empty($result);
    }
}
