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
use Gibbon\Domain\Staff\StaffAbsenceTypeGateway;

if (isActionAccessible($guid, $connection2, '/modules/Staff/absence_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs
        ->add(__('View Absences'), 'absence_view.php')
        ->add(__('New Absence'));

    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    $editLink = '';
    if (isset($_GET['editID'])) {
        $editLink = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/absence_manage_edit.php&gibbonStaffAbsenceID='.$_GET['editID'];
    }
    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], $editLink, null);
    }

    $staffAbsenceTypeGateway = $container->get(StaffAbsenceTypeGateway::class);

    $form = Form::create('staffAbsence', $_SESSION[$guid]['absoluteURL'].'/modules/Staff/absence_manage_addProcess.php');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('smallIntBorder fullWidth');

    $form->addHiddenValue('address', $_SESSION[$guid]['address']);

    $form->addRow()->addHeading(__('Basic Information'));

    if ($highestAction == 'New Absence_any') {
        $gibbonPersonID = $_GET['gibbonPersonID'] ?? $_SESSION[$guid]['gibbonPersonID'];
        $row = $form->addRow();
            $row->addLabel('gibbonPersonID', __('Person'));
            $row->addSelectStaff('gibbonPersonID')->placeholder()->isRequired()->selected($gibbonPersonID);
    } elseif ($highestAction == 'New Absence_mine') {
        $form->addHiddenValue('gibbonPersonID', $_SESSION[$guid]['gibbonPersonID']);
    }

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

    $form->addRow()->addHeading(__('Date & Time'));

    $row = $form->addRow();
        $row->addLabel('allDay', __('All Day'));
        $row->addYesNoRadio('allDay')->checked('Y');

    $form->toggleVisibilityByClass('timeOptions')->onRadio('allDay')->when('N');

    $row = $form->addRow();
        $row->addLabel('dateStart', __('Start Date'));
        $col = $row->addColumn('dateStart')->addClass('right');
        $col->addDate('dateStart')->to('dateEnd')->isRequired();
        $col->addTime('timeStart')->addClass('timeOptions');

    $row = $form->addRow();
        $row->addLabel('dateEnd', __('End Date'));
        $col = $row->addColumn('dateEnd')->addClass('right');
        $col->addDate('dateEnd')->from('dateStart')->isRequired();
        $col->addTime('timeEnd')->chainedTo('timeStart', false)->addClass('timeOptions');

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
