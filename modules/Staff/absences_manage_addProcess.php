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
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Services\Format;

require_once '../../gibbon.php';

$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/absences_manage_add.php';

if (isActionAccessible($guid, $connection2, '/modules/Staff/absences_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);

    $dateStart = $_POST['dateStart'] ?? '';
    $timeStart = $_POST['timeStart'] ?? '';
    $dateEnd = $_POST['dateEnd'] ?? '';
    $timeEnd = $_POST['timeEnd'] ?? '';

    $data = [
        'gibbonStaffAbsenceTypeID' => $_POST['gibbonStaffAbsenceTypeID'] ?? '',
        'gibbonPersonID'           => $_POST['gibbonPersonID'] ?? '',
        'reason'                   => $_POST['reason'] ?? '',
        'comment'                  => $_POST['comment'] ?? '',
        'gibbonPersonIDCreator'    => $_SESSION[$guid]['gibbonPersonID'],
    ];

    if (empty($data['gibbonStaffAbsenceTypeID']) || empty($data['gibbonPersonID']) || empty($dateStart) || empty($dateEnd)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Create the absence
    $gibbonStaffAbsenceID = $staffAbsenceGateway->insert($data);

    if (!$gibbonStaffAbsenceID) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $start = new DateTime(Format::dateConvert($dateStart).' 00:00:00');
    $end = new DateTime(Format::dateConvert($dateEnd).' 23:00:00');

    $dateRange = new DatePeriod($start, new DateInterval('P1D'), $end);
    $partialFail = false;
    $absenceCount = 0;

    // Create separate dates within the absence time span
    foreach ($dateRange as $date) {
        $data = [
            'gibbonStaffAbsenceID' => $gibbonStaffAbsenceID,
            'date'                 => $date->format('Y-m-d'),
            'allDay'               => $_POST['allDay'] ?? '',
            'timeStart'            => $timeStart,
            'timeEnd'              => $timeEnd,
        ];

        if (!isSchoolOpen($guid, $data['date'], $connection2)) {
            continue;
        }

        if ($staffAbsenceDateGateway->unique($data, ['gibbonStaffAbsenceID', 'date'])) {
            $partialFail &= !$staffAbsenceDateGateway->insert($data);
            $absenceCount++;
        } else {
            $partialFail = true;
        }
    }

    if ($absenceCount == 0) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $URL .= $partialFail || $absenceCount == 0
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URL}");
    exit;
}
