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

use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Domain\System\SettingGateway;
use Google_Service_Calendar_Event;
use Google_Service_Calendar;
use Gibbon\Services\Format;
use DateInterval;
use DateTime;

/**
 * AbsenceCalendarSync
 *
 * @version v18
 * @since   v18
 */
class AbsenceCalendarSync
{
    protected $calendarService;
    protected $staffAbsenceGateway;
    protected $googleCalendarID;
    protected $timezone;

    public function __construct(StaffAbsenceGateway $staffAbsenceGateway, SettingGateway $settingGateway, Google_Service_Calendar $calendarService = null)
    {
        $this->calendarService = $calendarService;
        $this->staffAbsenceGateway = $staffAbsenceGateway;
        $this->googleCalendarID = $settingGateway->getSettingByScope('Staff', 'absenceGoogleCalendarID');
        $this->timezone = $settingGateway->getSettingByScope('System', 'timezone');
    }

    public function addCalendarAbsence($gibbonStaffAbsenceID)
    {
        if (!$this->googleCalendarID || !$this->calendarService) {
            return false;
        }

        $absence = $this->staffAbsenceGateway->getAbsenceDetailsByID($gibbonStaffAbsenceID);
        $event = new Google_Service_Calendar_Event($this->getEventData($absence));

        if ($event = $this->calendarService->events->insert($this->googleCalendarID, $event)) {
            $this->staffAbsenceGateway->update($gibbonStaffAbsenceID, [
                'googleCalendarEventID' => $event->id,
            ]);
        }

        !empty($event);
    }

    public function updateCalendarAbsence($gibbonStaffAbsenceID)
    {
        if (!$this->googleCalendarID || !$this->calendarService) {
            return false;
        }

        $absence = $this->staffAbsenceGateway->getAbsenceDetailsByID($gibbonStaffAbsenceID);

        if (!empty($absence['googleCalendarEventID'])) {
            $event = new Google_Service_Calendar_Event($this->getEventData($absence));
            $event = $this->calendarService->events->update($this->googleCalendarID, $absence['googleCalendarEventID'], $event);

            return !empty($event);
        }
    }

    public function deleteCalendarAbsence($gibbonStaffAbsenceID)
    {
        if (!$this->googleCalendarID || !$this->calendarService) {
            return false;
        }

        $absence = $this->staffAbsenceGateway->getAbsenceDetailsByID($gibbonStaffAbsenceID);

        if (!empty($absence['googleCalendarEventID'])) {
            $event = $this->calendarService->events->delete($this->googleCalendarID, $absence['googleCalendarEventID']);

            return !empty($event);
        }
    }

    protected function getEventData($absence)
    {
        $fullName = Format::name($absence['titleAbsence'], $absence['preferredNameAbsence'], $absence['surnameAbsence'], 'Staff', false, true);
        $eventData = [
            'summary'     => $fullName.' - '.$absence['type'],
            'description' => $absence['type'],
            'start'       => ['timeZone' => $this->timezone],
            'end'         => ['timeZone' => $this->timezone],
            'reminders'   => ['useDefault' => false],
        ];

        if ($absence['allDay'] == 'Y') {
            $eventData['start']['date'] = $absence['dateStart'];
            $eventData['end']['date'] = (new DateTime($absence['dateEnd']))->add(new DateInterval('P1D'))->format('Y-m-d');
        } else {
            $eventData['start']['dateTime'] = (new DateTime($absence['dateStart'].' '.$absence['timeStart']))->format('c');
            $eventData['end']['dateTime'] = (new DateTime($absence['dateEnd'].' '.$absence['timeEnd']))->format('c');
        }

        return $eventData;
    }
}
