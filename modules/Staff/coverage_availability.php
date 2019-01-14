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

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_availability.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs
        ->add(__('My Coverage'), 'coverage_my.php')
        ->add(__('Edit Availability'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, [
            'success1' => __('Your request was completed successfully.').' '.__('You may now continue by submitting a coverage request for this absence.')
        ]);
    }

    $gibbonPersonIDCoverage = $_GET['gibbonPersonIDCoverage'] ?? $_SESSION[$guid]['gibbonPersonID'];

    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);

    if (empty($gibbonPersonIDCoverage)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // DATA TABLE
    $dates = $staffCoverageGateway->selectCoverageExceptionsByPerson($gibbonPersonIDCoverage);
    
    $table = DataTable::create('staffAvailabilityExceptions');
    $table->setTitle(__('Dates'));

    $table->addColumn('date', __('Date'))
        ->format(Format::using('dateReadable', 'date'));

    $table->addColumn('status', __('Availability'))
        ->format(function ($date) {
            return Format::small(__('Not Available'));
        });

    $table->addActionColumn()
        ->addParam('gibbonPersonIDCoverage', $gibbonPersonIDCoverage)
        ->addParam('gibbonStaffCoverageExceptionID')
        ->format(function ($date, $actions) {
            $actions->addAction('deleteInstant', __('Delete'))
                    ->setIcon('garbage')
                    ->isDirect()
                    ->setURL('/modules/Staff/coverage_availability_deleteProcess.php')
                    ->addConfirmation(__('Are you sure you wish to delete this record?'));
        });

    echo $table->render($dates->toDataSet());



    $form = Form::create('staffAvailability', $_SESSION[$guid]['absoluteURL'].'/modules/Staff/coverage_availability_addProcess.php');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('smallIntBorder fullWidth');
    $form->addHiddenValue('address', $_SESSION[$guid]['address']);
    $form->addHiddenValue('gibbonPersonIDCoverage', $gibbonPersonIDCoverage);

    $form->addRow()->addHeading(__('Add'));

    $row = $form->addRow();
        $row->addLabel('dateStart', __('Start Date'));
        $row->addDate('dateStart')->to('dateEnd')->isRequired();

    $row = $form->addRow();
        $row->addLabel('dateEnd', __('End Date'));
        $row->addDate('dateEnd')->from('dateStart');

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
