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

namespace Gibbon\Module\Reports\Forms;

use Gibbon\Forms\Layout\Column;
use Gibbon\Forms\FormFactoryInterface;

/**
 * GradesSlider
 *
 * @version v19
 * @since   v19
 */
class GradesSlider extends Column
{
    public function __construct(FormFactoryInterface $factory, $id, $termGrade, $termPercent, $readonly = false, $gradeAverage = null)
    {
        parent::__construct($factory, $id);

        $row = $this->addColumn()->addClass('text-center py-4');

        $col = $row->addColumn()->addClass('float-left text-center w-1/2');
            $col->addLabel('graph', 'Term Grade');
            $col->addNumber("value[{$termGrade['gibbonReportingCriteriaID']}]")
                ->setID("value{$termGrade['gibbonReportingCriteriaID']}")
                ->setClass('termGrade1to7 reportCriteria w-12 text-center mx-auto')
                ->setValue($termGrade['value'])
                ->onlyInteger(true)
                ->minimum(1)
                ->maximum(7)
                ->maxLength(1)
                ->readonly($readonly);
            $col->addRange('termGrade1to7Slider', 1, 7)
                ->setClass('termGrade1to7Slider reportCriteria mediumWidth mx-auto pt-2')
                ->setValue($termGrade['value'])
                ->setDisabled($readonly);

            if ($gradeAverage) {
                $col->addContent(__('Average').': '.$gradeAverage)->addClass('gradeAverage');
            }

        $col = $row->addColumn()->addClass('float-left text-center w-1/2');
            $col->addLabel('percent', 'Term Percent');
            $col->addTextField("value[{$termPercent['gibbonReportingCriteriaID']}]")
                ->setID("value{$termPercent['gibbonReportingCriteriaID']}")
                ->setClass('termGradePercent reportCriteria w-12 text-center mx-auto')
                ->setValue($termPercent['value'])
                ->setDisabled(empty($termPercent['value']))
                ->readonly($readonly);
            $col->addRange('termGradeSlider', 0, 100)
                ->setClass('termGradeSlider reportCriteria mediumWidth mx-auto pt-2')
                ->setValue(intval($termPercent['value']))
                ->setDisabled($readonly || empty($termPercent['value']));
            $col2 = $col->addColumn()->addClass('inline mediumWidth mx-auto');
                $col2->addContent('')->addClass('termGradeMin gradeAverage w-1/2 text-left');
                $col2->addContent('')->addClass('termGradeMax gradeAverage w-1/2 text-right right');
    }

    public function getOutput()
    {
        $output = parent::getOutput();

        $output .= "
        <script type='text/javascript'>
        $('#".$this->getID()."').each(function () {

            var context = this;

            $('input.termGradeSlider', context).on('input', function() {
                $('.termGradeMin', context).html($(this).attr('min')+'%');
                $('.termGradeMax', context).html($(this).attr('max')+'%');
        
                $('input.termGradePercent', context).val($(this).val()+'%');
            });
        
            $('input.termGrade1to7Slider', context).on('input', function() {
                $('input.termGrade1to7', context).val($(this).val());
                $('input.termGrade1to7', context).trigger('input');
            });
        
            $('input.termGrade1to7, input.termGradePercent', context).click(function() { 
                $(this).select(); 
            });
        
            $('input.termGradePercent', context).on('change', function() {
                if ($(this).val() == '') return;
                
                var min = $('input.termGradeSlider', context).attr('min');
                var max = $('input.termGradeSlider', context).attr('max');
                var value = Math.min(max, Math.max(min, parseInt($(this).val())) );
        
                if (value == '' || value == 0) return;
        
                $('input.termGradeSlider', context).val(value);
                $('input.termGradeSlider', context).trigger('input');
            });
        
            $('input.termGrade1to7', context).on('input', function() {
                if ($(this).val() == '') return;
                
                var value = Math.min(7, Math.max(1, $(this).val()));
                $(this).val(value);
        
                var min, max;
        
                if (value == '' || value == 0) return;
        
                switch (value) {
                    case 7: min = 95; max = 100; break;
                    case 6: min = 90; max = 94; break;
                    case 5: min = 80; max = 89; break;
                    case 4: min = 70; max = 79; break;
                    case 3: min = 60; max = 69; break;
                    case 2: min = 50; max = 59; break;
                    case 1: min = 0;  max = 50; break;
                }
        
                $('input.termGradePercent', context).attr('disabled', false);
                $('input.termGradeSlider', context).attr('disabled', false);
                $('input.termGradeSlider', context).attr('min', min);
                $('input.termGradeSlider', context).attr('max', max);
        
                $('input.termGradeSlider', context).trigger('input');
                $('input.termGrade1to7Slider', context).val($(this).val());
                
            });
        
            $('input.termGrade1to7', context).trigger('input');
        });
        </script>
        ";

        return $output;
    }


}
