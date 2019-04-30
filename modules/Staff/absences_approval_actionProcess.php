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

use Gibbon\Data\BackgroundProcess;
use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Module\Staff\AbsenceCalendarSync;

require_once '../../gibbon.php';

$gibbonStaffAbsenceID = $_POST['gibbonStaffAbsenceID'] ?? '';

$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/absences_approval_action.php&gibbonStaffAbsenceID='.$gibbonStaffAbsenceID;
$URLSuccess = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/absences_approval.php';

if (isActionAccessible($guid, $connection2, '/modules/Staff/absences_approval_action.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} elseif (empty($gibbonStaffAbsenceID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $absence = $staffAbsenceGateway->getByID($gibbonStaffAbsenceID);

    if (empty($absence)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    if ($absence['gibbonPersonIDApproval'] != $_SESSION[$guid]['gibbonPersonID']) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    $status = $_POST['status'] ?? '';

    if (empty($status)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $data = [
        'status'            => $status,
        'timestampApproval' => date('Y-m-d H:i:s'),
        'notesApproval'     => $_POST['notesApproval'],
    ];

    $updated = $staffAbsenceGateway->update($gibbonStaffAbsenceID, $data);

    if (empty($updated)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Start a background process for notifications
    $process = new BackgroundProcess($gibbon->session->get('absolutePath').'/uploads/background');
    $process->startProcess('staffNotification', __DIR__.'/notification_backgroundProcess.php', ['AbsenceApproval', $gibbonStaffAbsenceID]);

    if ($status == 'Approved') {
        $process->startProcess('staffNotification', __DIR__.'/notification_backgroundProcess.php', ['NewAbsence', $gibbonStaffAbsenceID]);

        // Create a Google Calendar event
        if ($calendarSync = $container->get(AbsenceCalendarSync::class)) {
            $calendarSync->insertCalendarAbsence($gibbonStaffAbsenceID);
        }
    }

    $URLSuccess .= '&return=success0';

    header("Location: {$URLSuccess}");
    exit;
}
