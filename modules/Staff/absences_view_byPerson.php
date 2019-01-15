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
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Domain\Staff\StaffAbsenceTypeGateway;
use Gibbon\Domain\School\SchoolYearGateway;
use Gibbon\Domain\DataSet;

if (isActionAccessible($guid, $connection2, '/modules/Staff/absences_view_byPerson.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('View Absences'));

    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    $gibbonSchoolYearID = $_SESSION[$guid]['gibbonSchoolYearID'];

    $schoolYearGateway = $container->get(SchoolYearGateway::class);
    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);
    $staffAbsenceTypeGateway = $container->get(StaffAbsenceTypeGateway::class);

    if ($highestAction == 'View Absences_any') {

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

    
    $absences = $staffAbsenceDateGateway->selectAbsenceDatesByPerson($gibbonPersonID)->fetchGrouped();
    $schoolYear = $schoolYearGateway->getSchoolYearByID($gibbonSchoolYearID);

    // CALENDAR VIEW
    $calendar = [];
    $dateRange = new DatePeriod(
        new DateTime(substr($schoolYear['firstDay'], 0, 7).'-01'),
        new DateInterval('P1M'),
        new DateTime($schoolYear['lastDay'])
    );

    foreach ($dateRange as $month) {
        $days = [];
        for ($dayCount = 1; $dayCount <= $month->format('t'); $dayCount++) {
            $date = new DateTime($month->format('Y-m').'-'.$dayCount);
            $absenceListByDay = $absences[$date->format('Y-m-d')] ?? [];
            $absenceCount = count($absenceListByDay);

            $days[$dayCount] = [
                'date'    => $date,
                'number'  => $dayCount,
                'count'   => $absenceCount,
                'weekend' => $date->format('N') >= 6,
                'absence' => current($absenceListByDay),
            ];
        }

        $calendar[] = [
            'name'  => $month->format('M'),
            'days'  => $days,
        ];
    }

    $table = DataTable::create('staffAbsenceCalendar');
    $table->setTitle(__('Calendar'));
    $table->getRenderer()->setClass('calendarTable calendarTableSmall');

    $table->addColumn('name', '')->notSortable();

    for ($dayCount = 1; $dayCount <= 31; $dayCount++) {
        $table->addColumn($dayCount, '')
            ->notSortable()
            ->format(function ($month) use ($guid, $dayCount) {
                $day = $month['days'][$dayCount] ?? null;
                if (empty($day) || $day['count'] <= 0) return '';

                $url = $_SESSION[$guid]['absoluteURL'].'/fullscreen.php?q=/modules/Staff/absences_view_details.php&gibbonStaffAbsenceID='.$day['absence']['gibbonStaffAbsenceID'].'&width=800&height=550';
                $title = $day['date']->format('l').'<br/>'.$day['date']->format('M j, Y');
                $title .= '<br/>'.$day['absence']['type'];
                $class = $day['absence']['allDay'] == 'Y' ? 'thickbox' : 'thickbox half-day';

                return Format::link($url, $day['number'], ['title' => $title, 'class' => $class]);
            })
            ->modifyCells(function ($month, $cell) use ($dayCount) {
                $day = $month['days'][$dayCount] ?? null;
                if (empty($day)) return '';

                if ($day['date']->format('Y-m-d') == date('Y-m-d')) $cell->addClass('today');
                
                if ($day['count'] > 0) $cell->addClass('bg-color'.($day['absence']['sequenceNumber'] % 10));
                elseif ($day['weekend']) $cell->addClass('weekend');
                else $cell->addClass('day');

                return $cell;
            });
    }

    echo $table->render(new DataSet($calendar));
    echo '<br/>';

    // COUNT TYPES
    $absenceTypes = $staffAbsenceTypeGateway->selectAllTypes()->fetchAll();
    $types = array_fill_keys(array_column($absenceTypes, 'name'), 0);

    foreach ($absences as $days) {
        foreach ($days as $absence) {
            $types[$absence['type']] += $absence['allDay'] == 'Y' ? 1 : 0.5;
        }
    }

    $table = DataTable::create('staffAbsenceTypes');

    foreach ($types as $name => $count) {
        $table->addColumn($name, $name)->width((100 / count($types)).'%');
    }

    echo $table->render(new DataSet([$types]));

    // QUERY
    $criteria = $staffAbsenceGateway->newQueryCriteria()
        ->sortBy('date', 'DESC')
        ->fromPOST();

    $absences = $staffAbsenceGateway->queryAbsencesByPerson($criteria, $gibbonPersonID);

    // Join a set of coverage data per absence
    $absenceIDs = $absences->getColumn('gibbonStaffAbsenceID');
    $coverageData = $staffAbsenceDateGateway->selectDatesByAbsence($absenceIDs)->fetchGrouped();
    $absences->joinColumn('gibbonStaffAbsenceID', 'coverageList', $coverageData);

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
            $output = Format::dateRangeReadable($absence['dateStart'], $absence['dateEnd']);
            if ($absence['allDay'] == 'Y') {
                $output .= '<br/>'.Format::small(__n('{count} Day', '{count} Days', $absence['days']));
            } else {
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
        ->width('20%')
        ->format(function ($absence) {
            $output = Format::relativeTime($absence['timestampCreator']);
            if ($absence['gibbonPersonID'] != $absence['gibbonPersonIDCreator']) {
                $output .= '<br/>'.Format::small(__('By').' '.Format::name('', $absence['preferredNameCreator'], $absence['surnameCreator'], 'Staff', false, true));
            }
            return $output;
        });

    // ACTIONS
    $canManage = isActionAccessible($guid, $connection2, '/modules/Staff/absences_manage.php');

    $table->addActionColumn()
        ->addParam('gibbonStaffAbsenceID')
        ->addParam('search', $criteria->getSearchText(true))
        ->format(function ($absence, $actions) use ($canManage) {
            $actions->addAction('view', __('View Details'))
                ->isModal(800, 550)
                ->setURL('/modules/Staff/absences_view_details.php');

            if ($canManage) {
                $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/Staff/absences_manage_edit.php');

                $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/Staff/absences_manage_delete.php');
            }
        });

    echo $table->render($absences);

}
