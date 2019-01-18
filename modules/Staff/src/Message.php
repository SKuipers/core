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
    abstract public function title() : string;
    abstract public function text() : string;
    abstract public function module() : string;
    abstract public function action() : string;
    abstract public function link() : string;

    public function via() : array
    {
        return ['mail'];
    }

    public function details() : array
    {
        return [];
    }

    public function toSMS() : string
    {
        return $this->text();
    }

    public function toMail() : array
    {
        return [
            'subject' => $this->title(),
            'title'   => $this->title(),
            'body'    => $this->text(),
            'details' => array_filter($this->details()),
            'button'  => [
                'url'  => $this->link(),
                'text' => $this->action(),
            ],
        ];
    }

    public function toNotification() : array
    {
        return [
            'text'       => $this->text(),
            'moduleName' => $this->module(),
            'actionLink' => $this->link(),
        ];
    }
}
