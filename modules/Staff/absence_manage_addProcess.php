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
use Gibbon\Services\Format;

require_once '../../gibbon.php';

$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/absence_manage_add.php';

if (isActionAccessible($guid, $connection2, '/modules/Staff/absence_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    // Proceed!
    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);

    $dateStart = $_POST['dateStart'] ?? '';
    $timeStart = $_POST['timeStart'] ?? '';
    $dateEnd = $_POST['dateEnd'] ?? '00:00:00';
    $timeEnd = $_POST['timeEnd'] ?? '23:00:00';

    $data = [
        'gibbonStaffAbsenceTypeID' => $_POST['gibbonStaffAbsenceTypeID'] ?? '',
        'gibbonPersonID'           => $_POST['gibbonPersonID'] ?? '',
        'reason'                   => $_POST['reason'] ?? '',
        'comment'                  => $_POST['comment'] ?? '',
        'allDay'                   => $_POST['allDay'] ?? '',
        'gibbonPersonIDCreator'    => $_SESSION[$guid]['gibbonPersonID'],
    ];

    if (empty($data['gibbonStaffAbsenceTypeID']) || empty($data['gibbonPersonID']) || empty($dateStart) || empty($dateEnd)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $start = new DateTime(Format::dateConvert($_POST['dateStart']).' '.$timeStart);
    $end = new DateTime(Format::dateConvert($_POST['dateEnd']).' '.$timeEnd);

    $data['timestampStart'] = $start->format('Y-m-d H:i:s');
    $data['timestampEnd'] = $end->format('Y-m-d H:i:s');

    $dateRange = new DatePeriod($start, new DateInterval('P1D'), $end);
    $partialFail = false;
    $absenceCount = 0;

    foreach ($dateRange as $date) {
        $data['date'] = $date->format('Y-m-d');

        if (!isSchoolOpen($guid, $data['date'], $connection2)) {
            continue;
        }

        if ($staffAbsenceGateway->unique($data, ['gibbonPersonID', 'date'])) {
            $partialFail &= !$staffAbsenceGateway->insert($data);
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
