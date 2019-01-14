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
use Gibbon\Domain\Staff\StaffAbsenceTypeGateway;
use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;

if (isActionAccessible($guid, $connection2, '/modules/Staff/absences_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs
        ->add(__('Manage Staff Absences'), 'absences_manage.php')
        ->add(__('Edit Absence'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, array('error3' => __('School is closed on the specified day.')));
    }

    $gibbonStaffAbsenceID = $_GET['gibbonStaffAbsenceID'] ?? '';

    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);
    $staffAbsenceTypeGateway = $container->get(StaffAbsenceTypeGateway::class);

    if (empty($gibbonStaffAbsenceID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $values = $staffAbsenceGateway->getByID($gibbonStaffAbsenceID);

    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $form = Form::create('staffAbsenceEdit', $_SESSION[$guid]['absoluteURL'].'/modules/Staff/absences_manage_editProcess.php');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('smallIntBorder fullWidth');
    $form->addHiddenValue('address', $_SESSION[$guid]['address']);
    $form->addHiddenValue('gibbonStaffAbsenceID', $gibbonStaffAbsenceID);

    $form->addRow()->addHeading(__('Basic Information'));


    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('Person'));
        $row->addSelectStaff('gibbonPersonID')->placeholder()->isRequired()->readonly();


    $types = $staffAbsenceTypeGateway->selectAllTypes()->fetchAll();
    $typesWithReasons = [];
    $reasonsOptions = [];
    $reasonsChained = [];

    $types = array_reduce($types, function ($group, $item) use (&$reasonsOptions, &$reasonsChained, &$typesWithReasons) {
        $id = $item['gibbonStaffAbsenceTypeID'];
        $group[$id] = $item['name'];
        $reasons = array_filter(array_map('trim', explode(',', $item['reasons'])));
        if (!empty($reasons)) {
            $typesWithReasons[] = $id;
            foreach ($reasons as $reason) {
                $reasonsOptions[$reason] = $reason;
                $reasonsChained[$reason] = $id;
            }
        }
        return $group;
    }, []);

    $row = $form->addRow();
        $row->addLabel('gibbonStaffAbsenceTypeID', __('Type'));
        $row->addSelect('gibbonStaffAbsenceTypeID')
            ->fromArray($types)
            ->placeholder()
            ->isRequired();

    $form->toggleVisibilityByClass('reasonOptions')->onSelect('gibbonStaffAbsenceTypeID')->when($typesWithReasons);

    $row = $form->addRow()->addClass('reasonOptions');
        $row->addLabel('reason', __('Reason'));
        $row->addSelect('reason')
            ->fromArray($reasonsOptions)
            ->chainedTo('gibbonStaffAbsenceTypeID', $reasonsChained)
            ->placeholder()
            ->isRequired();

    $row = $form->addRow();
        $row->addLabel('comment', __('Comment'));
        $row->addTextArea('comment')->setRows(2);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    $form->loadAllValuesFrom($values);

    echo $form->getOutput();


    $absenceDates = $staffAbsenceDateGateway->selectDatesByAbsence($gibbonStaffAbsenceID);

    // DATA TABLE
    $table = DataTable::create('staffAbsenceDates');
    $table->setTitle(__('Dates'));

    $table->addColumn('date', __('Date'))
        ->format(Format::using('dateReadable', 'date'));

    $table->addColumn('timeStart', __('Time'))->format(function ($absence) {
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

    // ACTIONS
    if ($absenceDates->rowCount() > 1) {
        $canRequestCoverage = isActionAccessible($guid, $connection2, '/modules/Staff/coverage_request.php');

        $table->addActionColumn()
            ->addParam('gibbonStaffAbsenceID', $gibbonStaffAbsenceID)
            ->addParam('gibbonStaffAbsenceDateID')
            ->format(function ($absence, $actions) use ($canRequestCoverage) {
                $actions->addAction('deleteInstant', __('Delete'))
                        ->setIcon('garbage')
                        ->isDirect()
                        ->setURL('/modules/Staff/absences_manage_edit_deleteProcess.php')
                        ->addConfirmation(__('Are you sure you wish to delete this record?'));

                if ($canRequestCoverage && empty($absence['coverage'])) {
                    $actions->addAction('coverage', __('Request Coverage'))
                        ->setIcon('attendance')
                        ->setURL('/modules/Staff/coverage_request.php');
                }
            });
    }

    echo $table->render($absenceDates->toDataSet());

    $form = Form::create('staffAbsenceAdd', $_SESSION[$guid]['absoluteURL'].'/modules/Staff/absences_manage_edit_addProcess.php');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('smallIntBorder fullWidth');
    $form->addHiddenValue('address', $_SESSION[$guid]['address']);
    $form->addHiddenValue('gibbonStaffAbsenceID', $gibbonStaffAbsenceID);

    $form->addRow()->addHeading(__('Add Date'));

    $row = $form->addRow();
        $row->addLabel('allDay', __('All Day'));
        $row->addYesNoRadio('allDay')->checked('Y');

    $form->toggleVisibilityByClass('timeOptions')->onRadio('allDay')->when('N');

    $row = $form->addRow();
        $row->addLabel('date', __('Date'));
        $row->addDate('date')->isRequired();

    $row = $form->addRow()->addClass('timeOptions');
        $row->addLabel('time', __('Time'));
        $col = $row->addColumn('time')->addClass('right');
        $col->addTime('timeStart')
            ->addClass('timeOptions')
            ->isRequired();
        $col->addTime('timeEnd')
            ->chainedTo('timeStart', false)
            ->addClass('timeOptions')
            ->isRequired();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
