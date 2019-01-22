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
    $absenceDates = $staffAbsenceDateGateway->selectDatesByAbsence($gibbonStaffAbsenceID);
    $unavailable = $substituteGateway->selectUnavailableDatesBySub($gibbonPersonIDCoverage)->fetchGroupedUnique();

    if (empty($absenceDates)) {
        die();
    }

    $table = DataTable::create('staffAbsenceDates');
    $table->setTitle(__('Dates'));
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
                $date = $unavailable[$absence['date']];
                
                // Handle full day and partial day unavailability
                if ($date['allDay'] == 'Y' || ($date['allDay'] == 'N' 
                    && $date['timeStart'] <= $absence['timeEnd'] 
                    && $date['timeEnd'] >= $absence['timeStart'])) {
                    return Format::small(__($unavailable[$absence['date']]['status'] ?? 'Not Available'));
                }
            }

            $datesAvailableToRequest++;
        });

    echo $table->render($absenceDates->toDataSet());
}
