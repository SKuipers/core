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
use Gibbon\Data\BackgroundProcess;
use Gibbon\Domain\Staff\SubstituteGateway;
use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Domain\Staff\StaffCoverageGateway;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Module\Staff\MessageSender;
use Gibbon\Module\Staff\Messages\BroadcastRequest;
use Gibbon\Module\Staff\Messages\CoverageAccepted;
use Gibbon\Module\Staff\Messages\CoveragePartial;
use Gibbon\Module\Staff\Messages\NewCoverage;
use Gibbon\Module\Staff\Messages\NewAbsence;
use Gibbon\Module\Staff\Messages\AbsencePendingApproval;

$_POST['address'] = '/modules/Staff/notification_backgroundProcess.php';

require_once '../../gibbon.php';

// Cancel out now if we're not running via CLI
if (!isCommandLineInterface()) {
    die(__('This script cannot be run from a browser, only via CLI.'));
}

// Setup default settings
getSystemSettings($guid, $connection2);
setCurrentSchoolYear($guid, $connection2);
Format::setupFromSession($container->get('session'));
ini_set('memory_limit', '2048M');
ini_set('max_execution_time', 900);
set_time_limit(900);

// Incoming variables from command line
$action = $argv[1] ?? '';

// Setup
$processor = new BackgroundProcess($gibbon->session->get('absolutePath').'/uploads/background');

$messageSender = $container->get(MessageSender::class);
$urgencyThreshold = getSettingByScope($connection2, 'Staff', 'urgencyThreshold') * 86400;
$urgentNotifications = getSettingByScope($connection2, 'Staff', 'urgentNotifications');
$organisationHR = getSettingByScope($connection2, 'System', 'organisationHR');

$substituteGateway = $container->get(SubstituteGateway::class);
$staffCoverageGateway = $container->get(StaffCoverageGateway::class);
$staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
$staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);

$sent = [];
$sendCount = 0;

switch ($action) {
    case 'CoverageAccepted':
        $gibbonStaffCoverageID = $argv[2] ?? '';
        $uncoveredDates = !empty($argv[3])? array_filter(explode('::', $argv[3])) : [];

        if ($coverage = $staffCoverageGateway->getCoverageDetailsByID($gibbonStaffCoverageID)) {
            $relativeSeconds = strtotime($coverage['dateStart']) - time();
            $coverage['urgent'] = $relativeSeconds <= $urgencyThreshold;

            // Send the coverage accepted message to the absent staff member
            $recipients = [$coverage['gibbonPersonID']];
            $message = !empty($uncoveredDates)
                ? new CoveragePartial($coverage, $uncoveredDates)
                : new CoverageAccepted($coverage);

            if ($messageSender->send($recipients, $message)) {
                $sendCount += count($recipients);
            }

            // Send a coverage arranged message to the selected staff for this absence
            $recipients = !empty($coverage['notificationList']) ? json_decode($coverage['notificationList']) : [];
            $recipients[] = $organisationHR;

            $message = new NewCoverage($coverage);

            if ($sent = $messageSender->send($recipients, $message)) {
                $sendCount += count($recipients);
            }
        }
        break;

    case 'NewAbsence':
        $gibbonStaffAbsenceID = $argv[2] ?? '';

        if ($absence = $staffAbsenceGateway->getAbsenceDetailsByID($gibbonStaffAbsenceID)) {
            $relativeSeconds = strtotime($absence['dateStart']) - time();
            $absence['urgent'] = $relativeSeconds <= $urgencyThreshold;

            $message = new NewAbsence($absence);

            // Target the absence message to the selected staff
            $recipients = !empty($absence['notificationList']) ? json_decode($absence['notificationList']) : [];
            $recipients[] = $organisationHR;

            if ($absence['gibbonPersonID'] != $absence['gibbonPersonIDCreator']) {
                $recipients[] = $absence['gibbonPersonID'];
            }

            // Send messages
            if ($sent = $messageSender->send($recipients, $message)) {
                $sendCount += count($recipients);

                $staffAbsenceGateway->update($gibbonStaffAbsenceID, [
                    'notificationSent' => 'Y',
                ]);
            }
        }

        break;

    case 'AbsencePendingApproval':
        $gibbonStaffAbsenceID = $argv[2] ?? '';

        if ($absence = $staffAbsenceGateway->getAbsenceDetailsByID($gibbonStaffAbsenceID)) {
            $relativeSeconds = strtotime($absence['dateStart']) - time();
            $absence['urgent'] = $relativeSeconds <= $urgencyThreshold;

            $message = new AbsencePendingApproval($absence);
            $recipients = [$absence['gibbonPersonIDApproval']];

            // Send messages
            if ($sent = $messageSender->send($recipients, $message)) {
                $sendCount += count($recipients);
            }
        }

        break;

    case 'CoverageBroadcast':
        $gibbonStaffCoverageID = $argv[2] ?? '';

        if ($coverage = $staffCoverageGateway->getCoverageDetailsByID($gibbonStaffCoverageID)) {
            $relativeSeconds = strtotime($coverage['dateStart']) - time();
            $coverage['urgent'] = $relativeSeconds <= $urgencyThreshold;

            $absenceDates = $staffAbsenceDateGateway->selectDatesByAbsence($coverage['gibbonStaffAbsenceID'])->fetchAll();

            // Get available subs
            $availableSubs = [];
            foreach ($absenceDates as $date) {
                $criteria = $substituteGateway->newQueryCriteria();
                $availableByDate = $substituteGateway->queryAvailableSubsByDate($criteria, $date['date'])->toArray();
                $availableSubs = array_merge($availableSubs, $availableByDate);
            }

            // Send messages
            $recipients = array_column($availableSubs, 'gibbonPersonID');
            $message = new BroadcastRequest($coverage);

            if ($sent = $messageSender->send($recipients, $message)) {
                $sendCount += count($recipients);

                $staffCoverageGateway->update($gibbonStaffCoverageID, [
                    'notificationSent' => 'Y',
                    'notificationList' => json_encode($recipients),
                ]);
            }
        }

        break;
}

echo __('Sent').': '.$sendCount."\n";
echo __('Send Report').": \n";
echo '<pre>';
print_r($sent);
echo '</pre>';

// End the process and output the result to terminal (output file)
$processor->stopProcess('staffNotification');
