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
    //Acess denied
    echo "<div class='error'>";
    echo __('You do not have access to this action.');
    echo '</div>';
} else {
    //Proceed!
    $page->breadcrumbs->add(__('Manage Staff Coverage'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    $gibbonSchoolYearID = $_SESSION[$guid]['gibbonSchoolYearID'];
    $search = $_GET['search'] ?? '';

    $StaffCoverageGateway = $container->get(StaffCoverageGateway::class);

    
    $form = Form::create('filter', $_SESSION[$guid]['absoluteURL'].'/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/Staff/coverage_manage.php');

    $row = $form->addRow();
        $row->addLabel('search', __('Search'));
        $row->addTextField('search')->setValue($search);

    // $row = $form->addRow();
    //     $row->addLabel('dateStart', __('Start Date'));
    //     $row->addDate('dateStart')->setValue($dateStart);

    // $row = $form->addRow();
    //     $row->addLabel('dateEnd', __('End Date'));
    //     $row->addDate('dateEnd')->setValue($dateEnd);

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

    $absences = $StaffCoverageGateway->queryCoverageBySchoolYear($criteria, $gibbonSchoolYearID, true);

    // Join a set of coverage data per absence
    // $absenceIDs = $absences->getColumn('gibbonStaffAbsenceID');
    // $coverageData = $staffAbsenceDateGateway->selectDatesByAbsence($absenceIDs)->fetchGrouped();
    // $absences->joinColumn('gibbonStaffAbsenceID', 'coverageList', $coverageData);

    // DATA TABLE
    $table = DataTable::createPaginated('staffAbsences', $criteria);
    $table->setTitle(__('View'));

    // if (isActionAccessible($guid, $connection2, '/modules/Staff/report_absences_summary.php')) {
    //     $table->addHeaderAction('view', __('View'))
    //         ->setIcon('planner')
    //         ->setURL('/modules/Staff/report_absences_summary.php')
    //         ->displayLabel()
    //         ->append('&nbsp;|&nbsp;');
    // }

    // $table->addHeaderAction('add', __('New Absence'))
    //     ->setURL('/modules/Staff/absences_manage_add.php')
    //     ->addParam('gibbonPersonID', '')
    //     ->addParam('date', $dateStart)
    //     ->displayLabel();
    
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
    // $table->addColumn('fullName', __('Name'))
    //     ->sortable(['surname', 'preferredName'])
    //     ->format(function ($absence) use ($guid) {
    //         $text = Format::name($absence['title'], $absence['preferredName'], $absence['surname'], 'Staff', false, true);
    //         $url = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/absences_view_byPerson.php&gibbonPersonID='.$absence['gibbonPersonID'];

    //         return Format::link($url, $text);
    //     });
    $table->addColumn('requested', __('Name'))
        ->sortable(['surnameAbsence', 'preferredNameAbsence'])
        ->format(function ($coverage) {
            return Format::name($coverage['titleAbsence'], $coverage['preferredNameAbsence'], $coverage['surnameAbsence'], 'Staff', false, true).'<br/>'.
                Format::small($coverage['jobTitleAbsence']);
        });

    $table->addColumn('date', __('Date'))
        ->width('18%')
        ->format(function ($absence) {
            $output = Format::dateRangeReadable($absence['dateStart'], $absence['dateEnd']);
            if ($absence['days'] > 1) {
                $output .= '<br/>'.Format::small(__n('{count} Day', '{count} Days', $absence['days']));
            } elseif ($absence['allDay'] == 'N') {
                $output .= '<br/>'.Format::small(Format::timeRange($absence['timeStart'], $absence['timeEnd']));
            }
            
            return $output;
        });

    $table->addColumn('type', __('Type'))
        ->description(__('Reason'))
        ->format(function ($absence) {
            return $absence['type'] .'<br/>'.Format::small($absence['reason']);
        });


    $table->addColumn('coverage', __('Substitute'))
        ->sortable(['surnameCoverage', 'preferredNameCoverage'])
        ->format(function ($coverage) {
            return $coverage['gibbonPersonIDCoverage'] 
                ? Format::name($coverage['titleCoverage'], $coverage['preferredNameCoverage'], $coverage['surnameCoverage'], 'Staff', false, true)
                : '<div class="badge success">'.__('Pending').'</div>';
        });


    // $table->addColumn('timestampRequested', __('Requested'))
    //     ->format(function ($absence) {
    //         if (empty($absence['timestampRequested'])) return;
    //         return Format::relativeTime($absence['timestampRequested'], 'M j, Y H:i');
    //     });

    $table->addColumn('status', __('Status'));

    // ACTIONS
    $table->addActionColumn()
        ->addParam('gibbonStaffCoverageID')
        ->format(function ($person, $actions) {
            // $actions->addAction('accept', __('Accept'))
            //     ->setIcon('iconTick')
            //     ->setURL('/modules/Staff/coverage_view_accept.php');

            // $actions->addAction('decline', __('Decline'))
            //     ->setIcon('iconCross')
            //     ->setURL('/modules/Staff/coverage_view_decline.php')
            //     ->append('<br/>');

            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/Staff/coverage_manage_edit.php');

            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/Staff/coverage_manage_delete.php');
        });

    echo $table->render($absences);
}
