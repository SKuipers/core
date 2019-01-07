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
use Gibbon\Domain\Staff\StaffAbsenceGateway;

if (isActionAccessible($guid, $connection2, '/modules/Staff/absences_view_byPerson.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('View Absences by Person'));

    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);

    if ($highestAction == 'View Absences by Person_any') {

        $gibbonPersonID = $_GET['gibbonPersonID'] ?? $_SESSION[$guid]['gibbonPersonID'];

        $form = Form::create('filter', $_SESSION[$guid]['absoluteURL'].'/index.php', 'get');
        $form->setFactory(DatabaseFormFactory::create($pdo));
        $form->setTitle(__('Filter'));
        $form->setClass('noIntBorder fullWidth');

        $form->addHiddenValue('address', $_SESSION[$guid]['address']);
        $form->addHiddenValue('q', '/modules/Staff/absences_view_byPerson.php');

        $row = $form->addRow();
            $row->addLabel('gibbonPersonID', __('Person'));
            $row->addSelectStaff('gibbonPersonID')->selected($gibbonPersonID);

        $row = $form->addRow();
            $row->addFooter();
            $row->addSearchSubmit($gibbon->session);

        echo $form->getOutput();
    } else {
        $gibbonPersonID = $_SESSION[$guid]['gibbonPersonID'];
    }

    // QUERY
    $criteria = $staffAbsenceGateway->newQueryCriteria()
        ->sortBy('date', 'DESC')
        ->fromPOST();

    $absences = $staffAbsenceGateway->queryAbsencesByPerson($criteria, $gibbonPersonID);

    // DATA TABLE
    $table = DataTable::createPaginated('staffAbsences', $criteria);
    $table->setTitle(__('View'));

    $table->addHeaderAction('add', __('New Absence'))
        ->setURL('/modules/Staff/absences_manage_add.php')
        ->addParam('gibbonPersonID', $gibbonPersonID)
        ->displayLabel();

    // COLUMNS
    $table->addColumn('date', __('Date'))
        ->width('22%')
        ->format(function ($absence) {
            $output = $absence['days'] > 1 
                ? Format::dateRangeReadable($absence['timestampStart'], $absence['timestampEnd'])
                : Format::dateReadable($absence['date']);
            if ($absence['allDay'] == 'Y') {
                $output .= '<br/>'.Format::small(__n('{count} Day', '{count} Days', $absence['days']));
            } else {
                $output .= '<br/>'.Format::small(Format::timeRange($absence['timestampStart'], $absence['timestampEnd']));
            }
            
            return $output;
        });
        
    $table->addColumn('type', __('Type'))
        ->description(__('Reason'))
        ->format(function ($absence) {
            return $absence['type'] .'<br/>'.Format::small($absence['reason']);
        });

    $table->addColumn('comment', __('Comment'));

    $table->addColumn('created', __('Created'))
        ->width('20%')
        ->format(function ($absence) {
            $output = Format::time($absence['timestampCreator'], 'M j, Y H:i');
            if ($absence['gibbonPersonID'] != $absence['gibbonPersonIDCreator']) {
                $output .= '<br/>'.Format::small(__('By').' '.Format::name('', $absence['preferredNameCreator'], $absence['surnameCreator'], 'Staff', false, true));
            }
            return $output;
        });

    // ACTIONS
    $table->addActionColumn()
        ->addParam('gibbonPersonID')
        ->addParam('search', $criteria->getSearchText(true))
        ->format(function ($person, $actions) use ($guid) {
            $actions->addAction('view', __('View Details'))
                    ->setURL('/modules/Staff/absences_view_details.php');
        });

    echo $table->render($absences);

}
