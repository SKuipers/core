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
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Domain\System\SettingGateway;
use Google_Service_Calendar_Event;
use Google_Service_Calendar;
use Gibbon\Services\Format;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * AbsenceCalendarSync
 *
 * @version v18
 * @since   v18
 */
class AbsenceCalendarSync
{
    protected $staffAbsenceGateway;
    protected $staffAbsenceDateGateway;
    protected $calendarService;
    protected $googleCalendarID;
    protected $timezone;

    public function __construct(StaffAbsenceGateway $staffAbsenceGateway, StaffAbsenceDateGateway $staffAbsenceDateGateway, SettingGateway $settingGateway, Google_Service_Calendar $calendarService = null)
    {
        $this->staffAbsenceGateway = $staffAbsenceGateway;
        $this->staffAbsenceDateGateway = $staffAbsenceDateGateway;
        $this->calendarService = $calendarService;
        $this->googleCalendarID = $settingGateway->getSettingByScope('Staff', 'absenceGoogleCalendarID');
        $this->timezone = $settingGateway->getSettingByScope('System', 'timezone');
    }

    public function insertCalendarAbsence($gibbonStaffAbsenceID)
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

        $dateStart = new DateTimeImmutable($absence['dateStart'].' '.$absence['timeStart'], new DateTimeZone($this->timezone));
        $dateEnd = new DateTimeImmutable($absence['dateEnd'].' '.$absence['timeEnd'], new DateTimeZone($this->timezone));

        if ($absence['allDay'] == 'Y') {
            $eventData['start']['date'] = $dateStart->format('Y-m-d');
            $eventData['end']['date'] = $dateEnd->add(new DateInterval('P1D'))->format('Y-m-d');
        } else {
            $eventData['start']['dateTime'] = $dateStart->format('c');
            $eventData['end']['dateTime'] = $dateEnd->format('c');
        }

        $totalDays = $dateStart->diff($dateEnd)->format('%a') + 1;

        // If the time span does not equal the actual number of days absent, we likely have a recurring event
        if ($totalDays != $absence['days']) {
            $eventData['recurrence'] = [];
            $absenceDates = $this->staffAbsenceDateGateway->selectDatesByAbsence($absence['gibbonStaffAbsenceID'])->fetchAll();

            foreach ($absenceDates as $date) {
                if ($date['date'] == $dateStart->format('Y-m-d')) continue;

                $dateObject = new DateTimeImmutable($date['date'].' '.$date['timeStart']);
                $eventData['recurrence'][] = 'RDATE:'.$dateObject->format('Ymd\THis');
            }

            if ($absence['allDay'] == 'Y') {
                $eventData['end']['date'] = $dateStart->format('Y-m-d');
            } else {
                $eventData['end']['dateTime'] = $dateStart->setTime($dateEnd->format('H'), $dateEnd->format('i'))->format('c');
            }
        }

        return $eventData;
    }
}
