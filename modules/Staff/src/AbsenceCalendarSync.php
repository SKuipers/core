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
use Google_Service_Exception;
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
        
        try {
            $event = new Google_Service_Calendar_Event($this->getEventData($absence));

            if ($event = $this->calendarService->events->insert($this->googleCalendarID, $event)) {
                $this->staffAbsenceGateway->update($gibbonStaffAbsenceID, [
                    'googleCalendarEventID' => $event->id,
                ]);
            }
        } catch (Google_Service_Exception $e) {
            return false;
        }

        return !empty($event);
    }

    public function updateCalendarAbsence($gibbonStaffAbsenceID)
    {
        if (!$this->googleCalendarID || !$this->calendarService) {
            return false;
        }

        $absence = $this->staffAbsenceGateway->getAbsenceDetailsByID($gibbonStaffAbsenceID);

        if (!empty($absence['googleCalendarEventID'])) {
            try {
                $event = new Google_Service_Calendar_Event($this->getEventData($absence));
                $event = $this->calendarService->events->update($this->googleCalendarID, $absence['googleCalendarEventID'], $event);
            } catch (Google_Service_Exception $e) {
                return false;
            }

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
            //'colorId'     => $this->getColorID($absence['sequenceNumber']),
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

    protected function getColorID($number)
    {
        $number = $number % 10;

        switch ($number) {
            case 0: return 3;
            case 1: return 4;
            case 2: return 5;
            case 3: return 1;
            case 4: return 7;
            case 5: return 6;
            case 6: return 4;
            case 7: return 2;
            case 8: return 3;
            case 9: return 9;
        }

        // bg-color0 rgba(153, 102, 255, 1.0) => 3 #dbadff
        // bg-color1 rgba(255, 99, 132, 1.0)  => 4 #ff887c
        // bg-color2 rgba(255, 206, 86, 1.0)  => 5 #fbd75b
        // bg-color3 rgba(54, 162, 235, 1.0)  => 1 #a4bdfc
        // bg-color4 rgba(133, 233, 194, 1.0) => 7 #46d6db
        // bg-color5 rgba(255, 159, 64, 1.0)  => 6 #ffb878
        // bg-color6 rgba(237, 85, 88, 1.0)   => 4 #ff887c
        // bg-color7 rgba(75, 192, 192, 1.0)  => 2 #7ae7bf
        // bg-color8 rgba(161, 89, 173, 1.0)  => 3 #dbadff
        // bg-color9 rgba(29, 109, 163, 1.0)  => 9 #5484ed
    }
}
