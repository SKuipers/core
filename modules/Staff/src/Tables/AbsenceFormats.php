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

/**
 * @version v18
 * @since   v18
 */
class AbsenceFormats
{
    public static function dateDetails($absence)
    {
        $output = Format::dateRangeReadable($absence['dateStart'], $absence['dateEnd']);
        if ($absence['allDay'] == 'Y' || $absence['days'] > 1) {
            $output .= '<br/>'.Format::small(__n('{count} Day', '{count} Days', $absence['days']));
        } else {
            $output .= '<br/>'.Format::small(Format::timeRange($absence['timeStart'], $absence['timeEnd']));
        }
        
        return Format::tooltip($output, $absence['value']);
    }

    public static function timeDetails($absence)
    {
        if ($absence['allDay'] == 'N') {
            return Format::small(Format::timeRange($absence['timeStart'], $absence['timeEnd']));
        } else {
            return Format::small(__('All Day'));
        }
    }

    public static function typeAndReason($absence)
    {
        $output = $absence['type'];
        if (!empty($absence['reason'])) {
            $output .= '<br/>'.Format::small($absence['reason']);
        }
        if ($absence['status'] != 'Approved') {
            $output .= '<br/><span class="small emphasis">'.__($absence['status']).'</span>';
        }
        return $output;
    }

    public static function coverage($absence) {
        if ($absence['coverage'] == 'Accepted') {
            return Format::name($absence['titleCoverage'], $absence['preferredNameCoverage'], $absence['surnameCoverage'], 'Staff', false, true);
        } elseif ($absence['coverage'] == 'Requested') {
            return '<div class="badge success">'.__('Pending').'</div>';
        }
        return '';
    }

    public static function coverageList($absence)
    {
        if (empty($absence['coverage']) || empty($absence['coverageList'])) {
            return '';
        }

        $names = array_unique(array_map([self, 'coverage'], $absence['coverageList'] ?? []));

        return implode('<br/>', $names);
    }

    public static function createdOn($absence)
    {
        $output = Format::relativeTime($absence['timestampCreator'], 'M j, Y H:i');
        if ($absence['gibbonPersonID'] != $absence['gibbonPersonIDCreator']) {
            $output .= '<br/>'.Format::small(__('By').' '.Format::name('', $absence['preferredNameCreator'], $absence['surnameCreator'], 'Staff', false, true));
        }
        return $output;
    }
}
