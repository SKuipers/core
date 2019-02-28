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

namespace Gibbon\Module\Staff\Tables;

use Gibbon\Services\Format;
use Gibbon\Domain\DataSet;
use Gibbon\Tables\DataTable;
use DateTime;
use DateInterval;
use DatePeriod;

/**
 * @version v18
 * @since   v18
 */
class CoverageCalendar
{
    public static function create($coverage, $exceptions, $dateStart, $dateEnd)
    {
        $calendar = [];
        $dateRange = new DatePeriod(
            new DateTime(substr($dateStart, 0, 7).'-01'),
            new DateInterval('P1M'),
            new DateTime($dateEnd)
        );

        $coverageByDate = array_reduce($coverage, function ($group, $item) {
            $group[$item['date']][] = $item;
            return $group;
        }, []);

        $exceptionsByDate = array_reduce($exceptions, function ($group, $item) {
            $group[$item['date']][] = $item;
            return $group;
        }, []);
    
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
                    'exception' => isset($exceptionsByDate[$date->format('Y-m-d')]) 
                        ? current($exceptionsByDate[$date->format('Y-m-d')]) 
                        : null,
                ];
            }
    
            $calendar[] = [
                'name'  => $month->format('M'),
                'days'  => $days,
            ];
        }
    
        $table = DataTable::create('staffAbsenceCalendar')
            ->setTitle(__('Calendar'));

        $table->getRenderer()->setClass('calendarTable calendarTableSmall');
    
        $table->addColumn('name', '')->notSortable();
    
        for ($dayCount = 1; $dayCount <= 31; $dayCount++) {
            $table->addColumn($dayCount, '')
                ->notSortable()
                ->format(function ($month) use ($dayCount) {
                    $day = $month['days'][$dayCount] ?? null;
                    if (empty($day) || ($day['count'] <= 0 && !$day['exception'])) return '';
    
                    $coverage = $day['coverage'];
    
                    $url = 'fullscreen.php?q=/modules/Staff/coverage_view_details.php&gibbonStaffCoverageID='.$coverage['gibbonStaffCoverageID'].'&width=800&height=550';

                    $params['title'] = $day['date']->format('l').'<br/>'.$day['date']->format('M j, Y');
                    $params['class'] = '';
                    if ($coverage['allDay'] == 'N') {
                        $params['class'] = $coverage['timeStart'] < '12:00:00' ? 'half-day-am' : 'half-day-pm';
                    }
                    
                    if ($day['count'] > 0) {
                        $name = Format::name($coverage['titleAbsence'], $coverage['preferredNameAbsence'], $coverage['surnameAbsence'], 'Staff', false, true);
                        if (empty($name)) {
                            $name = Format::name($coverage['titleStatus'], $coverage['preferredNameStatus'], $coverage['surnameStatus'], 'Staff', false, true);
                        }
                        $params['class'] .= ' thickbox';
                        $params['title'] .= '<br/>'.$name.'<br/>'.$coverage['status'];
                    } elseif ($day['exception']) {
                        if ($day['exception']['allDay'] == 'N') {
                            $params['class'] = $day['exception']['timeStart'] < '12:00:00' ? 'half-day-am' : 'half-day-pm';
                        }

                        $url = 'index.php?q=/modules/Staff/coverage_availability.php&gibbonPersonID='.$day['exception']['gibbonPersonID'];
                        $params['title'] .= '<br/>'.__($day['exception']['reason'] ?? 'Not Available');
                    }
    
                    return Format::link($url, $day['number'], $params);
                })
                ->modifyCells(function ($month, $cell) use ($dayCount) {
                    $day = $month['days'][$dayCount] ?? null;
                    if (empty($day)) return '';
    
                    if ($day['date']->format('Y-m-d') == date('Y-m-d')) $cell->addClass('today');
                    
                    switch ($day['coverage']['status']) {
                        case 'Requested': $cellColor = 'bg-color2'; break;
                        case 'Accepted':  $cellColor = 'bg-color0'; break;
                        default:          $cellColor = 'bg-grey';
                    }
                    
                    if ($day['count'] > 0) $cell->addClass($cellColor);
                    elseif ($day['exception']) $cell->addClass('bg-grey');
                    elseif ($day['weekend']) $cell->addClass('weekend');
                    else $cell->addClass('day');
    
                    return $cell;
                });
        }
    
        return $table->withData(new DataSet($calendar));
    }
}
