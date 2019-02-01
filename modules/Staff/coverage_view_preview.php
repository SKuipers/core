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

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;

include './modules/Timetable/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_view.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $gibbonStaffAbsenceDateID = $_GET['gibbonStaffAbsenceDateID'] ?? '';

    if (empty($gibbonStaffAbsenceDateID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);
    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);

    $absenceDate = $staffAbsenceDateGateway->getByID($gibbonStaffAbsenceDateID);

    if (empty($absenceDate)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $absence = $staffAbsenceGateway->getByID($absenceDate['gibbonStaffAbsenceID']);

    $q = $_GET['q'].='&gibbonStaffAbsenceDateID='.$gibbonStaffAbsenceDateID;
    $startDayStamp = strtotime($absenceDate['date']);
    $params = '';

    echo renderTT($guid, $connection2, $absence['gibbonPersonID'], '', 'Foo', $startDayStamp, $_GET['q'], $params,'full', false);

    // renderTTDay($guid, $connection2, $gibbonTTID, $schoolOpen, $startDayStamp, $count, $daysInWeek, $gibbonPersonID, $gridTimeStart, $eventsSchool, $eventsPersonal, $eventsSpaceBooking, $diffTime, $maxAllDays, $narrow, $specialDayStart = '', $specialDayEnd = '', $edit = false);
}
