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
use Gibbon\Module\Staff\Messages\CoverageAccepted;
use Gibbon\Module\Staff\Messages\CoveragePartial;

require_once '../../gibbon.php';

$gibbonStaffCoverageID = $_POST['gibbonStaffCoverageID'] ?? '';

$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/coverage_view_accept.php&gibbonStaffCoverageID='.$gibbonStaffCoverageID;
$URLSuccess = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/coverage_view.php';

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_view_accept.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);

    $coverageDates = $_POST['coverageDates'] ?? [];

    $data = [
        'gibbonPersonIDCoverage' => $_SESSION[$guid]['gibbonPersonID'],
        'timestampCoverage'      => date('Y-m-d H:i:s'),
        'notesCoverage'          => $_POST['notesCoverage'],
        'status'                 => 'Accepted',
    ];

    // Validate the required values are present
    if (empty($gibbonStaffCoverageID) || empty($data['gibbonPersonIDCoverage']) || empty($coverageDates)) {
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

    // Prevent two people accepting at the same time
    if ($coverage['status'] != 'Requested') {
        $URL .= '&return=warning3';
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
    $uncoveredDates = [];

    // Unlink any absence dates from the coverage request if they were not selected
    foreach ($absenceDates as $date) {
        if (!in_array($date['date'], $coverageDates)) {
            $uncoveredDates[] = $date['date'];
            $updated = $staffAbsenceDateGateway->update($date['gibbonStaffAbsenceDateID'], [
                'gibbonStaffCoverageID' => null,
            ]);
            $partialFail &= !$updated;
        }
    }

    // Send messages (Mail, SMS) to relevant users
    $recipients = [$absence['gibbonPersonID']];
    $message = !empty($uncoveredDates)
        ? new CoveragePartial($coverage, $coverageDates, $uncoveredDates)
        : new CoverageAccepted($coverage);

    $sent = $container
        ->get(MessageSender::class)
        ->send($recipients, $message);


    $URLSuccess .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URLSuccess}&sent=$sent");
    exit;
}
