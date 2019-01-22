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
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\Staff\StaffCoverageGateway;

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_manage.php') == false) {
    // Access denied
    echo "<div class='error'>";
    echo __('You do not have access to this action.');
    echo '</div>';
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage Staff Coverage'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    $gibbonSchoolYearID = $_SESSION[$guid]['gibbonSchoolYearID'];
    $search = $_GET['search'] ?? '';

    $urgencyThreshold = getSettingByScope($connection2, 'Staff', 'urgencyThreshold');
    $StaffCoverageGateway = $container->get(StaffCoverageGateway::class);

    
    $form = Form::create('filter', $_SESSION[$guid]['absoluteURL'].'/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/Staff/coverage_manage.php');

    $row = $form->addRow();
        $row->addLabel('search', __('Search'));
        $row->addTextField('search')->setValue($search);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($gibbon->session);

    echo $form->getOutput();


    // QUERY
    $criteria = $StaffCoverageGateway->newQueryCriteria()
        ->searchBy($StaffCoverageGateway->getSearchableColumns(), $search)
        ->sortBy('date', 'ASC');

    $criteria->filterBy('date', !$criteria->hasFilter() && !$criteria->hasSearchText() ? 'upcoming' : '')
        ->fromPOST();

    $coverage = $StaffCoverageGateway->queryCoverageBySchoolYear($criteria, $gibbonSchoolYearID, true);

    // DATA TABLE
    $table = DataTable::createPaginated('staffCoverage', $criteria);
    $table->setTitle(__('View'));

    $table->modifyRows(function ($coverage, $row) {
        if ($coverage['status'] == 'Accepted') $row->addClass('current');
        if ($coverage['status'] == 'Declined') $row->addClass('error');
        if ($coverage['status'] == 'Cancelled') $row->addClass('dull');
        return $row;
    });

    $table->addMetaData('filterOptions', [
        'date:upcoming'    => __('Upcoming'),
        'date:today'       => __('Today'),
        'date:past'        => __('Past'),
        'status:requested' => __('Coverage').': '.__('Requested'),
        'status:accepted'  => __('Coverage').': '.__('Accepted'),
        'status:declined'  => __('Coverage').': '.__('Declined'),
        'status:cancelled' => __('Coverage').': '.__('Cancelled'),
    ]);

    // COLUMNS
    $table->addColumn('requested', __('Name'))
        ->sortable(['surnameAbsence', 'preferredNameAbsence'])
        ->format(function ($coverage) {
            return Format::name($coverage['titleAbsence'], $coverage['preferredNameAbsence'], $coverage['surnameAbsence'], 'Staff', false, true).'<br/>'.
                Format::small($coverage['type'].' '.$coverage['reason']);
        });

    $table->addColumn('date', __('Date'))
        ->width('18%')
        ->format(function ($coverage) {
            $output = Format::dateRangeReadable($coverage['dateStart'], $coverage['dateEnd']);
            if ($coverage['days'] > 1) {
                $output .= '<br/>'.Format::small(__n('{count} Day', '{count} Days', $coverage['days']));
            } elseif ($coverage['allDay'] == 'N') {
                $output .= '<br/>'.Format::small(Format::timeRange($coverage['timeStart'], $coverage['timeEnd']));
            }
            return $output;
        });

    $table->addColumn('coverage', __('Substitute'))
        ->sortable(['surnameCoverage', 'preferredNameCoverage'])
        ->format(function ($coverage) {
            return $coverage['gibbonPersonIDCoverage'] 
                ? Format::name($coverage['titleCoverage'], $coverage['preferredNameCoverage'], $coverage['surnameCoverage'], 'Staff', false, true)
                : '<div class="badge success">'.__('Pending').'</div>';
        });

    $table->addColumn('status', __('Status'))
        ->width('15%')
        ->format(function ($coverage) use ($urgencyThreshold) {
            $relativeSeconds = strtotime($coverage['dateStart']) - time();
            if ($coverage['status'] != 'Requested') {
                return $coverage['status'];
            }
            if ($relativeSeconds <= 0) {
                return '<div class="badge dull">'.__('Overdue').'</div>';
            } elseif ($relativeSeconds <= (86400 * $urgencyThreshold)) {
                return '<div class="error badge">'.__('Urgent').'</div>';
            } elseif ($relativeSeconds <= (86400 * ($urgencyThreshold * 3))) {
                return '<div class="badge warning">'.__('Upcoming').'</div>';
            } else {
                return __('Upcoming');
            }
        });

    $table->addColumn('timestampRequested', __('Requested'))
        ->format(function ($coverage) {
            if (empty($coverage['timestampRequested'])) return;
            return Format::relativeTime($coverage['timestampRequested'], 'M j, Y H:i');
        });

    // ACTIONS
    $table->addActionColumn()
        ->addParam('gibbonStaffCoverageID')
        ->format(function ($person, $actions) {
            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/Staff/coverage_manage_edit.php');

            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/Staff/coverage_manage_delete.php');
        });

    echo $table->render($coverage);
}
