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

use Gibbon\Domain\Staff\StaffCoverageGateway;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;

require_once '../../gibbon.php';

$gibbonStaffCoverageID = $_GET['gibbonStaffCoverageID'] ?? '';

$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/coverage_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_manage_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} elseif (empty($gibbonStaffCoverageID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);
    $values = $staffCoverageGateway->getByID($gibbonStaffCoverageID);

    if (empty($values)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $absenceDates = $staffAbsenceDateGateway->selectDatesByCoverage($gibbonStaffCoverageID)->fetchAll();
    $partialFail = false;

    
    foreach ($absenceDates as $date) {
        if (!empty($date['gibbonStaffAbsenceID'])) {
            // Unlink any absence dates from the coverage request
            $updated = $staffAbsenceDateGateway->update($date['gibbonStaffAbsenceDateID'], [
                'gibbonStaffCoverageID' => null,
            ]);
        } else {
            // Otherwise remove the date (it's not linked to an absence)
            $updated = $staffAbsenceDateGateway->delete($date['gibbonStaffAbsenceDateID']);
        }
        
        $partialFail &= !$updated;
    }

    // Then delete the coverage itself
    $partialFail &= $staffCoverageGateway->delete($gibbonStaffCoverageID);

    $URL .= $partialFail
        ? '&return=warning1'
        : '&return=success0';

    header("Location: {$URL}");
    exit;
}
