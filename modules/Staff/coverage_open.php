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

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_open.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Open Requests'));

    $gibbonPersonID = $_SESSION[$guid]['gibbonPersonID'];
    $gibbonSchoolYearID = $_SESSION[$guid]['gibbonSchoolYearID'];
    
    $schoolYearGateway = $container->get(SchoolYearGateway::class);
    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);

    // QUERY
    $criteria = $staffCoverageGateway->newQueryCriteria()
        ->sortBy('date', 'ASC')
        ->fromPOST('staffCoverageAvailable');

    $coverage = $staffCoverageGateway->queryCoverageWithNoPersonAssigned($criteria, $gibbonPersonID);

    if ($coverage->getResultCount() == 0) {
        echo Format::alert(__('All coverage requests have been filled!'), 'success');
    } else {
        // DATA TABLE
        $table = DataTable::createPaginated('staffCoverageAvailable', $criteria);
        $table->setTitle(__('Available Coverage Requests'));

        $table->modifyRows(function ($coverage, $row) {
            if ($coverage['status'] == 'Accepted') $row->addClass('current');
            return $row;
        });

        $table->addColumn('status', __('Status'))
            ->width('15%')
            ->format(function ($coverage) {
                $relativeSeconds = strtotime($coverage['dateStart']) - time();
                if ($relativeSeconds <= (86400 * 3)) { // Less than three days
                    return '<span style="color: #CC0000; font-weight: bold; border: 2px solid #CC0000; padding: 2px 4px; background-color: #F6CECB;margin:0 auto;">'.__('Urgent').'</span>';
                } elseif ($relativeSeconds <= (86400 * 7)) { // Less than three days
                    return '<span style="color: #FF7414; font-weight: bold; border: 2px solid #FF7414; padding: 2px 4px; background-color: #FFD2A9;margin:0 auto;">'.__('Upcoming').'</span>';
                } else {
                    return Format::small(__('Available'));
                }
            });

        $table->addColumn('requested', __('Person'))
            ->width('30%')
            ->sortable(['surname', 'preferredName'])
            ->format(function ($coverage) {
                return Format::name($coverage['titleAbsence'], $coverage['preferredNameAbsence'], $coverage['surnameAbsence'], 'Staff', false, true);
            });

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

        $table->addActionColumn()
            ->addParam('gibbonStaffCoverageID')
            ->format(function ($coverage, $actions) {
                $actions->addAction('accept', __('Accept'))
                    ->setIcon('page_right')
                    ->setURL('/modules/Staff/coverage_accept.php');
            });

        echo $table->render($coverage);
    }
}
