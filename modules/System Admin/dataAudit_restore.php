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
use Gibbon\Domain\System\DataAuditGateway;

if (isActionAccessible($guid, $connection2, '/modules/System Admin/systemSettings.php') == false) {
    //Acess denied
    echo "<div class='error'>";
    echo __('You do not have access to this action.');
    echo '</div>';
} else {
    //Proceed!
    $gibbonDataAuditID = isset($_GET['gibbonDataAuditID'])? $_GET['gibbonDataAuditID'] : '';
    $redirect = isset($_GET['redirect'])? $_GET['redirect'] : '';

    if (empty($gibbonDataAuditID) || empty($redirect)) {
        echo "<div class='error'>";
        echo __('You have not specified one or more required parameters.');
        echo '</div>';
        return;
    }

    $dataAuditGateway = $container->get(DataAuditGateway::class);
    $dataAudit = $dataAuditGateway->getDataAuditByID($gibbonDataAuditID);

    if (empty($dataAudit)) {
        echo "<div class='error'>";
        echo __('The specified record cannot be found.');
        echo '</div>';
        return;
    }

    if (isActionAccessible($guid, $connection2, '/modules/'.$dataAudit['moduleName'].'/'.$dataAudit['gibbonActionURL']) == false) {
        echo "<div class='error'>";
        echo __('You do not have access to this action.');
        echo '</div>';
        return;
    }

    $form = Form::create('restoreRecord', $_SESSION[$guid]['absoluteURL'].'/modules/System Admin/dataAudit_restoreProcess.php');
    $form->addHiddenValue('address', $_GET['q']);
    $form->addHiddenValue('redirect', $redirect);
    $form->addHiddenValue('gibbonDataAuditID', $gibbonDataAuditID);

    $row = $form->addRow();
        $col = $row->addColumn();
        $col->addContent(__('Are you sure you want to restore this record?'))->wrap('<strong>', '</strong>');
        $col->addContent(__('Restoring a deleted record will not re-create any other records that may have been attached to this one.'))
            ->wrap('<span style="color: #cc0000"><is>', '</is></span>');


    $row = $form->addRow();
    $row->addLabel('confirm', sprintf(__('Type %1$s to confirm'), __('CONFIRM')));
    $row->addTextField('confirm')
        ->isRequired()
        ->addValidation(
            'Validate.Inclusion',
            'within: [\''.__('CONFIRM').'\'], failureMessage: "'.__('Please enter the text exactly as it is displayed to confirm this action.').'", caseSensitive: false')
        ->addValidationOption('onlyOnSubmit: true');

    $form->addRow()->addConfirmSubmit();

    echo $form->getOutput();
}
