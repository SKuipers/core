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

use Gibbon\Services\Format;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Domain\Staff\StaffAbsenceTypeGateway;
use Gibbon\Data\BackgroundProcess;

require_once '../../gibbon.php';

$gibbonPersonID = $_POST['gibbonPersonID'] ?? '';

$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/absences_manage_add.php';
$URLSuccess = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/absences_view_byPerson.php&gibbonPersonID='.$gibbonPersonID;

if (isActionAccessible($guid, $connection2, '/modules/Staff/absences_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);

    $dateStart = $_POST['dateStart'] ?? '';
    $dateEnd = $_POST['dateEnd'] ?? '';
    $notificationList = !empty($_POST['notificationList'])? explode(',', $_POST['notificationList']) : [];

    $data = [
        'gibbonSchoolYearID'       => $gibbon->session->get('gibbonSchoolYearID'),
        'gibbonPersonID'           => $_POST['gibbonPersonID'] ?? '',
        'gibbonStaffAbsenceTypeID' => $_POST['gibbonStaffAbsenceTypeID'] ?? '',
        'reason'                   => $_POST['reason'] ?? '',
        'comment'                  => $_POST['comment'] ?? '',
        'gibbonPersonIDCreator'    => $_SESSION[$guid]['gibbonPersonID'],
        'notificationSent'         => 'N',
        'notificationList'         => json_encode($notificationList),
    ];

    // Validate the required values are present
    if (empty($data['gibbonStaffAbsenceTypeID']) || empty($data['gibbonPersonID']) || empty($dateStart) || empty($dateEnd)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $type = $container->get(StaffAbsenceTypeGateway::class)->getByID($data['gibbonStaffAbsenceTypeID']);
    $person = $container->get(UserGateway::class)->getByID($data['gibbonPersonID']);

    if (empty($type) || empty($person)) {
        $URL .= '&return=error2';
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
        $dateData = [
            'gibbonStaffAbsenceID' => $gibbonStaffAbsenceID,
            'date'                 => $date->format('Y-m-d'),
            'allDay'               => $_POST['allDay'] ?? '',
            'timeStart'            => $_POST['timeStart'] ?? null,
            'timeEnd'              => $_POST['timeEnd'] ?? null,
        ];

        if (!isSchoolOpen($guid, $dateData['date'], $connection2)) {
            continue;
        }

        if ($staffAbsenceDateGateway->unique($dateData, ['gibbonStaffAbsenceID', 'date'])) {
            $partialFail &= !$staffAbsenceDateGateway->insert($dateData);
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

    // Start a background process for notifications
    $process = new BackgroundProcess($gibbon->session->get('absolutePath').'/uploads/background');
    $process->startProcess('staffNotification', __DIR__.'/notification_backgroundProcess.php', ['NewAbsence', $gibbonStaffAbsenceID]);


    // Redirect to coverage request
    $coverage = $_POST['coverage'] ?? 'N';

    if ($coverage != 'N') {
        $gibbonStaffAbsenceID = str_pad($gibbonStaffAbsenceID, 14, '0', STR_PAD_LEFT);
        $URL = $_SESSION[$guid]['absoluteURL']."/index.php?q=/modules/Staff/coverage_request.php&coverage=$coverage&gibbonStaffAbsenceID=$gibbonStaffAbsenceID";

        $URL .= '&return=success1';
        header("Location: {$URL}");
        exit;
    }

    $URLSuccess .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URLSuccess}");
    exit;
}
