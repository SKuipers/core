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

/**
 * Message
 *
 * @version v18
 * @since   v18
 */
abstract class Message
{
    abstract public function getTitle() : string;
    abstract public function getText() : string;
    abstract public function getModule() : string;

    public function getAction() : string
    {
        return '';
    }

    public function getLink() : string
    {
        return '';
    }

    public function getDetails() : array
    {
        return [];
    }

    public function via() : array
    {
        return ['mail'];
    }

    public function toSMS() : string
    {
        return $this->getText();
    }

    public function toMail() : array
    {
        return [
            'subject' => $this->getTitle(),
            'title'   => $this->getTitle(),
            'body'    => $this->getText(),
            'details' => array_filter($this->getDetails()),
            'button'  => [
                'url'  => $this->getLink(),
                'text' => $this->getAction(),
            ],
        ];
    }

    public function toDatabase() : array
    {
        return [
            'text'       => $this->getText(),
            'moduleName' => $this->getModule(),
            'actionLink' => '/'.$this->getLink(),
        ];
    }
}
