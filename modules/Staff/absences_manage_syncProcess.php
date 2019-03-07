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

use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Module\Staff\AbsenceCalendarSync;

$_POST['address'] = '/modules/Staff/absences_manage.php';

require_once '../../gibbon.php';

$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/absences_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/Staff/absences_manage.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    // Proceed!
    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    
    $criteria = $staffAbsenceGateway->newQueryCriteria()
        ->filterBy('dateStart', date('Y-m-d'))
        ->filterBy('status', 'Approved')
        ->pageSize(0);

    $absences = $staffAbsenceGateway->queryAbsencesBySchoolYear($criteria, $gibbon->session->get('gibbonSchoolYearID'), true);

    $partialFail = false;

    if ($calendarSync = $container->get(AbsenceCalendarSync::class)) {
        foreach ($absences as $absence) {
            if (!empty($absence['googleCalendarEventID'])) {
                $calendarSync->updateCalendarAbsence($absence['gibbonStaffAbsenceID']);
            } else {
                $calendarSync->insertCalendarAbsence($absence['gibbonStaffAbsenceID']);
            }
        }
    } else {
        $partialFail = true;
    }

    $URL .= $partialFail
        ? '&return=warning1'
        : '&return=success0';

    header("Location: {$URL}");
    exit;
}
