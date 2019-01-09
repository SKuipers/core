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

use Gibbon\UI\Chart\Chart;
use Gibbon\Services\Format;
use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Tables\DataTable;
use Gibbon\Forms\Form;
use Gibbon\Domain\Staff\StaffAbsenceTypeGateway;
use Gibbon\Domain\DataSet;

if (isActionAccessible($guid, $connection2, '/modules/Staff/report_absences_weekly.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Weekly Absences'));

    $page->scripts->add('chart');

    $dateFormat = $_SESSION[$guid]['i18n']['dateFormatPHP'];
    $date = isset($_REQUEST['dateStart'])? DateTimeImmutable::createFromFormat($dateFormat, $_REQUEST['dateStart']) :new DateTimeImmutable();

    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);
    $staffAbsenceTypeGateway = $container->get(StaffAbsenceTypeGateway::class);

    // DATE SELECTOR
    $form = Form::create('action', $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/report_absences_weekly.php');
	$form->setClass('blank fullWidth');
	$form->addHiddenValue('address', $_SESSION[$guid]['address']);

	$row = $form->addRow();

	$link = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/report_absences_weekly.php';
	$lastWeek = $date->modify('-1 week')->format($dateFormat);
	$thisWeek = (new DateTime('Today'))->format($dateFormat);
	$nextWeek = $date->modify('+1 week')->format($dateFormat);

	$col = $row->addColumn()->addClass('inline');
		$col->addButton(__('Last Week'))->addClass('buttonLink')->onClick("window.location.href='{$link}&dateStart={$lastWeek}'");
		$col->addButton(__('This Week'))->addClass('buttonLink')->onClick("window.location.href='{$link}&dateStart={$thisWeek}'");
		$col->addButton(__('Next Week'))->addClass('buttonLink')->onClick("window.location.href='{$link}&dateStart={$nextWeek}'");

	$col = $row->addColumn()->addClass('inline right');
		$col->addDate('dateStart')->setValue($date->format($dateFormat))->setClass('shortWidth');
		$col->addSubmit(__('Go'));

    echo $form->getOutput();

    // SETUP DAYS OF WEEK
    $sql = "SELECT name, nameShort FROM gibbonDaysOfWeek WHERE schoolDay='Y' ORDER BY sequenceNumber";
    $result = $pdo->select($sql)->fetchAll();
    
    $weekdays = array_map(function ($weekday) use ($date){
        $weekday['date'] = $date->modify($weekday['name'].' this week');
        return $weekday;
    }, $result);

    $weekdayNames = array_map('__', array_column($weekdays, 'nameShort'));

    $dateStart =  $weekdays[0]['date'];
    $dateEnd = $weekdays[count($weekdays) - 1]['date'];

    // BAR GRAPH
    $chartConfig = [
        'height' => '70',
        'tooltips' => [
            'mode' => 'x-axis',
        ],
        'scales' => [
            'yAxes' => [[
                'stacked' => true,
                'display' => false,
                'ticks'     => ['stepSize' => 1, 'suggestedMax' => 5],
            ]],
            'xAxes' => [[
                'display'   => true,
                'stacked'   => true,
                'gridLines' => ['display' => false],
            ]],
        ],
    ];

    
    
    // QUERY
    $criteria = $staffAbsenceGateway->newQueryCriteria()
        ->sortBy('date')
        ->sortBy('sequenceNumber')
        ->fromPOST();

    $absencesThisWeek = $staffAbsenceGateway->queryAbsencesByDateRange($criteria, $dateStart->format('Y-m-d'), $dateEnd->format('Y-m-d'), false)->toArray();
    $absenceTypes = $staffAbsenceTypeGateway->selectAllTypes()->fetchAll();

    $listData = [];
    $chartData = array_fill_keys($weekdayNames, 0);
    // $chartData = array_fill_keys(array_column($absenceTypes, 'name'), array_fill_keys($weekdayNames, 0));

    // foreach ($absencesThisWeek as $absence) {
    //     $weekday = __(DateTime::createFromFormat('Y-m-d', $absence['date'])->format('D'));
    //     $chartData[$absence['type']][$weekday] += 1;
    //     $listData[$absence['date']][] = $absence;
    // }

    foreach ($absencesThisWeek as $absence) {
        $weekday = __(DateTime::createFromFormat('Y-m-d', $absence['date'])->format('D'));
        $chartData[$weekday] += 1;
        $listData[$absence['date']][] = $absence;
    }

    $barGraph = Chart::create('staffAbsences', 'bar')
        ->setTitle(Format::dateRangeReadable($dateStart->format('Y-m-d'), $dateEnd->format('Y-m-d')))
        ->setOptions($chartConfig)
        ->setLabels($weekdayNames)
        ->setLegend(false)
        ->setColors(['hsl(260, 90%, 70%)']);
        // ->setColors(['hsl(260, 90%, 70%)', 'hsl(260, 90%, 75%)', 'hsl(260, 90%, 80%)', 'hsl(260, 90%, 85%)', 'hsl(260, 90%, 90%)']);

    $barGraph->addDataset('absent', __('Absent'))->setData($chartData);

    // foreach ($absenceTypes as $type) {
    //     $data = array_values($chartData[$type['name']]);
    //     $barGraph->addDataset($type['name'], __($type['name']))->setData($data);
    // }

    echo '<div style="height: 175px;overflow: visible;">'.$barGraph->render().'</div>';

    if (empty($listData)) {
        echo Format::alert(__('There are no absences for this time period.'), 'message');
        return;
    }

    
    foreach ($weekdays as $weekday) {
        $date = $weekday['date'];

        $absencesThisDay = $listData[$date->format('Y-m-d')] ?? [];

        if (empty($absencesThisDay)) {
            continue;
        }

        if (!isSchoolOpen($guid, $date->format('Y-m-d'), $connection2)) {
            echo '<h2>'.__($date->format('l')).'</h2>';
            echo Format::alert(__('School is closed on the specified day.'));
            continue;
        }

        $table = DataTable::create('staffAbsences'.$date->format('D'));
        $table->setTitle(__($date->format('l')));
        $table->setDescription(Format::dateReadable($date->format('Y-m-d')));

        // COLUMNS
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

        echo $table->render(new DataSet($absencesThisDay));
    }
}
