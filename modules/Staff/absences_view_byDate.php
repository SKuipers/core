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
use Gibbon\Domain\Staff\StaffAbsenceTypeGateway;

if (isActionAccessible($guid, $connection2, '/modules/Staff/absences_view_byDate.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('View Absences by Date'));

    $gibbonStaffAbsenceTypeID = $_GET['gibbonStaffAbsenceTypeID'] ?? '';
    $dateStart = $_GET['dateStart'] ?? Format::date(date('Y-m-d'));
    $dateEnd = $_GET['dateEnd'] ?? '';

    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffAbsenceTypeGateway = $container->get(StaffAbsenceTypeGateway::class);
    
    $form = Form::create('filter', $_SESSION[$guid]['absoluteURL'].'/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('address', $_SESSION[$guid]['address']);
    $form->addHiddenValue('q', '/modules/Staff/absences_view_byDate.php');

    $row = $form->addRow();
        $row->addLabel('dateStart', __('Start Date'));
        $row->addDate('dateStart')->setValue($dateStart);

    $row = $form->addRow();
        $row->addLabel('dateEnd', __('End Date'));
        $row->addDate('dateEnd')->setValue($dateEnd);

    $types = $staffAbsenceTypeGateway->selectAllTypes()->fetchAll();
    $types = array_combine(array_column($types, 'gibbonStaffAbsenceTypeID'), array_column($types, 'name'));
    
    $row = $form->addRow();
        $row->addLabel('gibbonStaffAbsenceTypeID', __('Type'));
        $row->addSelect('gibbonStaffAbsenceTypeID')
            ->fromArray(['' => __('All')])
            ->fromArray($types)
            ->selected($gibbonStaffAbsenceTypeID);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($gibbon->session);

    echo $form->getOutput();

    // QUERY
    $criteria = $staffAbsenceGateway->newQueryCriteria()
        ->sortBy('date', 'DESC')
        ->filterBy('type', $gibbonStaffAbsenceTypeID)
        ->fromPOST();

    $absences = $staffAbsenceGateway->queryAbsencesByDateRange($criteria, Format::dateConvert($dateStart), Format::dateConvert($dateEnd));

    // DATA TABLE
    $table = DataTable::createPaginated('staffAbsences', $criteria);
    $table->setTitle(__('View'));

    $table->addHeaderAction('add', __('New Absence'))
        ->setURL('/modules/Staff/absences_manage_add.php')
        ->addParam('date', $dateStart)
        ->displayLabel();

    // COLUMNS
    if ($dateStart != $dateEnd) {
        $table->addColumn('date', __('Date'))
            ->width('22%')
            ->format(function ($absence) {
                $output = Format::dateRangeReadable($absence['dateStart'], $absence['dateEnd']);
                if ($absence['allDay'] == 'Y') {
                    $output .= '<br/>'.Format::small(__n('{count} Day', '{count} Days', $absence['days']));
                } else {
                    $output .= '<br/>'.Format::small(Format::timeRange($absence['timeStart'], $absence['timeEnd']));
                }
                return $output;
            });
    }

    $table->addColumn('fullName', __('Name'))
        ->width('25%')
        ->sortable(['surname', 'preferredName'])
        ->format(function ($absence) {
            $output = Format::name($absence['title'], $absence['preferredName'], $absence['surname'], 'Staff', false, true);
            if ($absence['allDay'] != 'Y') {
                $output .= '<br/>'.Format::small(Format::timeRange($absence['timeStart'], $absence['timeEnd']));
            }
            return $output;
        });

    $table->addColumn('type', __('Type'))
        ->description(__('Reason'))
        ->format(function ($absence) {
            return $absence['type'] .'<br/>'.Format::small($absence['reason']);
        });
        
    $table->addColumn('comment', __('Comment'));

    $table->addColumn('timestampCreator', __('Created'))
        ->width('20%')
        ->format(function ($absence) {
            $output = Format::relativeTime($absence['timestampCreator']);
            if ($absence['gibbonPersonID'] != $absence['gibbonPersonIDCreator']) {
                $output .= '<br/>'.Format::small(__('By').' '.Format::name('', $absence['preferredNameCreator'], $absence['surnameCreator'], 'Staff', false, true));
            }
            return $output;
        });

    // ACTIONS
    $table->addActionColumn()
        ->addParam('gibbonStaffAbsenceID')
        ->addParam('search', $criteria->getSearchText(true))
        ->format(function ($person, $actions) use ($guid) {
            $actions->addAction('view', __('View Details'))
                ->isModal(800, 550)
                ->setURL('/modules/Staff/absences_view_details.php');
        });

    echo $table->render($absences);
}
