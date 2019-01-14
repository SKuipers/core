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

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_view_accept.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs->add(__('Accept Coverage Request'));

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

    $coverage = $staffCoverageGateway->getByID($gibbonStaffCoverageID);
    $absence = $container->get(StaffAbsenceGateway::class)->getByID($coverage['gibbonStaffAbsenceID'] ?? '');
    $type = $container->get(StaffAbsenceTypeGateway::class)->getByID($absence['gibbonStaffAbsenceTypeID'] ?? '');

    if (empty($coverage) || empty($absence) || empty($type)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    if ($coverage['status'] != 'Requested') {
        $page->addWarning(__('This coverage request has already been accepted.'));
        return;
    }

    $form = Form::create('staffCoverage', $_SESSION[$guid]['absoluteURL'].'/modules/Staff/coverage_view_acceptProcess.php');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('smallIntBorder fullWidth');
    $form->addHiddenValue('address', $_SESSION[$guid]['address']);
    $form->addHiddenValue('gibbonStaffCoverageID', $gibbonStaffCoverageID);

    $form->addRow()->addHeading(__('Accept Coverage Request'));

    $row = $form->addRow();
        $row->addLabel('gibbonPersonIDLabel', __('Person'));
        $row->addSelectStaff('gibbonPersonID')->placeholder()->isRequired()->selected($absence['gibbonPersonID'])->readonly();

    $row = $form->addRow();
        $row->addLabel('typeLabel', __('Type'));
        $row->addTextField('type')->readonly()->setValue($absence['reason'] ? "{$type['name']} ({$absence['reason']})" : $type['name']);

    $row = $form->addRow();
        $row->addLabel('timestampLabel', __('Requested'));
        $row->addTextField('timestamp')->readonly()->setValue(Format::relativeTime($coverage['timestampRequested'], false))->setTitle($coverage['timestampRequested']);

    if (!empty($coverage['notesRequested'])) {
        $row = $form->addRow();
            $row->addLabel('notesRequestedLabel', __('Notes'));
            $row->addTextArea('notesRequested')->setRows(2)->setValue($coverage['notesRequested'])->readonly();
    }

    // DATA TABLE
    $absenceDates = $container->get(StaffAbsenceDateGateway::class)->selectDatesByCoverage($gibbonStaffCoverageID);
    
    $table = DataTable::create('staffCoverageDates');
    $table->setTitle(__('Dates'));

    $table->addColumn('date', __('Date'))
        ->format(Format::using('dateReadable', 'date'));

    // $table->addColumn('tt', __('Timetable'))
    //     ->format(function ($absence) use ($guid) {
    //         $text = __('Preview');
    //         $url = $_SESSION[$guid]['absoluteURL'].'/fullscreen.php?q=/modules/Staff/coverage_view_preview.php&gibbonStaffAbsenceDateID='.$absence['gibbonStaffAbsenceDateID'].'&width=768&height=600';

    //         return Format::link($url, $text, ['class' => 'thickbox']);
    //     });

    $table->addColumn('timeStart', __('Time'))
        ->format(function ($absence) {
            if ($absence['allDay'] == 'N') {
                return Format::small(Format::timeRange($absence['timeStart'], $absence['timeEnd']));
            } else {
                return Format::small(__('All Day'));
            }
        });

    $datesAvailableToRequest = 0;
    $table->addColumn('coverageDates', __('Coverage'))
        ->width('8%')
        ->format(function ($absence) use ($form, &$datesAvailableToRequest) {
            if (empty($absence['gibbonStaffCoverageID'])) return __('N/A');

            $datesAvailableToRequest++;

            return $form
                ->getFactory()
                ->createCheckbox('coverageDates[]')
                ->setID('coverageDates-'.$absence['date'])
                ->setValue($absence['date'])
                ->checked($absence['date'])
                ->getOutput();
        });

    $row = $form->addRow()->addContent($table->render($absenceDates->toDataSet()));

    if ($datesAvailableToRequest > 0) {
        $row = $form->addRow();
            $row->addLabel('notesCoverage', __('Comment'));
            $row->addTextArea('notesCoverage')->setRows(3);

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();
    }

    echo $form->getOutput();
}
