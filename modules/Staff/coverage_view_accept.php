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
use Gibbon\Domain\Staff\StaffCoverageGateway;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Domain\Staff\SubstituteGateway;
use Gibbon\Module\Staff\Forms\StaffCard;
use Gibbon\Domain\User\UserGateway;

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_view_accept.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs
        ->add(__('My Coverage'), 'coverage_my.php')
        ->add(__('Accept Coverage Request'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, [
            'success1' => __('Your request was completed successfully.'),
            'warning3' => __('This coverage request has already been accepted.'),
        ]);
    }

    $gibbonStaffCoverageID = $_GET['gibbonStaffCoverageID'] ?? '';

    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);

    if (empty($gibbonStaffCoverageID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $coverage = $staffCoverageGateway->getCoverageDetailsByID($gibbonStaffCoverageID);
    if (empty($coverage)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    if ($coverage['status'] != 'Requested') {
        $page->addWarning(__('This coverage request has already been accepted.'));
        return;
    }

    

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
        'comment' => $coverage['notesStatus'],
    ]);

    // FORM
    $form = Form::create('staffCoverage', $_SESSION[$guid]['absoluteURL'].'/modules/Staff/coverage_view_acceptProcess.php');
    $form->setClass('blank w-full');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $_SESSION[$guid]['address']);
    $form->addHiddenValue('gibbonStaffCoverageID', $gibbonStaffCoverageID);

    // DATA TABLE
    $coverageDates = $container->get(StaffAbsenceDateGateway::class)->selectDatesByCoverage($gibbonStaffCoverageID);

    $gibbonPersonID = !empty($coverage['gibbonPersonIDCoverage']) ? $coverage['gibbonPersonIDCoverage'] : $_SESSION[$guid]['gibbonPersonID'];
    $unavailable = $container->get(SubstituteGateway::class)->selectUnavailableDatesBySub($gibbonPersonID, $gibbonStaffCoverageID)->fetchGrouped();

    $table = DataTable::create('staffCoverageDates');
    $table->setTitle(__('Dates'));
    $table->getRenderer()->addData('class', 'bulkActionForm datesTable mb-4');

    $table->addColumn('date', __('Date'))
        ->format(Format::using('dateReadable', 'date'));

    $table->addColumn('timeStart', __('Time'))
        ->format(function ($coverage) {
            if ($coverage['allDay'] == 'N') {
                return Format::small(Format::timeRange($coverage['timeStart'], $coverage['timeEnd']));
            } else {
                return Format::small(__('All Day'));
            }
        });

    $datesAvailableToRequest = 0;
    $table->addCheckboxColumn('coverageDates', 'date')
        ->width('15%')
        ->checked(true)
        ->format(function ($coverage) use (&$datesAvailableToRequest, &$unavailable) {
            // Has this date already been requested?
            if (empty($coverage['gibbonStaffCoverageID'])) return __('N/A');

            // Is this date unavailable: absent, already booked, or has an availability exception
            if (isset($unavailable[$coverage['date']])) {
                $times = $unavailable[$coverage['date']];

                foreach ($times as $time) {
                    // Handle full day and partial day unavailability
                    if ($time['allDay'] == 'Y' 
                    || ($time['allDay'] == 'N' && $coverage['allDay'] == 'Y')
                    || ($time['allDay'] == 'N' && $coverage['allDay'] == 'N'
                        && $time['timeStart'] <= $coverage['timeEnd']
                        && $time['timeEnd'] >= $coverage['timeStart'])) {
                        return Format::small(__($time['status'] ?? 'Not Available'));
                    }
                }
            }

            $datesAvailableToRequest++;
        });

    $row = $form->addRow()->addContent($table->render($coverageDates->toDataSet()));
  
    $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full');

    $table->addRow()->addHeading(__('Accept Coverage Request'));

    if ($datesAvailableToRequest > 0) {
        $row = $table->addRow();
            $row->addLabel('notesCoverage', __('Reply'));
            $row->addTextArea('notesCoverage')->setRows(3)->setClass('w-full sm:max-w-xs');

        $row = $table->addRow();
            $row->addContent();
            $row->addSubmit();
    } else {
        $row = $table->addRow();
            $row->addAlert(__('Not Available'), 'warning');
    }

    echo $form->getOutput();
}
