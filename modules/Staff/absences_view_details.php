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

    $form = ViewAbsenceForm::create($container, $gibbonStaffAbsenceID);
    $form->loadAllValuesFrom($values);

    echo $form->getOutput();
}
