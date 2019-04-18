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
use Gibbon\Services\Format;
use Gibbon\Domain\Staff\StaffCoverageGateway;
use Gibbon\Module\Staff\Forms\StaffCard;
use Gibbon\Domain\User\UserGateway;

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_view_cancel.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs
        ->add(__('My Coverage'), 'coverage_my.php')
        ->add(__('Cancel Coverage Request'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, [
            'success1' => __('Your request was completed successfully.')
        ]);
    }

    $gibbonStaffCoverageID = $_GET['gibbonStaffCoverageID'] ?? '';

    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);

    if (empty($gibbonStaffCoverageID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $coverage = $staffCoverageGateway->getCoverageDetailsByID($gibbonStaffCoverageID);

    if (empty($coverage) || $coverage['status'] != 'Requested') {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $form = Form::create('staffCoverage', $_SESSION[$guid]['absoluteURL'].'/modules/Staff/coverage_view_cancelProcess.php');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $_SESSION[$guid]['address']);
    $form->addHiddenValue('gibbonStaffCoverageID', $gibbonStaffCoverageID);

    $form->addRow()->addHeading(__('Cancel Coverage Request'));

    // Staff Card
    $gibbonPersonIDStatus = !empty($coverage['gibbonPersonID'])? $coverage['gibbonPersonID'] : $coverage['gibbonPersonIDStatus'];
    $page->writeFromTemplate('users/staffCard.twig.html', $container->get(StaffCard::class)->compose($gibbonPersonIDStatus));

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

    $row = $form->addRow();
        $row->addLabel('notesStatus', __('Reply'));
        $row->addTextArea('notesStatus')->setRows(3);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();
    
    echo $form->getOutput();
}
