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

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_view.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Open Requests'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    $urgencyThreshold = getSettingByScope($connection2, 'Staff', 'urgencyThreshold');

    $gibbonPersonID = $_SESSION[$guid]['gibbonPersonID'];
    $gibbonSchoolYearID = $_SESSION[$guid]['gibbonSchoolYearID'];
    
    $schoolYearGateway = $container->get(SchoolYearGateway::class);
    $staffCoverageGateway = $container->get(StaffCoverageGateway::class);

    // QUERY
    $criteria = $staffCoverageGateway->newQueryCriteria()
        ->sortBy('date', 'ASC')
        ->filterBy('requested', 'Y')
        ->filterBy('date:upcoming')
        ->pageSize(0)
        ->fromPOST('myCoverage');

    $myCoverage = $staffCoverageGateway->queryCoverageByPersonCovering($criteria, $gibbonPersonID, true);

    $criteria = $staffCoverageGateway->newQueryCriteria()
        ->sortBy('date', 'ASC')
        ->filterBy('requested', 'Y')
        ->filterBy('date:upcoming')
        ->pageSize(0)
        ->fromPOST('allCoverage');

    $allCoverage = $staffCoverageGateway->queryCoverageWithNoPersonAssigned($criteria, true);

    if ($myCoverage->getResultCount() == 0 && $allCoverage->getResultCount() == 0) {
        echo Format::alert(__('All coverage requests have been filled!'), 'success');
        return;
    }

    // DATA TABLE
    $table = DataTable::createPaginated('staffCoverageAvailable', $criteria);

    $table->addMetaData('hidePagination', true);
    
    $table->modifyRows(function ($coverage, $row) {
        if ($coverage['status'] == 'Accepted') $row->addClass('current');
        if ($coverage['status'] == 'Declined') $row->addClass('error');
        if ($coverage['status'] == 'Cancelled') $row->addClass('dull');
        return $row;
    });

    $table->addColumn('status', __('Status'))
        ->width('15%')
        ->format(function ($coverage) use ($urgencyThreshold) {
            $relativeSeconds = strtotime($coverage['dateStart']) - time();
            if ($relativeSeconds <= (86400 * $urgencyThreshold)) {
                return '<div class="error badge">'.__('Urgent').'</div>';
            } elseif ($relativeSeconds <= (86400 * ($urgencyThreshold * 3))) {
                return '<div class="badge warning">'.__('Upcoming').'</div>';
            } else {
                return __('Upcoming');
            }
        });

    $table->addColumn('requested', __('Person'))
        ->width('30%')
        ->sortable(['surname', 'preferredName'])
        ->format(function ($coverage) {
            return Format::name($coverage['titleAbsence'], $coverage['preferredNameAbsence'], $coverage['surnameAbsence'], 'Staff', false, true).'<br/>'.
                   Format::small($coverage['jobTitleAbsence']);
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
        ->format(function ($coverage, $actions) use ($gibbonPersonID) {
            $actions->addAction('accept', __('Accept'))
                ->setIcon('iconTick')
                ->setURL('/modules/Staff/coverage_view_accept.php');

            if ($gibbonPersonID == $coverage['gibbonPersonIDCoverage']) {
                $actions->addAction('decline', __('Decline'))
                    ->setIcon('iconCross')
                    ->setURL('/modules/Staff/coverage_view_decline.php');
            }
        });

    if ($myCoverage->getResultCount() > 0) {
        $myRequestsTable = clone $table;
        $myRequestsTable->setID('myCoverage');
        $myRequestsTable->setTitle(__('Personal Coverage Requests'));
        $myRequestsTable->setDescription(Format::alert(__('These requests have been submitted to you personally. If you are unable to accept the request, please decline it so that the requesting staff member is notified and can find a different substitute.'), 'message'));

        echo $myRequestsTable->render($myCoverage);
    }

    if ($allCoverage->getResultCount() > 0) {
        $allRequestsTable = clone $table;
        $allRequestsTable->setID('allCoverage');
        $allRequestsTable->setTitle(__('All Coverage Requests'));
        $allRequestsTable->setDescription(__('These requests are open for any available substitute to accept. '));

        echo $allRequestsTable->render($allCoverage);
    }
}
