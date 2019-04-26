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
use Gibbon\Domain\Staff\StaffCoverageGateway;
use Gibbon\Data\BackgroundProcess;

require_once '../../gibbon.php';

$gibbonStaffAbsenceID = $_POST['gibbonStaffAbsenceID'] ?? '';

$URL = $gibbon->session->get('absoluteURL').'/index.php?q=/modules/Staff/coverage_request.php&gibbonStaffAbsenceID='.$gibbonStaffAbsenceID;
$URLSuccess = $gibbon->session->get('absoluteURL').'/index.php?q=/modules/Staff/coverage_view_edit.php&gibbonStaffAbsenceID='.$gibbonStaffAbsenceID;

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_request.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);

    $requestDates = $_POST['requestDates'] ?? [];
    $substituteTypes = $_POST['substituteTypes'] ?? [];

    $data = [
        'gibbonStaffAbsenceID'   => $gibbonStaffAbsenceID,
        'gibbonSchoolYearID'     => $gibbon->session->get('gibbonSchoolYearID'),
        'gibbonPersonIDStatus'   => $gibbon->session->get('gibbonPersonID'),
        'gibbonPersonIDCoverage' => $_POST['gibbonPersonIDCoverage'] ?? null,
        'notesStatus'            => $_POST['notesStatus'] ?? '',
        'requestType'            => $_POST['requestType'] ?? '',
        'substituteTypes'        => implode(',', $substituteTypes),
        'status'                 => 'Requested',
        'notificationSent'       => 'N',
    ];

    // Validate the required values are present
    if (empty($data['gibbonStaffAbsenceID']) || !($data['requestType'] == 'Individual' || $data['requestType'] == 'Broadcast')) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $absence = $container->get(StaffAbsenceGateway::class)->getByID($data['gibbonStaffAbsenceID']);

    if (empty($absence)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }
    
    if ($data['requestType'] == 'Individual') {
        // Return a custom error message if no dates have been selected
        if (empty($requestDates)) {
            $URL .= '&return=error8';
            header("Location: {$URL}");
            exit;
        }

        // Ensure the person is selected & exists for Individual coverage requests
        $personCoverage = $container->get(UserGateway::class)->getByID($data['gibbonPersonIDCoverage']);
        if (empty($personCoverage)) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        }
    }

    // Create the coverage request
    $gibbonStaffCoverageID = $staffCoverageGateway->insert($data);
    $gibbonStaffCoverageID = str_pad($gibbonStaffCoverageID, 14, '0', STR_PAD_LEFT);

    if (!$gibbonStaffCoverageID) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $partialFail = false;

    $absenceDates = $staffAbsenceDateGateway->selectDatesByAbsence($data['gibbonStaffAbsenceID']);

    // Link each absence date to the coverage request
    foreach ($absenceDates as $date) {
        if ($data['requestType'] == 'Broadcast' || in_array($date['date'], $requestDates)) {
            $updated = $staffAbsenceDateGateway->update($date['gibbonStaffAbsenceDateID'], [
                'gibbonStaffCoverageID' => $gibbonStaffCoverageID,
            ]);
            $partialFail &= !$updated;
        }
    }

    // Send messages (Mail, SMS) to relevant users
    $processType = 'Coverage'.$data['requestType'];
    $process = new BackgroundProcess($gibbon->session->get('absolutePath').'/uploads/background');
    $process->startProcess('staffNotification', __DIR__.'/notification_backgroundProcess.php', [$processType, $gibbonStaffCoverageID]);

    
    $URLSuccess .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URLSuccess}&gibbonStaffCoverageID={$gibbonStaffCoverageID}");
    exit;
}
