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
use Gibbon\Domain\Staff\StaffAbsenceTypeGateway;
use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Domain\Staff\StaffCoverageGateway;

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_request.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs->add(__('Coverage Request'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, [
            'success1' => __('Your request was completed successfully.').' '.__('You may now continue by submitting a coverage request for this absence.')
        ]);
    }

    $gibbonStaffAbsenceID = $_GET['gibbonStaffAbsenceID'] ?? '';
    $gibbonPersonIDCoverage = $_GET['gibbonPersonIDCoverage'] ?? '';

    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);
    $staffAbsenceTypeGateway = $container->get(StaffAbsenceTypeGateway::class);

    if (empty($gibbonStaffAbsenceID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $values = $staffAbsenceGateway->getByID($gibbonStaffAbsenceID);

    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $form = Form::create('staffAbsenceEdit', $_SESSION[$guid]['absoluteURL'].'/modules/Staff/coverage_requestProcess.php');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('smallIntBorder fullWidth');
    $form->addHiddenValue('address', $_SESSION[$guid]['address']);
    $form->addHiddenValue('gibbonStaffAbsenceID', $gibbonStaffAbsenceID);

    $form->addRow()->addHeading(__('Coverage Request'));

    $row = $form->addRow();
        $row->addLabel('gibbonPersonIDCoverage', __('Substitute'));
        $row->addSelectSubstitute('gibbonPersonIDCoverage')->placeholder()->selected($gibbonPersonIDCoverage);

    $row = $form->addRow();
        $row->addLabel('notesRequested', __('Comment'));
        $row->addTextArea('notesRequested')->setRows(3);

    // DATA TABLE
    $absenceDates = $staffAbsenceDateGateway->selectDatesByAbsence($gibbonStaffAbsenceID);
    $unavailable = $staffCoverageGateway->selectUnavailableDatesByPerson($gibbonPersonIDCoverage)->fetchKeyPair();

    $table = DataTable::create('staffAbsenceDates');
    $table->setTitle(__('Dates'));

    $table->addColumn('date', __('Date'))
        ->format(Format::using('dateReadable', 'date'));

    $table->addColumn('timeStart', __('Time'))
        ->format(function ($absence) {
            if ($absence['allDay'] == 'N') {
                return Format::small(Format::timeRange($absence['timeStart'], $absence['timeEnd']));
            } else {
                return Format::small(__('All Day'));
            }
        });

    $datesAvailableToRequest = 0;
    $table->addColumn('requestDates', __('Request'))
        ->width('12%')
        ->format(function ($absence) use ($form, &$datesAvailableToRequest, &$unavailable) {
            // Has this date already been requested?
            if (!empty($absence['gibbonStaffCoverageID'])) return __('Requested');

            // Is this date unavailable: absent, already booked, or has an availability exception
            if (isset($unavailable[$absence['date']])) return Format::small(__('Not Available'));

            $datesAvailableToRequest++;

            return $form
                ->getFactory()
                ->createCheckbox('requestDates[]')
                ->setID('requestDates-'.$absence['date'])
                ->setValue($absence['date'])
                ->checked($absence['date'])
                ->getOutput();
        });

    $row = $form->addRow()->addContent($table->render($absenceDates->toDataSet()));

    if ($datesAvailableToRequest > 0) {
        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();
    }

    echo $form->getOutput();
}
