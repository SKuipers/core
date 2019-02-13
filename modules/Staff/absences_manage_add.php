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
use Gibbon\Services\Format;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Domain\Staff\StaffAbsenceGateway;
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

    $absoluteURL = $gibbon->session->get('absoluteURL');
    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffAbsenceTypeGateway = $container->get(StaffAbsenceTypeGateway::class);

    $form = Form::create('staffAbsence', $_SESSION[$guid]['absoluteURL'].'/modules/Staff/absences_manage_addProcess.php');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $_SESSION[$guid]['address']);

    $form->addRow()->addHeading(__('Basic Information'));

    if ($highestAction == 'New Absence_any') {
        $gibbonPersonID = $_GET['gibbonPersonID'] ?? $_SESSION[$guid]['gibbonPersonID'];
        $row = $form->addRow();
            $row->addLabel('gibbonPersonID', __('Person'));
            $row->addSelectStaff('gibbonPersonID')->placeholder()->isRequired()->selected($gibbonPersonID);
    } elseif ($highestAction == 'New Absence_mine') {
        $gibbonPersonID = $_SESSION[$guid]['gibbonPersonID'];
        $form->addHiddenValue('gibbonPersonID', $gibbonPersonID);
    }

    $types = $staffAbsenceTypeGateway->selectAllTypes()->fetchAll();
    $typesRequiringApproval = $staffAbsenceTypeGateway->selectTypesRequiringApproval()->fetchAll(\PDO::FETCH_COLUMN, 0);
    $approverOptions = explode(',', getSettingByScope($connection2, 'Staff', 'absenceApprovers'));

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

    // $form->toggleVisibilityByClass('typeSelected')->onSelect('gibbonStaffAbsenceTypeID')->whenNot('Please select ...');
    $form->toggleVisibilityByClass('reasonOptions')->onSelect('gibbonStaffAbsenceTypeID')->when($typesWithReasons);

    $row = $form->addRow()->addClass('reasonOptions');
        $row->addLabel('reason', __('Reason'));
        $row->addSelect('reason')
            ->fromArray($reasonsOptions)
            ->chainedTo('gibbonStaffAbsenceTypeID', $reasonsChained)
            ->placeholder()
            ->isRequired();

    $row = $form->addRow();
        $row->addLabel('comment', __('Confidential Comment'))->description(__('This message is only shared with the people notified of this absence and users who manage staff absences.'));
        $row->addTextArea('comment')->setRows(3);

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

    if (!empty($typesRequiringApproval)) {
        $form->toggleVisibilityByClass('approvalRequired')->onSelect('gibbonStaffAbsenceTypeID')->when($typesRequiringApproval);
        $form->toggleVisibilityByClass('approvalNotRequired')->onSelect('gibbonStaffAbsenceTypeID')->whenNot(array_merge($typesRequiringApproval, ['Please select...']));

        $form->addRow()->addHeading(__('Requires Approval'))->addClass('approvalRequired');

        $row = $form->addRow()->addClass('approvalRequired');
        $row->addLabel('gibbonPersonIDApproval', __('Approver'));
        $row->addSelectUsersFromList('gibbonPersonIDApproval', $approverOptions)
            ->placeholder()
            ->isRequired();
    }
    $form->addRow()->addHeading(__('Notifications'));

    // HR Administrator
    $organisationHR = getSettingByScope($connection2, 'System', 'organisationHR');
    $personHR = $container->get(UserGateway::class)->getByID($organisationHR);

    if ($personHR) {
        $row = $form->addRow();
            $row->addLabel('organisationHRLabel', __('Automatic Notification'));
            $row->addTextField('organisationHR')->readonly()->setValue(Format::nameList([$personHR], 'Staff'));
    }

    // Get the users last notified by this staff member
    $recentAbsence = $staffAbsenceGateway->getMostRecentAbsenceByPerson($gibbonPersonID);
    $notificationList = !empty($recentAbsence['notificationList'])? json_decode($recentAbsence['notificationList']) : [];

    // Format user details into token-friendly list
    $notified = $container->get(UserGateway::class)->selectNotificationDetailsByPerson($notificationList)->fetchGroupedUnique();
    $notified = array_map(function ($token) use ($absoluteURL) {
        return [
            'id'       => $token['gibbonPersonID'],
            'name'     => Format::name('', $token['preferredName'], $token['surname'], 'Staff', false, true),
            'jobTitle' => !empty($token['jobTitle']) ? $token['jobTitle'] : $token['type'],
            'image'    => $absoluteURL.'/'.$token['image_240'],
        ];
    }, $notified);

    $row = $form->addRow();
        $row->addLabel('notificationList', __('Notify Additional People'))->description(__('Your notification choices are saved and pre-filled for future absences.'));
        $row->addFinder('notificationList')
            ->fromAjax($gibbon->session->get('absoluteURL').'/modules/Staff/staff_searchAjax.php')
            ->selected($notified)
            ->setParameter('resultsLimit', 10)
            ->resultsFormatter('function(item){ return "<li class=\'finderListItem\'><div class=\'finderPhoto\' style=\'background-image: url(" + item.image + ");\'></div><div class=\'finderName\'>" + item.name + "<br/><span class=\'finderDetail\'>" + item.jobTitle + "</span></div></li>"; }')
            ->tokenFormatter('function(item){ return "<li class=\'finderToken\'>" + item.name + "</li>"; }');
    
    $row = $form->addRow()->addClass('approvalRequired displayNone');
        $row->addAlert(__("These people will only be notified if this absence is approved."), 'message');

    if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_request.php')) {
        $form->addRow()->addHeading(__('Coverage'))->addClass('approvalNotRequired');

        $row = $form->addRow()->addClass('approvalNotRequired');
            $row->addLabel('coverage', __('Substitute Required?'));
            $row->addYesNo('coverage')->isRequired()->selected('N');
    
        $form->toggleVisibilityByClass('coverageOptions')->onSelect('coverage')->whenNot('N');
            
        $row = $form->addRow()->addClass('coverageOptions approvalNotRequired');
            $row->addAlert(__("You'll have the option to send a coverage request after submitting this form."), 'success');
    } else {
        $form->addHiddenValue('coverage', 'N');
    }

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
