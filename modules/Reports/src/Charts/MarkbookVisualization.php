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

namespace Gibbon\Module\Reports\Charts;

use Gibbon\UI\Chart\Chart;

class MarkbookVisualization
{
    protected $markbookEntries;
    protected $markbookWeights;

    protected $gradeAverage;

    public function __construct(array $markbookEntries = [], array $markbookWeights = [])
    {
        $this->markbookEntries = $markbookEntries;
        $this->markbookWeights = $markbookWeights;
    }

    public function getCharts()
    {
        return [
            'progression' => $this->getProgressionChart(),
            'frequency' => $this->getFrequencyCharts(),
        ];
    }

    public function getProgressionChart()
    {
        $tooltipAssignment = "function(tooltipItem, data) {
            var list = this._chartInstance.config.metadata[tooltipItem.xLabel];
            return list ;
        }";

        foreach ($this->markbookEntries as $markbookType => $entries) {
            foreach ($entries as $entry) {
                $date = $entry['completeDate'] ?? $entry['date'];
                $tooltip = [$entry['name'], ''];

                if ($entry['attainmentValue']) {
                    $tooltip[] = __('Attainment').': '.$entry['attainmentValue'];
                }
                if ($entry['effortValue']) {
                    $tooltip[] = __('Effort').': '.$entry['effortValue'];
                }

                $lineData[$entry['completeDate']] = $entry['attainmentValue'];
                $metaData[$entry['completeDate']] = $tooltip;
            }
        }

        ksort($lineData);

        $lineGraph = Chart::create('progression', 'line')
            ->setTitle(__('Progression'))
            ->setOptions([
                'legend' => false,
                'height' => '70',
                'scales' => [
                    'yAxes' => [[
                        'ticks'     => ['stepSize' => 1, 'min' => 1, 'max' => 7],
                        'gridLines' => ['display' => false]
                    ]],
                    'xAxes' => [[
                        'display'   => true,
                        'type'      => 'time',
                        'gridLines' => ['display' => false]
                    ]],
                ],
            ])
            ->setLabels($lineData)
            ->onTooltip($tooltipAssignment)
            ->setMetaData($metaData);

        $lineGraph->setColors([$lineGraph->getColor(7)]);

        $lineGraph->addDataset('progression')
            ->setLabel(__('Attainment'))
            ->setProperties(['fill' => false, 'borderWidth' => 1])
            ->setData($lineData);

        return $lineGraph;
    }

    public function getFrequencyCharts()
    {
        $tooltipLabel = "function(tooltipItem, data) {
            var index = tooltipItem.datasetIndex;
            var list = this._chartInstance.config.metadata[index][tooltipItem.xLabel];
            return list ;
        }";
    
        $tooltipTitle = "function(tooltipItem) {
            return tooltipItem[0].yLabel + ' item(s):';
        }";
    
        $chartConfig = [
            'legend'             => false,
            'height'             => '90',
            'scales'             => [
                'yAxes' => [[
                    'stacked' => true,
                    'ticks' => ['display' => true, 'labelOffset' => 0, 'padding' => 0, 'stepSize' => 1, 'suggestedMax' => 4, 'fontColor' => 'transparent'],
                    'gridLines' => ['drawTicks' => true, 'tickMarkLength' => 0],
                ]],
                'xAxes' => [[
                    'stacked' => true,
                ]],
            ],
        ];

        $count = 0;
        $termTotal = $termWeight = 0;
        $finalTotal = $finalWeight = 0;

        $charts = [];

        if (empty($this->markbookWeights)) {
            $barGraph = Chart::create('assessment', 'bar')
                ->onTooltip($tooltipLabel, $tooltipTitle)
                ->setOptions($chartConfig)
                ->setLabels([1,2,3,4,5,6,7])
                ->setLegend(['display' => true, 'position' => 'right']);

            $metaData = [];

            foreach ($this->markbookEntries as $markbookType => $entries) {
                $chartData = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0];

                foreach ($entries as $entry) {
                    if (!is_numeric($entry['attainmentValue'])) {
                        continue;
                    }

                    $chartData[$entry['attainmentValue']]++;
                    $metaData[$count][$entry['attainmentValue']][] = $entry['name'];
                }

                $total = array_sum(array_column($entries, 'attainmentValue'));
                $average = $entries ? number_format(round($total / count($entries), 1), 1) : 0;
                $weight = count($entries);

                $calculate = current($entries)['calculate'] ?? 'term';
                if ($calculate == 'year') {
                    $finalTotal += $total;
                    $finalWeight += $weight;
                } else {
                    $termTotal += $total;
                    $termWeight += $weight;
                }

                $barGraph->addDataset('assignments'.$count, $markbookType)->setData($chartData);
                $count++;
            }

            $barGraph->setTitle(__('Attainment').' - '.__('Average').' '.$average);
            $barGraph->setMetaData($metaData);
            
            $charts[] = $barGraph;
        } else {
            foreach ($this->markbookEntries as $markbookType => $entries) {
                $chartData = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0];
                $metaData = [];

                $entries = array_filter($entries, function ($item) {
                    return !empty($item['attainmentValue']) && intval($item['gibbonScaleIDAttainment']) == 1;
                });

                foreach ($entries as $entry) {
                    if (!is_numeric($entry['attainmentValue'])) {
                        continue;
                    }

                    $chartData[$entry['attainmentValue']]++;
                    $metaData[0][$entry['attainmentValue']][] = $entry['description'];
                }

                $total = array_sum(array_column($entries, 'attainmentValue'));
                $average = $entries ? number_format(round($total / count($entries), 1), 1) : 0;

                if ($this->markbookWeights[$markbookType]['weighting'] === null) {
                    $weight = count($entries);
                } else {
                    $weight = floatval($this->markbookWeights[$markbookType]['weighting']);
                }
                

                $calculate = current($entries)['calculate'] ?? 'term';
                if ($calculate == 'year') {
                    $finalTotal += ($average * $weight);
                    $finalWeight += $weight;
                } else {
                    $termTotal += ($average * $weight);
                    $termWeight += $weight;
                }

                $title = $this->markbookWeights[$markbookType]['description'] ?? $markbookType;
                if ($this->markbookWeights[$markbookType]['weighting'] !== null) {
                    $title .= ' - '.floatval($this->markbookWeights[$markbookType]['weighting']).'%';
                }
                $title .= ' - '.__('Average').' '.$average;

                $barGraph = Chart::create('assessment'.$count, 'bar')
                    ->setTitle($title)
                    ->onTooltip($tooltipLabel, $tooltipTitle)
                    ->setOptions($chartConfig)
                    ->setMetaData($metaData)
                    ->setLabels($chartData);
            
                $barGraph->setColors([$barGraph->getColor($count)]);

                $barGraph->addDataset('assignments')->setData($chartData);

                $charts[] = $barGraph;
                $count++;
            }
        }

        $termAverage = $termWeight ? number_format(round($termTotal / $termWeight, 1), 1) : $termTotal;

        $overallWeight = min(100.0, max(0.0, 100.0 - $finalWeight));
        $overallTotal = ($termAverage * $overallWeight);

        $finalAverage = $finalWeight ? number_format(round($finalTotal / $finalWeight, 1), 1) : $finalTotal;

        $overallWeight += $finalWeight;
        $overallTotal += ($finalAverage * $finalWeight);

        $this->gradeAverage = $overallWeight ? number_format(round($overallTotal / $overallWeight, 1), 1) : $overallTotal;

        return $charts;
    }

    public function getGradeAverage()
    {
        return $this->gradeAverage;
    }
}
