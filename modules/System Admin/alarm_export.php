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

use Gibbon\Domain\System\AlarmGateway;

include '../../gibbon.php';

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/System Admin/alarm.php';

if (isActionAccessible($guid, $connection2, '/modules/System Admin/alarm.php') == false) {
    // Access denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit();
} else {
    $alarmGateway = $container->get(AlarmGateway::class);
    $alarm = $alarmGateway->getLatestAlarm();

    if (empty($alarm)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit();
    } else {
        //Proceed!
        $result = $alarmGateway->selectAlarmConfirmationReadable($alarm['gibbonAlarmID']);

        $exp = new Gibbon\Excel();
        $exp->exportWithQuery($result, 'alarmConfirm.xls');
    }
}
