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

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\Staff\StaffCoverageGateway;
use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Domain\Staff\StaffAbsenceTypeGateway;
use Gibbon\Domain\Staff\SubstituteGateway;
use Gibbon\Module\Staff\Forms\ViewCoverageForm;

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_view_accept.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs
        ->add(__('My Coverage'), 'coverage_my.php')
        ->add(__('Accept Coverage Request'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, [
            'success1' => __('Your request was completed successfully.'),
            'warning3' => __('This coverage request has already been accepted.'),
        ]);
    }

    $gibbonStaffCoverageID = $_GET['gibbonStaffCoverageID'] ?? '';

    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);

    if (empty($gibbonStaffCoverageID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $coverage = $staffCoverageGateway->getCoverageDetailsByID($gibbonStaffCoverageID);
    if (empty($coverage)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    if ($coverage['status'] != 'Requested') {
        $page->addWarning(__('This coverage request has already been accepted.'));
        return;
    }

    $form = Form::create('staffCoverage', $_SESSION[$guid]['absoluteURL'].'/modules/Staff/coverage_view_acceptProcess.php');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $_SESSION[$guid]['address']);
    $form->addHiddenValue('gibbonStaffCoverageID', $gibbonStaffCoverageID);

    $form->addRow()->addHeading(__('Accept Coverage Request'));

    $gibbonPersonIDStatus = !empty($coverage['gibbonPersonID'])? $coverage['gibbonPersonID'] : $coverage['gibbonPersonIDStatus'];
    if (!empty($gibbonPersonIDStatus)) {
        $form->addRow()->addContent(ViewCoverageForm::getStaffCard($container, $gibbonPersonIDStatus));
    }

    if (!empty($coverage['gibbonStaffAbsenceID'])) {
        $row = $form->addRow();
            $row->addLabel('typeLabel', __('Type'));
            $row->addTextField('type')->readonly()->setValue($coverage['reason'] ? "{$coverage['type']} ({$coverage['reason']})" : $coverage['type']);
    }
    
    $row = $form->addRow();
        $row->addLabel('timestampLabel', __('Requested'));
        $row->addTextField('timestamp')->readonly()->setValue(Format::relativeTime($coverage['timestampStatus'], false))->setTitle($coverage['timestampStatus']);

    if (!empty($coverage['notesStatus'])) {
        $row = $form->addRow();
            $row->addLabel('notesStatusLabel', __('Comment'));
            $row->addTextArea('notesStatus')->setRows(3)->setValue($coverage['notesStatus'])->readonly();
    }

    // DATA TABLE
    $coverageDates = $container->get(StaffAbsenceDateGateway::class)->selectDatesByCoverage($gibbonStaffCoverageID);

    $gibbonPersonID = !empty($coverage['gibbonPersonIDCoverage']) ? $coverage['gibbonPersonIDCoverage'] : $_SESSION[$guid]['gibbonPersonID'];
    $unavailable = $container->get(SubstituteGateway::class)->selectUnavailableDatesBySub($gibbonPersonID, $gibbonStaffCoverageID)->fetchGrouped();

    $table = DataTable::create('staffCoverageDates');
    $table->setTitle(__('Dates'));
    $table->getRenderer()->setClass('bulkActionForm datesTable');

    $table->addColumn('date', __('Date'))
        ->format(Format::using('dateReadable', 'date'));

    // $table->addColumn('tt', __('Timetable'))
    //     ->format(function ($absence) use ($guid) {
    //         $text = __('Preview');
    //         $url = $_SESSION[$guid]['absoluteURL'].'/fullscreen.php?q=/modules/Staff/coverage_view_preview.php&gibbonStaffAbsenceDateID='.$absence['gibbonStaffAbsenceDateID'].'&width=768&height=600';

    //         return Format::link($url, $text, ['class' => 'thickbox']);
    //     });

    $table->addColumn('timeStart', __('Time'))
        ->format(function ($coverage) {
            if ($coverage['allDay'] == 'N') {
                return Format::small(Format::timeRange($coverage['timeStart'], $coverage['timeEnd']));
            } else {
                return Format::small(__('All Day'));
            }
        });

    $datesAvailableToRequest = 0;
    $table->addCheckboxColumn('coverageDates', 'date')
        ->width('15%')
        ->checked(true)
        ->format(function ($coverage) use (&$datesAvailableToRequest, &$unavailable) {
            // Has this date already been requested?
            if (empty($coverage['gibbonStaffCoverageID'])) return __('N/A');

            // Is this date unavailable: absent, already booked, or has an availability exception
            if (isset($unavailable[$coverage['date']])) {
                $times = $unavailable[$coverage['date']];

                foreach ($times as $time) {
                    // Handle full day and partial day unavailability
                    if ($time['allDay'] == 'Y' 
                    || ($time['allDay'] == 'N' && $coverage['allDay'] == 'Y')
                    || ($time['allDay'] == 'N' && $coverage['allDay'] == 'N'
                        && $time['timeStart'] <= $coverage['timeEnd']
                        && $time['timeEnd'] >= $coverage['timeStart'])) {
                        return Format::small(__($time['status'] ?? 'Not Available'));
                    }
                }
            }

            $datesAvailableToRequest++;
        });

    $row = $form->addRow()->addContent($table->render($coverageDates->toDataSet()));

    if ($datesAvailableToRequest > 0) {
        $row = $form->addRow();
            $row->addLabel('notesCoverage', __('Reply'));
            $row->addTextArea('notesCoverage')->setRows(3);

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();
    } else {
        $row = $form->addRow();
            $row->addAlert(__('Not Available'), 'warning');
    }

    echo $form->getOutput();
}
