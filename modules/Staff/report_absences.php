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

if (isActionAccessible($guid, $connection2, '/modules/Staff/report_absences_calendar.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Staff Absences'));

    $page->scripts->add('chart');

    $dateFormat = $_SESSION[$guid]['i18n']['dateFormatPHP'];
    $date = isset($_REQUEST['date'])? DateTimeImmutable::createFromFormat($dateFormat, $_REQUEST['date']) :new DateTimeImmutable();

    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);
    $staffAbsenceTypeGateway = $container->get(StaffAbsenceTypeGateway::class);


    // DATE SELECTOR

    $form = Form::create('action', $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/report_absences.php');
	$form->setClass('blank fullWidth');
	$form->addHiddenValue('address', $_SESSION[$guid]['address']);

	$row = $form->addRow();

	$link = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/report_absences.php';
	$prevDay = $date->modify('-1 day')->format($dateFormat);
	$nextDay = $date->modify('+1 day')->format($dateFormat);

	$col = $row->addColumn()->addClass('inline');
		$col->addButton(__('Previous Day'))->addClass('buttonLink')->onClick("window.location.href='{$link}&date={$prevDay}'");
		$col->addButton(__('Next Day'))->addClass('buttonLink')->onClick("window.location.href='{$link}&date={$nextDay}'");

	$col = $row->addColumn()->addClass('inline right');
		$col->addDate('date')->setValue($date->format($dateFormat))->setClass('shortWidth');
		$col->addSubmit(__('Go'));

    echo $form->getOutput();

    if (!isSchoolOpen($guid, $date->format('Y-m-d'), $connection2)) {
        echo Format::alert(__('School is closed on the specified day.'));
        return;
    }

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

    $dateStart = $date->modify('Monday this week')->format('Y-m-d');
    $dateEnd = $date->modify('Friday this week')->format('Y-m-d');
    
    $absences = $staffAbsenceDateGateway->selectAbsenceDatesByDateRange($dateStart, $dateEnd)->fetchAll();
    $absenceTypes = $staffAbsenceTypeGateway->selectAllTypes()->fetchAll();

    $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

    $chartData = array_fill_keys(array_column($absenceTypes, 'name'), array_fill_keys($weekdays, 0));

    

    foreach ($absences as $absence) {
        $weekday = DateTime::createFromFormat('Y-m-d', $absence['date'])->format('D');
        $chartData[$absence['type']][$weekday] += 1;
    }

    $barGraph = Chart::create('staffAbsences', 'bar')
        ->setTitle('This Week')
        ->setOptions($chartConfig)
        ->setLabels($weekdays)
        ->setLegend(false);

    foreach ($absenceTypes as $type) {
        $data = array_values($chartData[$type['name']]);
        $barGraph->addDataset($type['name'], __($type['name']))->setData($data);
    }

    echo $barGraph->render();
 


    // QUERY
    $criteria = $staffAbsenceGateway->newQueryCriteria()
        ->sortBy('date', 'DESC')
        ->fromPOST();

    $absences = $staffAbsenceGateway->queryAbsencesByDateRange($criteria, $date->format('Y-m-d'));

    // DATA TABLE
    $table = DataTable::create('staffAbsences');
    $table->setTitle($date->format('Y-m-d') == date('Y-m-d')  ? __('Today') : $date->format('l'));
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
