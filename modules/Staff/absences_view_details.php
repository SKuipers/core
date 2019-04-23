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
use Gibbon\Domain\User\UserGateway;
use Gibbon\Module\Staff\Forms\StaffCard;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Module\Staff\Tables\AbsenceFormats;
use Gibbon\Domain\Staff\StaffCoverageGateway;
use Gibbon\Module\Staff\Tables\AbsenceDates;

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
    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);

    if (empty($gibbonStaffAbsenceID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // $absence = $staffAbsenceGateway->getByID($gibbonStaffAbsenceID);
    $absence = $container->get(StaffAbsenceGateway::class)->getAbsenceDetailsByID($gibbonStaffAbsenceID);

    if (empty($absence)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    if ($highestAction == 'View Absences_my' && $absence['gibbonPersonID'] != $_SESSION[$guid]['gibbonPersonID']) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    // Staff Card
    $staffCard = $container->get(StaffCard::class);
    $page->writeFromTemplate('users/staffCard.twig.html', $staffCard->compose($absence['gibbonPersonID']));


    // Absence Dates
    $table = $container->get(AbsenceDates::class)->compose($absence);
    $page->write($table->getOutput());

    // Absence Request
    $person = $container->get(UserGateway::class)->getByID($absence['gibbonPersonIDCreator']);
    $page->writeFromTemplate('users/statusComment.twig.html', [
        'name'    => Format::name($person['title'], $person['preferredName'], $person['surname'] ,'Staff', false, true),
        'action'   => 'Requested',
        'photo'   => $_SESSION[$guid]['absoluteURL'].'/'.$person['image_240'],
        'date'    => Format::relativeTime($absence['timestampCreator']),
        'status'  => $absence['status'] == 'Pending Approval' ? __('Pending Approval') : '',
        'tag'     => 'message',
        'comment' => $absence['comment'],
    ]);


    // Approval
    if (!empty($absence['gibbonPersonIDApproval']) && $absence['status'] != 'Pending Approval') {
        $approver = $container->get(UserGateway::class)->getByID($absence['gibbonPersonIDApproval']);
        $page->writeFromTemplate('users/statusComment.twig.html', [
            'name'    => Format::name($approver['title'], $approver['preferredName'], $approver['surname'] ,'Staff', false, true),
            'action'   => $absence['status'] != 'Pending Approval' ? $absence['status'] : '',
            'photo'   => $_SESSION[$guid]['absoluteURL'].'/'.$approver['image_240'],
            'date'    => Format::relativeTime($absence['timestampApproval']),
            'status'  => $absence['status'],
            'tag'     => $absence['status'] == 'Approved' ? 'success' : 'error',
            'comment' => $absence['notesApproval'],
        ]);
    }

    $coverageList = $staffCoverageGateway->selectCoverageByAbsenceID($absence['gibbonStaffAbsenceID'])->fetchAll();

    // Coverage
    if (!empty($coverageList)) {
        foreach ($coverageList as $coverage) {
            $requester = $container->get(UserGateway::class)->getByID($coverage['gibbonPersonIDStatus']);
            $page->writeFromTemplate('users/statusComment.twig.html', [
                'name'    => Format::name($requester['title'], $requester['preferredName'], $requester['surname'], 'Staff', false, true),
                'action'   => __('Requested Coverage'),
                'photo'   => $_SESSION[$guid]['absoluteURL'].'/'.$requester['image_240'],
                'date'    => Format::relativeTime($coverage['timestampStatus']),
                'status'  => $coverage['status'] != 'Accepted' ? __('Pending') : '',
                'tag'     => 'message',
                'comment' => $coverage['notesStatus'],
            ]);
            
            if (!empty($coverage['gibbonPersonIDCoverage'])) {
                $substitute = $container->get(UserGateway::class)->getByID($coverage['gibbonPersonIDCoverage']);
                $page->writeFromTemplate('users/statusComment.twig.html', [
                    'name'    => Format::name($substitute['title'], $substitute['preferredName'], $substitute['surname'], 'Staff', false, true),
                    'action'   => $coverage['status'],
                    'photo'   => $_SESSION[$guid]['absoluteURL'].'/'.$substitute['image_240'],
                    'date'    => Format::relativeTime($coverage['timestampCoverage']),
                    'status'  => $coverage['status'],
                    'tag'     => $coverage['status'] == 'Accepted' ? 'success' : 'error',
                    'comment' => $coverage['notesCoverage'],
                ]);
            }
        }
    }


}
