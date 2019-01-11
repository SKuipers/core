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
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\Staff\StaffCoverageGateway;
use Gibbon\Domain\School\SchoolYearGateway;
use Gibbon\Domain\DataSet;

if (isActionAccessible($guid, $connection2, '/modules/Staff/absences_view_byPerson.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('My Coverage'));

    $gibbonPersonID = $_SESSION[$guid]['gibbonPersonID'];
    $gibbonSchoolYearID = $_SESSION[$guid]['gibbonSchoolYearID'];
    
    $schoolYearGateway = $container->get(SchoolYearGateway::class);
    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);

    if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_request.php')) {

        $criteria = $staffCoverageGateway->newQueryCriteria()
            ->sortBy('date', 'DESC')
            ->fromPOST('staffCoverageSelf');

        $coverage = $staffCoverageGateway->queryCoverageByPersonAbsent($criteria, $gibbonPersonID);

        // DATA TABLE
        $table = DataTable::createPaginated('staffCoverageSelf', $criteria);
        $table->setTitle(__('Covering Me'));

        $table->modifyRows(function ($coverage, $row) {
            if ($coverage['status'] == 'Accepted') $row->addClass('current');
            return $row;
        });

        $table->addColumn('status', __('Status'))->width('15%');

        $table->addColumn('date', __('Date'))
            ->format(function ($coverage) {
                $output = Format::dateRangeReadable($coverage['dateStart'], $coverage['dateEnd']);
                if ($coverage['allDay'] == 'Y') {
                    $output .= '<br/>'.Format::small(__n('{count} Day', '{count} Days', $coverage['days']));
                } else {
                    $output .= '<br/>'.Format::small(Format::timeRange($coverage['timeStart'], $coverage['timeEnd']));
                }
                
                return $output;
            });

        $table->addColumn('requested', __('Coverage'))
            ->width('30%')
            ->sortable(['surname', 'preferredName'])
            ->format(function ($coverage) {
                return $coverage['gibbonPersonIDCoverage'] 
                    ? Format::name($coverage['titleCoverage'], $coverage['preferredNameCoverage'], $coverage['surnameCoverage'], 'Staff', false, true)
                    : Format::small(__('Pending'));
            });

        $table->addColumn('notesCoverage', __('Comment'));

        $table->addActionColumn()
            ->addParam('gibbonStaffCoverageID')
            ->format(function ($coverage, $actions) {
                $actions->addAction('view', __('View Details'))
                    ->isModal(800, 550)
                    ->setURL('/modules/Staff/coverage_view_details.php');
            });

        echo $table->render($coverage);
    }

    
    if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_accept.php')) {

        $criteria = $staffCoverageGateway->newQueryCriteria()
            ->sortBy('date', 'DESC')
            ->fromPOST('staffCoverageOther');

        $coverage = $staffCoverageGateway->queryCoverageByPersonCovering($criteria, $gibbonPersonID);

        $coverageByDate = array_reduce($coverage->toArray(), function ($group, $item) {
            $group[$item['date']][] = $item;
            return $group;
        }, []);
    
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
                $coverageListByDay = $coverageByDate[$date->format('Y-m-d')] ?? [];
                $coverageCount = count($coverageListByDay);
    
                $days[$dayCount] = [
                    'date'    => $date,
                    'number'  => $dayCount,
                    'count'   => $coverageCount,
                    'weekend' => $date->format('N') >= 6,
                    'coverage' => current($coverageListByDay),
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
    
                    $coverage = $day['coverage'];
    
                    // $url = $_SESSION[$guid]['absoluteURL'].'/fullscreen.php?q=/modules/Staff/absences_view_details.php&gibbonStaffAbsenceID='.$day['absence']['gibbonStaffAbsenceID'].'&width=800&height=550';
                    $url = '#';
                    $title = $day['date']->format('l').'<br/>'.$day['date']->format('M j, Y');
                    $title .= '<br/>'.Format::name($coverage['titleAbsence'], $coverage['preferredNameAbsence'], $coverage['surnameAbsence'], 'Staff', false, true);
                    $title .= '<br/>'.$coverage['status'];
    
                    return Format::link($url, $day['number'], ['title' => $title, 'class' => 'thickbox']);
                })
                ->modifyCells(function ($month, $cell) use ($dayCount) {
                    $day = $month['days'][$dayCount] ?? null;
                    if (empty($day)) return '';
    
                    if ($day['date']->format('Y-m-d') == date('Y-m-d')) $cell->addClass('today');
                    
                    if ($day['count'] > 0) $cell->addClass($day['coverage']['status'] == 'Requested' ? 'bg-color2' : 'bg-color0');
                    elseif ($day['weekend']) $cell->addClass('weekend');
                    else $cell->addClass('day');
    
                    return $cell;
                });
        }
    
        echo $table->render(new DataSet($calendar));
        echo '<br/>';



        // DATA TABLE
        $table = DataTable::createPaginated('staffCoverageOther', $criteria);
        $table->setTitle(__('Covering Them'));

        $table->modifyRows(function ($coverage, $row) {
            if ($coverage['status'] == 'Accepted') $row->addClass('current');
            return $row;
        });

        $table->addColumn('status', __('Status'))->width('15%');

        $table->addColumn('date', __('Date'))
            ->format(function ($coverage) {
                $output = Format::dateRangeReadable($coverage['dateStart'], $coverage['dateEnd']);
                if ($coverage['allDay'] == 'Y') {
                    $output .= '<br/>'.Format::small(__n('{count} Day', '{count} Days', $coverage['days']));
                } else {
                    $output .= '<br/>'.Format::small(Format::timeRange($coverage['timeStart'], $coverage['timeEnd']));
                }
                
                return $output;
            });

        $table->addColumn('requested', __('Person'))
            ->width('30%')
            ->sortable(['surname', 'preferredName'])
            ->format(function ($coverage) {
                return Format::name($coverage['titleAbsence'], $coverage['preferredNameAbsence'], $coverage['surnameAbsence'], 'Staff', false, true);
            });

        $table->addColumn('notesRequested', __('Comment'));

        $table->addActionColumn()
            ->addParam('gibbonStaffCoverageID')
            ->format(function ($coverage, $actions) {
                $actions->addAction('view', __('View Details'))
                    ->isModal(800, 550)
                    ->setURL('/modules/Staff/coverage_view_details.php');
            });

        echo $table->render($coverage);
    }
}
