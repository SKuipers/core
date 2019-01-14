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

require_once '../../gibbon.php';

$gibbonPersonIDCoverage = $_GET['gibbonPersonIDCoverage'] ?? '';
$gibbonStaffCoverageExceptionID = $_GET['gibbonStaffCoverageExceptionID'] ?? '';

$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/coverage_availability.php&gibbonPersonIDCoverage='.$gibbonPersonIDCoverage;

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_availability.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} elseif (empty($gibbonPersonIDCoverage) || empty($gibbonStaffCoverageExceptionID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);

    if ($gibbonPersonIDCoverage != $_SESSION[$guid]['gibbonPersonID']) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    $deleted = $staffCoverageGateway->deleteCoverageException($gibbonStaffCoverageExceptionID);

    $URL .= !$deleted
        ? '&return=error2'
        : '&return=success0';

    header("Location: {$URL}");
    exit;
}
