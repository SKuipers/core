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
use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Domain\Staff\StaffCoverageGateway;
use Gibbon\Module\Staff\MessageSender;
use Gibbon\Module\Staff\Messages\CoverageDeclined;
use Gibbon\Domain\Staff\SubstituteGateway;

require_once '../../gibbon.php';

$gibbonStaffCoverageID = $_POST['gibbonStaffCoverageID'] ?? '';

$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/coverage_view_decline.php&gibbonStaffCoverageID='.$gibbonStaffCoverageID;
$URLSuccess = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/coverage_view.php';

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_view_decline.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);
    $substituteGateway = $container->get(SubstituteGateway::class);

    $markAsUnavailable = $_POST['markAsUnavailable'] ?? false;

    $data = [
        'timestampCoverage'      => date('Y-m-d H:i:s'),
        'notesCoverage'          => $_POST['notesCoverage'],
        'status'                 => 'Declined',
    ];

    // Validate the required values are present
    if (empty($gibbonStaffCoverageID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $coverage = $staffCoverageGateway->getByID($gibbonStaffCoverageID);

    if (empty($coverage)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $absence = $container->get(StaffAbsenceGateway::class)->getByID($coverage['gibbonStaffAbsenceID']);

    if (empty($absence)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Prevent two people declining at the same time (?)
    if ($coverage['status'] != 'Requested' || empty($coverage['gibbonPersonIDCoverage'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Update the database
    $updated = $staffCoverageGateway->update($gibbonStaffCoverageID, $data);

    if (!$updated) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $partialFail = false;

    $coverage = $staffCoverageGateway->getCoverageDetailsByID($gibbonStaffCoverageID);
    $absenceDates = $staffAbsenceDateGateway->selectDatesByCoverage($gibbonStaffCoverageID);

    // Unlink any absence dates from the coverage request so they can be re-requested
    foreach ($absenceDates as $date) {
        $updated = $staffAbsenceDateGateway->update($date['gibbonStaffAbsenceDateID'], [
            'gibbonStaffCoverageID' => null,
        ]);
        $partialFail &= !$updated;

        if ($markAsUnavailable) {
            $substituteGateway->insertUnavailability([
                'gibbonPersonID' => $coverage['gibbonPersonID'],
                'date'           => $date['date'],
                'allDay'         => 'Y',
            ]);
        }
    }

    $urgencyThreshold = getSettingByScope($connection2, 'Staff', 'urgencyThreshold') * 86400;
    $relativeSeconds = strtotime($coverage['dateStart']) - time();
    $coverage['urgent'] = $relativeSeconds <= $urgencyThreshold;

    // Send messages (Mail, SMS) to relevant users
    $recipients = [$absence['gibbonPersonID']];
    $message = new CoverageDeclined($coverage);

    $sent = $container
        ->get(MessageSender::class)
        ->send($recipients, $message);


    $URLSuccess .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URLSuccess}");
    exit;
}
