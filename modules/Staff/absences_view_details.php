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
use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Domain\Staff\StaffAbsenceTypeGateway;
use Gibbon\Domain\User\UserGateway;

if (isActionAccessible($guid, $connection2, '/modules/Staff/absences_view_details.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {

    $page->breadcrumbs
        ->add(__('View Absences'), 'absences_view_byPerson.php')
        ->add(__('View Details'));

    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    $gibbonStaffAbsenceID = $_GET['gibbonStaffAbsenceID'] ?? '';

    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);

    if (empty($gibbonStaffAbsenceID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $values = $staffAbsenceGateway->getByID($gibbonStaffAbsenceID);

    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    if ($highestAction == 'View Absences_my' && $values['gibbonPersonID'] != $_SESSION[$guid]['gibbonPersonID']) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    $canManage = isActionAccessible($guid, $connection2, '/modules/Staff/absences_manage.php') || $values['gibbonPersonID'] == $_SESSION[$guid]['gibbonPersonID'];

    $form = Form::create('staffAbsence', '');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $_SESSION[$guid]['address']);
    $form->addHiddenValue('gibbonStaffAbsenceID', $gibbonStaffAbsenceID);

    $row = $form->addRow();
        $row->addLabel('gibbonPersonIDLabel', __('Person'));
        $row->addSelectStaff('gibbonPersonID')->placeholder()->isRequired()->readonly();

    $type = $container->get(StaffAbsenceTypeGateway::class)->getByID($values['gibbonStaffAbsenceTypeID']);

    $row = $form->addRow();
        $row->addLabel('typeLabel', __('Type'));
        $row->addTextField('type')->readonly()->setValue($type['name']);

    if (!empty($values['reason'])) {
        $row = $form->addRow()->addClass('reasonOptions');
            $row->addLabel('reasonLabel', __('Reason'));
            $row->addTextField('reason')->readonly();
    }

    if ($canManage) {
        $row = $form->addRow();
            $row->addLabel('commentLabel', __('Comment'));
            $row->addTextArea('comment')->setRows(2)->readonly();
    }

    $creator = $container->get(UserGateway::class)->getByID($values['gibbonPersonIDCreator']);

    $row = $form->addRow();
        $row->addLabel('timestampLabel', __('Created'));
        $row->addContent(Format::relativeTime($values['timestampCreator']).'<br/>'.Format::small(__('By').' '.Format::nameList([$creator], 'Staff')))->wrap('<div class="standardWidth floatRight">', '</div>');

    $form->loadAllValuesFrom($values);

    echo $form->getOutput();

    $absenceDates = $staffAbsenceDateGateway->selectDatesByAbsence($gibbonStaffAbsenceID);

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

    if ($canManage && isActionAccessible($guid, $connection2, '/modules/Staff/coverage_request.php')) {
        $table->addActionColumn()
            ->addParam('gibbonStaffAbsenceID')
            ->format(function ($absence, $actions) {
                if (!empty($absence['gibbonStaffCoverageID'])) return;

                $actions->addAction('coverage', __('Request Coverage'))
                    ->setIcon('attendance')
                    ->setURL('/modules/Staff/coverage_request.php');
            });
    }
    

    echo $table->render($absenceDates->toDataSet());

}
