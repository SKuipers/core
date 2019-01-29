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

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs->add(__('Edit Coverage'));

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

    $form = Form::create('staffCoverage', $_SESSION[$guid]['absoluteURL'].'/modules/Staff/coverage_manage_editProcess.php');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $_SESSION[$guid]['address']);
    $form->addHiddenValue('gibbonStaffCoverageID', $gibbonStaffCoverageID);

    $form->addRow()->addHeading(__('Basic Information'));

    $row = $form->addRow();
        $row->addLabel('gibbonPersonIDLabel', __('Person'));
        $row->addSelectStaff('gibbonPersonID')->placeholder()->isRequired()->selected($absence['gibbonPersonID'])->readonly();

    $row = $form->addRow();
        $row->addLabel('typeLabel', __('Type'));
        $row->addTextField('type')->readonly()->setValue($absence['reason'] ? "{$type['name']} ({$absence['reason']})" : $type['name']);

    $row = $form->addRow();
        $row->addLabel('timestamp', __('Requested'));
        $row->addTextField('timestampValue')
            ->readonly()
            ->setValue(Format::relativeTime($coverage['timestampRequested'], false))
            ->setTitle($coverage['timestampRequested']);

    
    $row = $form->addRow();
        $row->addLabel('notesRequestedLabel', __('Notes'));
        $row->addTextArea('notesRequested')->setRows(3)->setValue($coverage['notesRequested']);
    
    
    $form->addRow()->addHeading(__('Substitute'));

    $row = $form->addRow();
        $row->addLabel('requestTypeLabel', __('Type'));
        $row->addTextField('requestType')->readonly()->setValue($coverage['requestType']);

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addTextField('status')->readonly()->setValue($coverage['status']);

    if ($coverage['requestType'] == 'Individual') {
        $row = $form->addRow();
            $row->addLabel('gibbonPersonIDLabel', __('Person'));
            $row->addSelectUsers('gibbonPersonIDCoverage')
                ->placeholder()
                ->isRequired()
                ->selected($coverage['gibbonPersonIDCoverage'] ?? '')
                ->setReadonly(true);
    } else if ($coverage['requestType'] == 'Broadcast') {

        $notified = $coverage['notificationSent'] == 'Y'
            ? json_decode($coverage['notificationList'])
            : [];

        if ($notified) {
            $data = ['gibbonPersonIDList' => implode(',', $notified)];
            $sql = "SELECT username, title, preferredName, surname FROM gibbonPerson WHERE FIND_IN_SET(gibbonPersonID, :gibbonPersonIDList) ";
            $people = $pdo->select($sql, $data)->fetchAll();

            $row = $form->addRow();
                $row->addLabel('sentToLabel', __('Recipients'));
                $row->addTextArea('sentTo')->readonly()->setValue(Format::nameList($people, 'Staff', false, true, ', '));
        }
    }

    // Output the coverage status change timestamp, if it has been actioned
    if ($coverage['status'] != 'Requested' && !empty($coverage['timestampCoverage'])) {
        $row = $form->addRow();
        $row->addLabel('timestampCoverage', __($coverage['status']));
        $row->addTextField('timestampCoverageValue')
            ->readonly()
            ->setValue(Format::relativeTime($coverage['timestampCoverage'], false))
            ->setTitle($coverage['timestampCoverage']);
    }

    if (!empty($coverage['notesCoverage'])) {
        $row = $form->addRow();
            $row->addLabel('notesCoverageLabel', __('Comment'));
            $row->addTextArea('notesCoverage')->setRows(3)->readonly();
    }

    // DATA TABLE
    $absenceDates = $container->get(StaffAbsenceDateGateway::class)->selectDatesByCoverage($gibbonStaffCoverageID);
    
    $table = DataTable::create('staffCoverageDates');
    $table->setTitle(__('Dates'));

    $table->addColumn('date', __('Date'))
        ->format(Format::using('dateReadable', 'date'));

    $table->addColumn('timeStart', __('Time'))
        ->format(function ($absence) {
            if ($absence['allDay'] == 'N') {
                return Format::small(Format::timeRange($absence['timeStart'], $absence['timeEnd']));
            } else {
                return Format::small(__('All Day'));
            }
        });

    $table->addColumn('coverage', __('Coverage'))
        ->format(function ($absence) {
            if (empty($absence['coverage'])) {
                return Format::small(__('N/A'));
            }

            return $absence['coverage'] == 'Accepted'
                    ? Format::name($absence['titleCoverage'], $absence['preferredNameCoverage'], $absence['surnameCoverage'], 'Staff', false, true)
                    : '<div class="badge success">'.__('Pending').'</div>';
        });

    $row = $form->addRow()->addContent($table->render($absenceDates->toDataSet()));

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();
    
    $form->loadAllValuesFrom($coverage);

    echo $form->getOutput();
}
