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

use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\Activities\ActivityGateway;

//Module includes
include './modules/'.$_SESSION[$guid]['module'].'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Activities/activities_my.php') == false) {
    //Acess denied
    echo "<div class='error'>";
    echo __($guid, 'You do not have access to this action.');
    echo '</div>';
} else {
    echo "<div class='trail'>";
    echo "<div class='trailHead'><a href='".$_SESSION[$guid]['absoluteURL']."'>".__($guid, 'Home')."</a> > <a href='".$_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/'.getModuleName($_GET['q']).'/'.getModuleEntry($_GET['q'], $connection2, $guid)."'>".__($guid, getModuleName($_GET['q']))."</a> > </div><div class='trailEnd'>".__($guid, 'My Activities').'</div>';
    echo '</div>';

    $highestAction = getHighestGroupedAction($guid, '/modules/Activities/activities_attendance.php', $connection2);
    $canAccessEnrolment = isActionAccessible($guid, $connection2, '/modules/Activities/activities_manage_enrolment.php');

    $activityGateway = $container->get(ActivityGateway::class);
    
    // CRITERIA
    $criteria = $activityGateway->newQueryCriteria()
        ->sortBy('name')
        ->fromArray($_POST);

    $activities = $activityGateway->queryActivitiesByParticipant($criteria, $_SESSION[$guid]['gibbonSchoolYearID'], $_SESSION[$guid]['gibbonPersonID']);

    // DATA TABLE
    $table = DataTable::createPaginated('myActivities', $criteria);

    $table->addColumn('name', __('Activity'))
        ->format(function($activity) {
            return $activity['name'].'<br/><span class="small emphasis">'.$activity['type'].'</span>';
        });
    $table->addColumn('role', __('Role'))->format(function($activity){
        return !empty($activity['role']) ? $activity['role'] : __('Student');
    });
    $table->addColumn('status', __('Status'))->format(function($activity){
        return !empty($activity['status']) ? $activity['status'] : '<i>'.__('N/A').'</i>';
    });

    $table->addActionColumn()
        ->addParam('gibbonActivityID')
        ->format(function ($activity, $actions) use ($highestAction, $canAccessEnrolment) {
            if ($activity['role'] == 'Organiser' &&  $canAccessEnrolment) {
                $actions->addAction('enrolment', __('Enrolment'))
                    ->addParam('gibbonSchoolYearTermID', '')
                    ->addParam('search', '')
                    ->setIcon('config')
                    ->setURL('/modules/Activities/activities_manage_enrolment.php');
            }

            $actions->addAction('view', __('View Details'))
                ->isModal(1000, 550)
                ->setURL('/modules/Activities/activities_my_full.php');

            if ($highestAction == "Enter Activity Attendance" || ($highestAction == "Enter Activity Attendance_leader" && ($activity['role'] == 'Organiser' || $activity['role'] == 'Assistant' || $activity['role'] == 'Coach'))) {
                $actions->addAction('attendance', __('Attendance'))
                    ->setIcon('attendance')
                    ->setURL('/modules/Activities/activities_attendance.php');
            }
        });

    echo $table->render($activities);
}
