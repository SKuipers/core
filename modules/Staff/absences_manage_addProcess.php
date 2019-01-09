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
use Gibbon\Domain\Staff\StaffAbsenceTypeGateway;

require_once '../../gibbon.php';

$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/absences_manage_add.php';

if (isActionAccessible($guid, $connection2, '/modules/Staff/absences_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);

    $dateStart = $_POST['dateStart'] ?? '';
    $dateEnd = $_POST['dateEnd'] ?? '';

    $data = [
        'gibbonPersonID'           => $_POST['gibbonPersonID'] ?? '',
        'gibbonStaffAbsenceTypeID' => $_POST['gibbonStaffAbsenceTypeID'] ?? '',
        'reason'                   => $_POST['reason'] ?? '',
        'comment'                  => $_POST['comment'] ?? '',
        'gibbonPersonIDCreator'    => $_SESSION[$guid]['gibbonPersonID'],
    ];

    // Validate the required values are present
    if (empty($data['gibbonStaffAbsenceTypeID']) || empty($data['gibbonPersonID']) || empty($dateStart) || empty($dateEnd)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $type = $container->get(StaffAbsenceTypeGateway::class)->getByID($data['gibbonStaffAbsenceTypeID']);
    $person = $container->get(UserGateway::class)->getByID($data['gibbonPersonID']);

    if (empty($type) || empty($person)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Create the absence
    $gibbonStaffAbsenceID = $staffAbsenceGateway->insert($data);

    if (!$gibbonStaffAbsenceID) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $start = new DateTime(Format::dateConvert($dateStart).' 00:00:00');
    $end = new DateTime(Format::dateConvert($dateEnd).' 23:00:00');

    $dateRange = new DatePeriod($start, new DateInterval('P1D'), $end);
    $partialFail = false;
    $absenceCount = 0;

    // Create separate dates within the absence time span
    foreach ($dateRange as $date) {
        $dateData = [
            'gibbonStaffAbsenceID' => $gibbonStaffAbsenceID,
            'date'                 => $date->format('Y-m-d'),
            'allDay'               => $_POST['allDay'] ?? '',
            'timeStart'            => $_POST['timeStart'] ?? '',
            'timeEnd'              => $_POST['timeEnd'] ?? '',
        ];

        if (!isSchoolOpen($guid, $dateData['date'], $connection2)) {
            continue;
        }

        if ($staffAbsenceDateGateway->unique($dateData, ['gibbonStaffAbsenceID', 'date'])) {
            $partialFail &= !$staffAbsenceDateGateway->insert($dateData);
            $absenceCount++;
        } else {
            $partialFail = true;
        }
    }

    if ($absenceCount == 0) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Raise a new notification event
    $event = new NotificationEvent('Staff', 'New Staff Absence');
    
    $notificationText = __('A new staff absence has been recorded for {name}: {type} on {date}', [
        'name' => Format::nameList([$person], 'Staff', false, true),
        'type' => $data['reason'] ? "{$type['name']} ({$data['reason']})" : $type['name'],
        'date' => Format::dateRangeReadable($start->format('Y-m-d'), $end->format('Y-m-d')),
    ]);
    if (!empty($data['comment'])) {
        $notificationText .= '<br/><br/>'.__('Comment').': '.$data['comment'].'<br/>';
    }

    $event->setNotificationText($notificationText);
    $event->setActionLink('/index.php?q=/modules/Staff/absences_view_byPerson.php&gibbonPersonID='.$data['gibbonPersonID']);

    // Notify the target staff member, if they're not the one who created the absence
    // if ($data['gibbonPersonID'] != $_SESSION[$guid]['gibbonPersonID']) {
    //     $event->addRecipient($data['gibbonPersonID']);
    // }

    $event->sendNotificationsAsBcc($pdo, $gibbon->session);


    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URL}");
    exit;
}
