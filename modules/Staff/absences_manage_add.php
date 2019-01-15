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

if (isActionAccessible($guid, $connection2, '/modules/Staff/absences_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs->add(__('New Absence'));

    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    $editLink = '';
    if (isset($_GET['editID'])) {
        $editLink = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/absences_manage_edit.php&gibbonStaffAbsenceID='.$_GET['editID'];
    }
    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], $editLink, null);
    }

    $staffAbsenceTypeGateway = $container->get(StaffAbsenceTypeGateway::class);

    $form = Form::create('staffAbsence', $_SESSION[$guid]['absoluteURL'].'/modules/Staff/absences_manage_addProcess.php');

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

    $date = $_GET['date'] ?? '';
    $row = $form->addRow();
        $row->addLabel('dateStart', __('Start Date'));
        $col = $row->addColumn('dateStart')->addClass('right');
        $col->addDate('dateStart')->to('dateEnd')->isRequired()->setValue($date);
        $col->addTime('timeStart')
            ->addClass('timeOptions')
            ->isRequired();

    $row = $form->addRow();
        $row->addLabel('dateEnd', __('End Date'));
        $col = $row->addColumn('dateEnd')->addClass('right');
        $col->addDate('dateEnd')->from('dateStart')->isRequired()->setValue($date);
        $col->addTime('timeEnd')
            ->chainedTo('timeStart', false)
            ->addClass('timeOptions')
            ->isRequired();

    if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_request.php')) {
        $form->addRow()->addHeading(__('Coverage'));

        $coverageOptions = [
            'N'         => __('No'),
            'request'   => __('Specific substitute'),
            'broadcast' => __('Any available substitute'),
        ];
        $row = $form->addRow();
            $row->addLabel('coverage', __('Substitute Required?'));
            $row->addSelect('coverage')->isRequired()->fromArray($coverageOptions)->selected('N');
    
        $form->toggleVisibilityByClass('coverageOptions')->onSelect('coverage')->when('request');
        $form->toggleVisibilityByClass('broadcastOptions')->onSelect('coverage')->when('broadcast');
            
        $row = $form->addRow()->addClass('coverageOptions');
            $row->addLabel('gibbonPersonIDCoverage', __('Substitute'));
            $row->addSelectSubstitute('gibbonPersonIDCoverage')->placeholder()->isRequired();
    
        $notification = __("SMS and email");
        $row = $form->addRow()->addClass('coverageOptions');
            $row->addAlert(__("This option sends your request by {notification} to the selected substitute.", ['notification' => $notification]).' '.__("You'll receive a notification when they accept or decline your request. If your request is declined you'll have to option to send a new request."), 'message');
    
        $row = $form->addRow()->addClass('broadcastOptions');
            $row->addAlert(__("This option sends a request by {notification} out to <b>ALL</b> available subs.", ['notification' => $notification]).' '.__("You'll receive a notification once your request is accepted."), 'message');
    } else {
        $form->addHiddenValue('coverage', 'N');
    }

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
