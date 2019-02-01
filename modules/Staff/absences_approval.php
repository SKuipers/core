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
use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Domain\Staff\StaffAbsenceTypeGateway;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;

if (isActionAccessible($guid, $connection2, '/modules/Staff/absences_approval.php') == false) {
    //Acess denied
    echo "<div class='error'>";
    echo __('You do not have access to this action.');
    echo '</div>';
} else {
    //Proceed!
    $page->breadcrumbs->add(__('Approve Staff Absences'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    $gibbonSchoolYearID = $_SESSION[$guid]['gibbonSchoolYearID'];
    $gibbonStaffAbsenceTypeID = $_GET['gibbonStaffAbsenceTypeID'] ?? '';
    $search = $_GET['search'] ?? '';
    $dateStart = $_GET['dateStart'] ?? '';
    $dateEnd = $_GET['dateEnd'] ?? '';

    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffAbsenceTypeGateway = $container->get(StaffAbsenceTypeGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);

    // QUERY
    $criteria = $staffAbsenceGateway->newQueryCriteria()
        ->searchBy($staffAbsenceGateway->getSearchableColumns(), $search)
        ->sortBy('status', 'ASC');

    // $criteria->filterBy('status', !$criteria->hasFilter() && !$criteria->hasSearchText() ? 'pending approval' : '')
    //     ->fromPOST();

    $absences = $staffAbsenceGateway->queryAbsencesByApprover($criteria, $_SESSION[$guid]['gibbonPersonID']);

    // DATA TABLE
    $table = DataTable::createPaginated('staffAbsences', $criteria);
    $table->setTitle(__('View'));

    $table->modifyRows(function ($absence, $row) {
        if ($absence['status'] == 'Approved') $row->addClass('current');
        if ($absence['status'] == 'Declined') $row->addClass('error');
        return $row;
    });
    
    $table->addMetaData('filterOptions', [
        'date:upcoming'    => __('Upcoming'),
        'date:today'       => __('Today'),
        'date:past'        => __('Past'),
        'status:pending approval' => __('Status').': '.__('Pending Approval'),
        'status:approved'         => __('Status').': '.__('Approved'),
        'status:declined'         => __('Status').': '.__('Declined'),
    ]);

    // COLUMNS
    $table->addColumn('fullName', __('Name'))
        ->sortable(['surname', 'preferredName'])
        ->format(function ($absence) use ($guid) {
            $text = Format::name($absence['title'], $absence['preferredName'], $absence['surname'], 'Staff', false, true);
            $url = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Staff/absences_view_byPerson.php&gibbonPersonID='.$absence['gibbonPersonID'];

            return Format::link($url, $text);
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
            $output = $absence['type'];
            if (!empty($absence['reason'])) {
                $output .= '<br/>'.Format::small($absence['reason']);
            }
            $output .= '<br/><span class="small emphasis">'.__($absence['status']).'</span>';
            
            return $output;
        });

    $table->addColumn('timestampCreator', __('Created'))
        ->format(function ($absence) {
            $output = Format::relativeTime($absence['timestampCreator'], 'M j, Y H:i');
            if ($absence['gibbonPersonID'] != $absence['gibbonPersonIDCreator']) {
                $output .= '<br/>'.Format::small(__('By').' '.Format::name('', $absence['preferredNameCreator'], $absence['surnameCreator'], 'Staff', false, true));
            }
            return $output;
        });

    // ACTIONS
    $table->addActionColumn()
        ->addParam('gibbonStaffAbsenceID')
        ->format(function ($absence, $actions) use ($guid) {
            $actions->addAction('view', __('View Details'))
                ->isModal(800, 550)
                ->setURL('/modules/Staff/absences_view_details.php');

            if ($absence['status'] == 'Pending Approval') {
                $actions->addAction('approve', __('Approve'))
                    ->setIcon('iconTick')
                    ->addParam('action', 'Approve')
                    ->setURL('/modules/Staff/absences_approval_action.php');

                $actions->addAction('decline', __('Decline'))
                    ->setIcon('iconCross')
                    ->addParam('action', 'Decline')
                    ->setURL('/modules/Staff/absences_approval_action.php');
            }
        });

    echo $table->render($absences);
}
