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
use Gibbon\Comms\NotificationEvent;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Domain\Staff\StaffCoverageGateway;

require_once '../../gibbon.php';

$gibbonStaffAbsenceID = $_POST['gibbonStaffAbsenceID'] ?? '';

$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/absences_view_details.php&gibbonStaffAbsenceID='.$gibbonStaffAbsenceID;

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_request.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);

    $data = [
        'gibbonStaffAbsenceID'    => $gibbonStaffAbsenceID,
        'gibbonPersonIDCoverage'  => $_POST['gibbonPersonIDCoverage'] ?? null,
        'gibbonPersonIDRequested' => $_SESSION[$guid]['gibbonPersonID'],
        'notesRequested'          => $_POST['notesRequested'],
        'status'                  => 'Requested',
    ];

    // Validate the required values are present
    if (empty($data['gibbonStaffAbsenceID'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $absence = $container->get(StaffAbsenceGateway::class)->getByID($data['gibbonStaffAbsenceID']);
    // $person = $container->get(UserGateway::class)->getByID($data['gibbonPersonIDCoverage']);

    if (empty($absence)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    if (empty($data['gibbonPersonIDCoverage'])) {
        $data['gibbonPersonIDCoverage'] = null;
    }

    // Create the coverage request
    $gibbonStaffCoverageID = $staffCoverageGateway->insert($data);

    if (!$gibbonStaffCoverageID) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $partialFail = false;

    $absenceDates = $staffAbsenceDateGateway->selectDatesByAbsence($data['gibbonStaffAbsenceID']);
    $requestDates = $_POST['requestDates'] ?? [];

    // Link each absence date to the coverage request
    foreach ($absenceDates as $date) {
        if (in_array($date['date'], $requestDates)) {
            $updated = $staffAbsenceDateGateway->update($date['gibbonStaffAbsenceDateID'], [
                'gibbonStaffCoverageID' => $gibbonStaffCoverageID,
            ]);
            $partialFail &= !$updated;
        }
    }

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URL}");
    exit;
}
