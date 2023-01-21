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

use Gibbon\Data\Validator;
use Gibbon\Domain\Staff\StaffDutyPersonGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Staff/staff_duty_edit.php';

if (isActionAccessible($guid, $connection2, '/modules/Staff/staff_duty_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!

    $gibbonStaffDutyPersonID = $_POST['gibbonStaffDutyPersonID'] ?? null;
    $data = [
        'gibbonStaffDutyID' => $_POST['gibbonStaffDutyID'] ?? null,
        'gibbonDaysOfWeekID' => $_POST['gibbonDaysOfWeekID'] ?? null,
    ];

    if (empty($gibbonStaffDutyPersonID) || empty($data['gibbonDaysOfWeekID']) || empty($data['gibbonStaffDutyID'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    }

    $staffDutyPersonGateway = $container->get(StaffDutyPersonGateway::class);
    $updated = $staffDutyPersonGateway->update($gibbonStaffDutyPersonID, $data);

    echo $updated;
}
