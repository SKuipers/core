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

use Gibbon\Services\Format;
use Gibbon\Tables\Prefab\ReportTable;
use Gibbon\Forms\Form;
use Gibbon\Domain\DataSet;
use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Domain\School\SchoolYearGateway;
use Gibbon\Domain\Staff\StaffAbsenceTypeGateway;

if (isActionAccessible($guid, $connection2, '/modules/Staff/report_absence_calendar.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $viewMode = $_REQUEST['format'] ?? '';
    $dateFormat = $_SESSION[$guid]['i18n']['dateFormatPHP'];
    $gibbonSchoolYearID = $_SESSION[$guid]['gibbonSchoolYearID'];
    $gibbonStaffAbsenceTypeID = $_GET['gibbonStaffAbsenceTypeID'] ?? '';

    $schoolYearGateway = $container->get(SchoolYearGateway::class);
    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffAbsenceTypeGateway = $container->get(StaffAbsenceTypeGateway::class);

    if (empty($viewMode)) {
        $page->breadcrumbs->add(__('Staff Absence Calendar'));

        $form = Form::create('filter', $_SESSION[$guid]['absoluteURL'].'/index.php', 'get');
        $form->setTitle(__('Filter'));
        $form->setClass('noIntBorder fullWidth');

        $form->addHiddenValue('address', $_SESSION[$guid]['address']);
        $form->addHiddenValue('sidebar', 'false');
        $form->addHiddenValue('q', '/modules/Staff/report_absence_calendar.php');

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
    }

    $criteria = $staffAbsenceGateway->newQueryCriteria()
        ->filterBy('type', $gibbonStaffAbsenceTypeID)
        ->fromPOST();

    $schoolYear = $schoolYearGateway->getSchoolYearByID($gibbonSchoolYearID);

    $absences = $staffAbsenceGateway->queryAbsencesBySchoolYear($criteria, $gibbonSchoolYearID)->toArray();
    $absences = array_reduce($absences, function ($group, $item) {
        $group[$item['date']][] = $item;
        return $group;
    }, []);
    
    $startDate = new DateTime($schoolYear['firstDay']);
    $endDate = new DateTime($schoolYear['lastDay']);

    $dateRange = new DatePeriod($startDate, new DateInterval('P1M'), $endDate);
    $calendar = [];
    $totalAbsence = 0;
    $maxAbsence = 0;

    foreach ($dateRange as $month) {
        $days = [];
        for ($dayCount = 1; $dayCount <= $month->format('t'); $dayCount++) {
            $date = new DateTime($month->format('Y-m').'-'.$dayCount);
            $absenceCount = count($absences[$date->format('Y-m-d')] ?? []);

            $days[$dayCount] = [
                'date'    => $date,
                'number'  => $dayCount,
                'count'   => $absenceCount,
                'weekend' => $date->format('N') >= 6,
            ];
            $totalAbsence += $absenceCount;
            $maxAbsence = max($absenceCount, $maxAbsence);
        }

        $calendar[] = [
            'name'  => $month->format('M'),
            'year'  => $month->format('Y'),
            'month' => $month->format('m'),
            'days'  => $days,
        ];
    }
    
    // DATA TABLE
    $table = ReportTable::createPaginated('staffAbsence', $criteria)->setViewMode($viewMode, $gibbon->session);
    $table->setTitle(__('Staff Absences'));
    $table->setDescription(__n('{count} Absence', '{count} Absences', $totalAbsence));
    $table->getRenderer()->setClass('mini calendarTable');

    $table->addMetaData('hidePagination', true);

    $table->addColumn('name', '')->notSortable();

    for ($dayCount = 1; $dayCount <= 31; $dayCount++) {
        $table->addColumn($dayCount, '')
            ->notSortable()
            ->format(function ($month) use ($guid, $dayCount, $gibbonStaffAbsenceTypeID, $dateFormat) {
                $day = $month['days'][$dayCount] ?? null;
                if (empty($day)) return '';

                $url = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/absence_view_byDate.php&dateStart='.$day['date']->format($dateFormat).'&gibbonStaffAbsenceTypeID='.$gibbonStaffAbsenceTypeID;
                $title = $day['date']->format('l');
                $title .= '<br/>'.$day['date']->format('M j, Y');
                $title .= '<br/>'.__n('{count} Absence', '{count} Absences', $day['count']);

                return Format::link($url, $day['number'], $title);
            })
            ->modifyCells(function ($month, $cell) use ($dayCount, $maxAbsence) {
                $day = $month['days'][$dayCount] ?? null;
                if (empty($day)) return '';

                $count = $day['count'] ?? 0;

                if ($day['date']->format('Y-m-d') == date('Y-m-d')) $cell->addClass('today');
                elseif ($count > ceil($maxAbsence * 0.8)) $cell->addClass('dayHighlight4');
                elseif ($count > ceil($maxAbsence * 0.5)) $cell->addClass('dayHighlight3');
                elseif ($count > ceil($maxAbsence * 0.2)) $cell->addClass('dayHighlight2');
                elseif ($count > 0) $cell->addClass('dayHighlight1');
                elseif ($day['weekend']) $cell->addClass('weekend');

                return $cell;
            });
    }

    echo $table->render(new DataSet($calendar));
}
