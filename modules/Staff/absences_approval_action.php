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

use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Module\Staff\Forms\ViewAbsenceForm;
use Gibbon\Forms\Form;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\Staff\Tables\AbsenceFormats;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Module\Staff\Forms\StaffCard;

if (isActionAccessible($guid, $connection2, '/modules/Staff/absences_approval_action.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $gibbonStaffAbsenceID = $_GET['gibbonStaffAbsenceID'] ?? '';
    $action = $_GET['action'] ?? '';

    $page->breadcrumbs
        ->add(__('Approve Staff Absences'), 'absences_approval.php')
        ->add(__($action));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], $editLink, null);
    }

    $absence = $container->get(StaffAbsenceGateway::class)->getByID($gibbonStaffAbsenceID);

    if (empty($absence)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // Staff Card
    $staffCard = $container->get(StaffCard::class);
    $page->writeFromTemplate('users/staffCard.twig.html', $staffCard->compose($absence['gibbonPersonID']));

    // Absence Request
    $person = $container->get(UserGateway::class)->getByID($absence['gibbonPersonID']);
    $page->writeFromTemplate('users/statusComment.twig.html', [
        'name'    => Format::name($person['title'], $person['preferredName'], $person['surname'] ,'Staff', false, true),
        'action'   => 'Requested',
        'photo'   => $_SESSION[$guid]['absoluteURL'].'/'.$person['image_240'],
        'date'    => Format::relativeTime($absence['timestampCreator']),
        'status'  => $absence['status'],
        'tag'     => 'message',
        'comment' => !empty($absence['commentConfidential']) ? $absence['commentConfidential'] : $absence['comment'],
    ]);

    // Absence Dates
    $absenceDates = $container->get(StaffAbsenceDateGateway::class)->selectDatesByAbsence($absence['gibbonStaffAbsenceID']);

    $table = DataTable::create('staffAbsenceDates')->withData($absenceDates->toDataSet());

    $table->setTitle(__('Dates'));

    $table->addColumn('date', __('Date'))
            ->format(Format::using('dateReadable', 'date'));

    $table->addColumn('timeStart', __('Time'))
            ->format([AbsenceFormats::class, 'timeDetails']);

    echo $table->getOutput();
    echo '<br/>';

    // Approval Form
    $form = Form::create('staffAbsenceApproval', $_SESSION[$guid]['absoluteURL'].'/modules/Staff/absences_approval_actionProcess.php');

    $form->addHiddenValue('address', $_SESSION[$guid]['address']);
    $form->addHiddenValue('gibbonStaffAbsenceID', $gibbonStaffAbsenceID);
    $form->addHiddenValue('action', $action);

    $row = $form->addRow();
        $row->addLabel('actionLabel', __('Action'));
        $row->addTextField('actionValue')->readonly()->setValue($action);

    $row = $form->addRow();
        $row->addLabel('notesApproval', __('Reply'));
        $row->addTextArea('notesApproval')->setRows(3);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
