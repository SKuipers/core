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

namespace Gibbon\Module\Staff\Forms;

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\Staff\StaffCoverageGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Psr\Container\ContainerInterface;

/**
 * ViewCoverageForm
 *
 * @version v18
 * @since   v18
 */
class ViewCoverageForm
{
    public static function create(ContainerInterface $container, $gibbonStaffCoverageID)
    {
        $guid = $container->get('config')->getConfig('guid');
        $pdo = $container->get('db');
        $connection2 = $pdo->getConnection();

        $staffCoverageGateway = $container->get(StaffCoverageGateway::class);
        $coverage = $staffCoverageGateway->getCoverageDetailsByID($gibbonStaffCoverageID);

        $form = Form::create('staffCoverage', '');

        $form->setFactory(DatabaseFormFactory::create($pdo));
    
        $form->addRow()->addHeading(__('Staff Absence'));
    
        $row = $form->addRow();
            $row->addLabel('gibbonPersonIDLabel', __('Person'));
            $row->addSelectStaff('gibbonPersonID')->placeholder()->isRequired()->selected($coverage['gibbonPersonID'])->readonly();
    
        $row = $form->addRow();
            $row->addLabel('typeLabel', __('Type'));
            $row->addTextField('type')->readonly()->setValue($coverage['reason'] ? "{$coverage['type']} ({$coverage['reason']})" : $coverage['type']);
    
        $row = $form->addRow();
            $row->addLabel('timestamp', __('Requested'));
            $row->addTextField('timestampValue')
                ->readonly()
                ->setValue(Format::relativeTime($coverage['timestampStatus'], false))
                ->setTitle($coverage['timestampStatus']);
    
        $row = $form->addRow();
            $row->addLabel('notesStatusLabel', __('Comment'));
            $row->addTextArea('notesStatus')->setRows(3)->setValue($coverage['notesStatus'])->readonly();
        
        
        $form->addRow()->addHeading(__('Substitute'));
    
        if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_manage.php')) {
    
            $row = $form->addRow();
                $row->addLabel('requestTypeLabel', __('Type'));
                $row->addTextField('requestType')->readonly()->setValue($coverage['requestType']);
    
            $row = $form->addRow();
                $row->addLabel('statusLabel', __('Status'));
                $row->addTextField('status')->readonly()->setValue($coverage['status']);
    
            if ($coverage['requestType'] == 'Individual') {
                $row = $form->addRow();
                $row->addLabel('gibbonPersonIDLabel', __('Person'));
                $row->addSelectUsers('gibbonPersonIDCoverage')
                    ->placeholder()
                    ->isRequired()
                    ->selected($coverage['gibbonPersonIDCoverage'] ?? '')
                    ->setReadonly(true);
            } elseif ($coverage['requestType'] == 'Broadcast') {
                $notificationList = $coverage['notificationSent'] == 'Y' ? json_decode($coverage['notificationListCoverage']) : [];
    
                if ($notificationList) {
                    $notified = $container->get(UserGateway::class)->selectNotificationDetailsByPerson($notificationList)->fetchGroupedUnique();
                    $row = $form->addRow();
                    $row->addLabel('notifiedLabel', __('Notified'));
                    $row->addTextArea('notified')->readonly()->setValue(Format::nameList($notified, 'Staff', false, true, ', '));
                }
            }
        } elseif ($coverage['status'] == 'Accepted' && !empty($coverage['gibbonPersonIDCoverage'])) {
    
            $row = $form->addRow();
            $row->addLabel('gibbonPersonIDLabel', __('Person'));
            $row->addSelectUsers('gibbonPersonIDCoverage')
                ->placeholder()
                ->isRequired()
                ->selected($coverage['gibbonPersonIDCoverage'] ?? '')
                ->setReadonly(true);
        }
    
        // Output the coverage status change timestamp, if it has been actioned
        if ($coverage['status'] != 'Requested' && !empty($coverage['timestampCoverage'])) {
            $row = $form->addRow();
            $row->addLabel('timestampCoverage', __($coverage['status']));
            $row->addTextField('timestampCoverageValue')
                ->readonly()
                ->setValue(Format::relativeTime($coverage['timestampCoverage'], false))
                ->setTitle($coverage['timestampCoverage']);
        }
    
        if (!empty($coverage['notesCoverage'])) {
            $row = $form->addRow();
                $row->addLabel('notesCoverageLabel', __('Reply'));
                $row->addTextArea('notesCoverage')->setRows(3)->readonly();
        }
    
        // DATA TABLE
        $absenceDates = $container->get(StaffAbsenceDateGateway::class)->selectDatesByCoverage($gibbonStaffCoverageID);
        
        $table = DataTable::create('staffCoverageDates');
        $table->setTitle(__('Dates'));
    
        $table->addColumn('date', __('Date'))
            ->format(Format::using('dateReadable', 'date'));
    
        $table->addColumn('timeStart', __('Time'))
            ->format(function ($absence) {
                if ($absence['allDay'] == 'N') {
                    return Format::small(Format::timeRange($absence['timeStart'], $absence['timeEnd']));
                } else {
                    return Format::small(__('All Day'));
                }
            });
    
        $table->addColumn('coverage', __('Coverage'))
            ->format(function ($absence) {
                if (empty($absence['coverage'])) {
                    return Format::small(__('N/A'));
                }
    
                return $absence['coverage'] == 'Accepted'
                        ? Format::name($absence['titleCoverage'], $absence['preferredNameCoverage'], $absence['surnameCoverage'], 'Staff', false, true)
                        : '<div class="badge success">'.__('Pending').'</div>';
            });
    
        $row = $form->addRow()->addContent($table->render($absenceDates->toDataSet()));

        $form->loadAllValuesFrom($coverage);

        return $form;
    }
}
