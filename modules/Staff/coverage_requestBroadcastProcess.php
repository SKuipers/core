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

use Gibbon\Data\BackgroundProcess;
use Gibbon\Domain\Staff\SubstituteGateway;
use Gibbon\Domain\Staff\StaffCoverageGateway;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Module\Staff\MessageSender;
use Gibbon\Module\Staff\Messages\BroadcastRequest;
use Gibbon\Services\Format;

$_POST['address'] = '/modules/Staff/coverage_requestBroadcastProcess.php';

require_once '../../gibbon.php';

// Cancel out now if we're not running via CLI
if (!isCommandLineInterface()) {
    die(__('This script cannot be run from a browser, only via CLI.'));
}

// Setup default settings
ini_set('memory_limit', '2048M');
ini_set('max_execution_time', 900);
set_time_limit(900);

getSystemSettings($guid, $connection2);
setCurrentSchoolYear($guid, $connection2);
Format::setupFromSession($container->get('session'));

// Incoming variables from command line
$gibbonStaffCoverageID = (isset($argv[1]))? $argv[1] : null ;

// Setup
$processor = new BackgroundProcess($gibbon->session->get('absolutePath').'/uploads/background');

$substituteGateway = $container->get(SubstituteGateway::class);
$staffCoverageGateway = $container->get(StaffCoverageGateway::class);
$staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);

$coverage = $staffCoverageGateway->getCoverageDetailsByID($gibbonStaffCoverageID);
if (empty($coverage)) {
    die('error1'."\n");
}

$absenceDates = $staffAbsenceDateGateway->selectDatesByAbsence($coverage['gibbonStaffAbsenceID'])->fetchAll();
if (empty($absenceDates)) {
    die('error1'."\n");
}

// Get available subs
$availableSubs = [];
foreach ($absenceDates as $date) {
    $availableByDate = $substituteGateway->selectAvailableSubsByDate($date['date'])->fetchGroupedUnique();
    $availableSubs = array_merge($availableSubs, $availableByDate);
}

// Send messages
$recipients = array_column($availableSubs, 'gibbonPersonID');
$message = new BroadcastRequest($coverage);

$sent = $container
    ->get(MessageSender::class)
    ->send($recipients, $message);


$staffCoverageGateway->update($gibbonStaffCoverageID, [
    'notificationSent' => $sent ? 'Y' : 'N',
    'notificationList' => $sent ? json_encode($recipients) : '',
]);

echo __('Sent').': '.($sent ? count($recipients) : 0);

// End the process and output the result to terminal (output file)
$processor->stopProcess('coverageBroadcast');
