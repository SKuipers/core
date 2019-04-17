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
use Psr\Container\ContainerInterface;
use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Services\Format;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\Staff\StaffAbsenceTypeGateway;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Module\Staff\Tables\AbsenceFormats;

/**
 * ViewAbsenceForm
 *
 * @version v18
 * @since   v18
 */
class ViewAbsenceForm
{
    public static function create(ContainerInterface $container, $gibbonStaffAbsenceID)
    {
        $guid = $container->get('config')->getConfig('guid');
        $pdo = $container->get('db');
        $connection2 = $pdo->getConnection();

        $values = $container->get(StaffAbsenceGateway::class)->getByID($gibbonStaffAbsenceID);

        $canManage = isActionAccessible($guid, $connection2, '/modules/Staff/absences_manage.php') || $values['gibbonPersonID'] == $_SESSION[$guid]['gibbonPersonID'];
        $canRequest = isActionAccessible($guid, $connection2, '/modules/Staff/coverage_request.php');

        $form = Form::create('staffAbsence', '');

        $form->setFactory(DatabaseFormFactory::create($pdo));
        $form->addHiddenValue('address', $_SESSION[$guid]['address']);
        $form->addHiddenValue('gibbonStaffAbsenceID', $gibbonStaffAbsenceID);

        // $table = $form->addRow()->addTable()->setClass('smallIntBorder standardForm fullWidth');

        $row = $form->addRow();
            $row->addLabel('personLabel', __('Person'));
            $row->addSelectStaff('person')->readonly()->selected($values['gibbonPersonID']);

        $type = $container->get(StaffAbsenceTypeGateway::class)->getByID($values['gibbonStaffAbsenceTypeID']);

        $row = $form->addRow();
            $row->addLabel('typeLabel', __('Type'));
            $row->addTextField('type')->readonly()->setValue($type['name']);

        if (!empty($values['reason'])) {
            $row = $form->addRow()->addClass('reasonOptions');
                $row->addLabel('reasonLabel', __('Reason'));
                $row->addTextField('reason')->readonly();
        }
        
        if ($type['requiresApproval'] == 'Y') {
            $approver = '';
            if (!empty($values['gibbonPersonIDApproval'])) {
                $approver = $container->get(UserGateway::class)->getByID($values['gibbonPersonIDApproval']);
                $approver = Format::small(__('By').' '.Format::nameList([$approver], 'Staff', false, true));

                if (!empty($values['commentConfidential']) && $_SESSION[$guid]['gibbonPersonID'] == $values['gibbonPersonIDApproval']) {
                    $row = $form->addRow();
                        $row->addLabel('commentConfidentialLabel', __('Confidential Comment'))->description(__('This message is only shared with the selected approver.'));
                        $row->addTextArea('commentConfidential')->setRows(2)->readonly();
                }
            }

            $row = $form->addRow();
                $row->addLabel('status', __('Status'));
                $row->addContent($values['status'].'<br/>'.$approver)->wrap('<div class="standardWidth floatRight">', '</div>');
        }

        $creator = $container->get(UserGateway::class)->getByID($values['gibbonPersonIDCreator']);

        $row = $form->addRow();
            $row->addLabel('timestampLabel', __('Created'));
            $row->addContent(Format::relativeTime($values['timestampCreator']).'<br/>'.Format::small(__('By').' '.Format::nameList([$creator], 'Staff')))->wrap('<div class="standardWidth floatRight">', '</div>');

        if ($canManage) {
            $row = $form->addRow();
                $row->addLabel('commentLabel', __('Comment'));
                $row->addTextArea('comment')->setRows(2)->readonly();
        }

        $absenceDates = $container->get(StaffAbsenceDateGateway::class)->selectDatesByAbsence($values['gibbonStaffAbsenceID']);

        $table = $form->addRow()->addDataTable('staffAbsenceDates')->withData($absenceDates->toDataSet());
        $table->setTitle(__('Dates'));

        $table->addColumn('date', __('Date'))
              ->format(Format::using('dateReadable', 'date'));

        $table->addColumn('timeStart', __('Time'))
              ->format([AbsenceFormats::class, 'timeDetails']);

        $table->addColumn('coverage', __('Coverage'))
              ->format([AbsenceFormats::class, 'coverage']);

        if ($canManage && $canRequest && $values['status'] == 'Approved') {
            $table->addActionColumn()
                ->addParam('gibbonStaffAbsenceID')
                ->format(function ($absence, $actions) {
                    if (!empty($absence['gibbonStaffCoverageID'])) return;
                    if ($absence['date'] < date('Y-m-d')) return;

                    $actions->addAction('coverage', __('Request Coverage'))
                        ->setIcon('attendance')
                        ->setURL('/modules/Staff/coverage_request.php');
                });
        }
        
        return $form;
    }
}
