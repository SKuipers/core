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
use Gibbon\Data\BackgroundProcess;
use Gibbon\Domain\Staff\StaffCoverageGateway;
use Gibbon\Module\Staff\MessageSender;
use Gibbon\Module\Staff\Messages\CoveragePartial;
use Gibbon\Module\Staff\Messages\CoverageAccepted;
use Gibbon\Module\Staff\Messages\NewCoverage;

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
$staffCoverageGateway = $container->get(StaffCoverageGateway::class);

$sent = 0;

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
                $sent += count($recipients);
            }

            // Send a coverage arranged message to Admin
            $recipients = ['001'];
            $message = new NewCoverage($coverage);

            if ($messageSender->send($recipients, $message)) {
                $sent += count($recipients);
            }
        }
        break;
}

echo __('Sent').': '.$sent;

// End the process and output the result to terminal (output file)
$processor->stopProcess('staffNotification');
