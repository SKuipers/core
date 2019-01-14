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
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;

if (isActionAccessible($guid, $connection2, '/modules/Staff/absences_manage.php') == false) {
    //Acess denied
    echo "<div class='error'>";
    echo __('You do not have access to this action.');
    echo '</div>';
} else {
    //Proceed!
    $page->breadcrumbs->add(__('Manage Staff Absences'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    $gibbonSchoolYearID = $_SESSION[$guid]['gibbonSchoolYearID'];
    $gibbonStaffAbsenceTypeID = $_GET['gibbonStaffAbsenceTypeID'] ?? '';
    $search = $_GET['search'] ?? '';
    $dateStart = $_GET['dateStart'] ?? '';
    $dateEnd = $_GET['dateEnd'] ?? '';

    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffAbsenceTypeGateway = $container->get(StaffAbsenceTypeGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);
    
    $form = Form::create('filter', $_SESSION[$guid]['absoluteURL'].'/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/Staff/absences_manage.php');

    $row = $form->addRow();
        $row->addLabel('search', __('Search'));
        $row->addTextField('search')->setValue($search);

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
        $row->addSearchSubmit($gibbon->session, __('Clear Filters'));

    echo $form->getOutput();


    // QUERY
    $criteria = $staffAbsenceGateway->newQueryCriteria()
        ->searchBy($staffAbsenceGateway->getSearchableColumns(), $search)
        ->sortBy('date', 'ASC')
        ->filterBy('startDate', Format::dateConvert($dateStart))
        ->filterBy('endDate', Format::dateConvert($dateEnd))
        ->filterBy('type', $gibbonStaffAbsenceTypeID);

    $criteria->filterBy('date', !$criteria->hasFilter() && !$criteria->hasSearchText() ? 'upcoming' : '')
        ->fromPOST();

    $absences = $staffAbsenceGateway->queryAbsencesBySchoolYear($criteria, $gibbonSchoolYearID, true);

    // Join a set of coverage data per absence
    $absenceIDs = $absences->getColumn('gibbonStaffAbsenceID');
    $coverageData = $staffAbsenceDateGateway->selectDatesByAbsence($absenceIDs)->fetchGrouped();
    $absences->joinColumn('gibbonStaffAbsenceID', 'coverageList', $coverageData);

    // DATA TABLE
    $table = DataTable::createPaginated('staffAbsences', $criteria);
    $table->setTitle(__('View'));

    if (isActionAccessible($guid, $connection2, '/modules/Staff/report_absences_summary.php')) {
        $table->addHeaderAction('view', __('View'))
            ->setIcon('planner')
            ->setURL('/modules/Staff/report_absences_summary.php')
            ->displayLabel()
            ->append('&nbsp;|&nbsp;');
    }

    $table->addHeaderAction('add', __('New Absence'))
        ->setURL('/modules/Staff/absences_manage_add.php')
        ->addParam('gibbonPersonID', '')
        ->addParam('date', $dateStart)
        ->displayLabel();
    
    $table->addMetaData('filterOptions', [
        'date:upcoming'    => __('Upcoming'),
        'date:today'       => __('Today'),
        'date:past'        => __('Past'),
        'status:requested' => __('Coverage').': '.__('Requested'),
        'status:accepted'  => __('Coverage').': '.__('Accepted'),
        'status:declined'  => __('Coverage').': '.__('Declined'),
        'status:cancelled' => __('Coverage').': '.__('Cancelled'),
    ]);

    // COLUMNS
    $table->addColumn('fullName', __('Name'))
        ->sortable(['surname', 'preferredName'])
        ->format(function ($absence) use ($guid) {
            $text = Format::name($absence['title'], $absence['preferredName'], $absence['surname'], 'Staff', false, true);
            $url = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/absences_view_byPerson.php&gibbonPersonID='.$absence['gibbonPersonID'];

            return Format::link($url, $text);
        });

    $table->addColumn('date', __('Date'))
        ->width('18%')
        ->format(function ($absence) {
            $output = Format::dateRangeReadable($absence['dateStart'], $absence['dateEnd']);
            if ($absence['days'] > 1) {
                $output .= '<br/>'.Format::small(__n('{count} Day', '{count} Days', $absence['days']));
            } elseif ($absence['allDay'] == 'N') {
                $output .= '<br/>'.Format::small(Format::timeRange($absence['timeStart'], $absence['timeEnd']));
            }
            
            return $output;
        });

    $table->addColumn('type', __('Type'))
        ->description(__('Reason'))
        ->format(function ($absence) {
            return $absence['type'] .'<br/>'.Format::small($absence['reason']);
        });


    $table->addColumn('coverage', __('Coverage'))
        ->format(function ($absence) {
            if (empty($absence['coverage']) || empty($absence['coverageList'])) {
                return '';
            } elseif ($absence['coverage'] != 'Accepted') {
                return '<div class="badge success">'.__('Pending').'</div>';
            }

            $names = array_unique(array_map(function ($person) {
                return $person['coverage'] == 'Accepted'
                    ? Format::name($person['titleCoverage'], $person['preferredNameCoverage'], $person['surnameCoverage'], 'Staff', false, true)
                    : '<div class="badge success">'.__('Pending').'</div>';
            }, $absence['coverageList'] ?? []));

            return implode('<br/>', $names);
        });


    $table->addColumn('timestampCreator', __('Created'))
        ->format(function ($absence) {
            $output = Format::relativeTime($absence['timestampCreator'], 'M j, Y H:i');
            if ($absence['gibbonPersonID'] != $absence['gibbonPersonIDCreator']) {
                $output .= '<br/>'.Format::small(__('By').' '.Format::name('', $absence['preferredNameCreator'], $absence['surnameCreator'], 'Staff', false, true));
            }
            return $output;
        });

    // ACTIONS
    $table->addActionColumn()
        ->addParam('gibbonStaffAbsenceID')
        ->format(function ($person, $actions) use ($guid) {
            $actions->addAction('view', __('View Details'))
                ->isModal(800, 550)
                ->setURL('/modules/Staff/absences_view_details.php');

            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/Staff/absences_manage_edit.php');

            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/Staff/absences_manage_delete.php');
        });

    echo $table->render($absences);
}
