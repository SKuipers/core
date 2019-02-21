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

use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Domain\Staff\SubstituteGateway;

require_once '../../gibbon.php';

$gibbonStaffAbsenceID = $_POST['gibbonStaffAbsenceID'] ?? '';
$gibbonPersonIDCoverage = $_POST['gibbonPersonIDCoverage'] ?? '';

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_request.php') == false) {
    die(Format::alert(__('Your request failed because you do not have access to this action.')));
} elseif (empty($gibbonStaffAbsenceID) || empty($gibbonPersonIDCoverage)|| $gibbonPersonIDCoverage == 'Please select...') {
    die();
} else {
    // Proceed!
    $substituteGateway = $container->get(SubstituteGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);

    // DATA TABLE
    $substitute = $substituteGateway->selectBy('gibbonPersonID', $gibbonPersonIDCoverage)->fetch();
    $absenceDates = $staffAbsenceDateGateway->selectDatesByAbsence($gibbonStaffAbsenceID);
    $unavailable = $substituteGateway->selectUnavailableDatesBySub($gibbonPersonIDCoverage)->fetchGrouped();

    if (empty($absenceDates) || empty($substitute)) {
        die();
    }

    $table = DataTable::create('staffAbsenceDates');
    $table->setTitle(__('Availability'));
    $table->setDescription($substitute['details']);
    $table->getRenderer()->setClass('bulkActionForm');

    $table->addColumn('dateLabel', __('Date'))
        ->format(Format::using('dateReadable', 'date'));

    $table->addColumn('timeStart', __('Time'))
        ->format(function ($absence) {
            if ($absence['allDay'] == 'N') {
                return Format::small(Format::timeRange($absence['timeStart'], $absence['timeEnd']));
            } else {
                return Format::small(__('All Day'));
            }
        });

    $datesAvailableToRequest = 0;
    $table->addCheckboxColumn('requestDates', 'date')
        ->width('15%')
        ->checked(true)
        ->format(function ($absence) use (&$datesAvailableToRequest, &$unavailable) {
            // Has this date already been requested?
            if (!empty($absence['gibbonStaffCoverageID'])) return __('Requested');

            // Is this date unavailable: absent, already booked, or has an availability exception
            if (isset($unavailable[$absence['date']])) {
                $times = $unavailable[$absence['date']];

                foreach ($times as $time) {
                
                    // Handle full day and partial day unavailability
                    if ($time['allDay'] == 'Y' 
                    || ($time['allDay'] == 'N' && $absence['allDay'] == 'Y')
                    || ($time['allDay'] == 'N' && $absence['allDay'] == 'N'
                        && $time['timeStart'] <= $absence['timeEnd']
                        && $time['timeEnd'] >= $absence['timeStart'])) {
                        return Format::small(__($time['status'] ?? 'Not Available'));
                    }
                }
            }

            $datesAvailableToRequest++;
        });

    echo $table->render($absenceDates->toDataSet());
}
