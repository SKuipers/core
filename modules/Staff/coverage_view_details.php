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

use Gibbon\Module\Staff\Forms\ViewCoverageForm;
use Gibbon\Domain\Staff\StaffCoverageGateway;
use Gibbon\Module\Staff\Forms\StaffCard;
use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Services\Format;

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_view_details.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs
        ->add(__('My Coverage'), 'coverage_my.php')
        ->add(__('View Details'));

    $gibbonStaffCoverageID = $_GET['gibbonStaffCoverageID'] ?? '';

    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);
    $coverage = $container->get(StaffCoverageGateway::class)->getByID($gibbonStaffCoverageID);
    
    
    if (!empty($coverage['gibbonStaffAbsenceID'])) {
        $absence = $container->get(StaffAbsenceGateway::class)->getByID($coverage['gibbonStaffAbsenceID']);
        $gibbonPersonID = $absence['gibbonPersonID'];
        // Absence Coverage
    } else {
        // General Coverage
        $gibbonPersonID = $coverage['gibbonPersonIDStatus'];
    }

    // Staff Card
    $staffCard = $container->get(StaffCard::class);
    $page->writeFromTemplate('users/staffCard.twig.html', $staffCard->compose($gibbonPersonID));

    // Coverage Request
    $requester = $container->get(UserGateway::class)->getByID($coverage['gibbonPersonIDStatus']);
    $page->writeFromTemplate('users/statusComment.twig.html', [
        'name'    => Format::name($requester['title'], $requester['preferredName'], $requester['surname'], 'Staff', false, true),
        'action'   => __('Requested'),
        'photo'   => $_SESSION[$guid]['absoluteURL'].'/'.$requester['image_240'],
        'date'    => Format::relativeTime($coverage['timestampStatus']),
        'status'  => $coverage['status'] == 'Requested' ? __('Pending') : '',
        'tag'     => 'message',
        'comment' => $coverage['notesStatus'],
    ]);

    if ($coverage['status'] != 'Requested') {
        $substitute = $container->get(UserGateway::class)->getByID($coverage['gibbonPersonIDCoverage']);
        $page->writeFromTemplate('users/statusComment.twig.html', [
            'name'    => Format::name($substitute['title'], $substitute['preferredName'], $substitute['surname'] ,'Staff', false, true),
            'action'   => __($coverage['status']),
            'photo'   => $_SESSION[$guid]['absoluteURL'].'/'.$substitute['image_240'],
            'date'    => Format::relativeTime($coverage['timestampCoverage']),
            'status'  => __($coverage['status']),
            'tag'     => $coverage['status'] == 'Accepted' ? 'success' : 'error',
            'comment' => $coverage['notesCoverage'],
        ]);
    }
}
