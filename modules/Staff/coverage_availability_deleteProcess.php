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

use Gibbon\Domain\Staff\SubstituteGateway;

require_once '../../gibbon.php';

$gibbonPersonID = $_REQUEST['gibbonPersonID'] ?? '';
$gibbonSubstituteUnavailableID = $_REQUEST['gibbonSubstituteUnavailableID'] ?? '';

$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/coverage_availability.php&gibbonPersonID='.$gibbonPersonID;

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_availability.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} elseif (empty($gibbonPersonID) || empty($gibbonSubstituteUnavailableID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $substituteGateway = $container->get(SubstituteGateway::class);

    if ($gibbonPersonID != $_SESSION[$guid]['gibbonPersonID']) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    $exceptionList = is_array($gibbonSubstituteUnavailableID)? $gibbonSubstituteUnavailableID : [$gibbonSubstituteUnavailableID];
    $partialFail = false;

    foreach ($exceptionList as $exceptionID) {
        $deleted = $substituteGateway->deleteUnavailability($exceptionID);
        $partialFail &= !$deleted;
    }

    $URL .= $partialFail
        ? '&return=warning1'
        : '&return=success0';

    header("Location: {$URL}");
    exit;
}
