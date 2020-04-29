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
use Gibbon\Domain\Activities\ActivityTypeGateway;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;

if (isActionAccessible($guid, $connection2, '/modules/School Admin/activitySettings.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Activity Settings'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    $activityTypes = getSettingByScope($connection2, 'Activities', 'activityTypes');
    $activityTypeGateway = $container->get(ActivityTypeGateway::class);
    $settingGateway = $container->get(SettingGateway::class);
    
    // Activity Types - CSV to Table Migration
    if (!empty($activityTypes)) {
        $continue = true;
        $activityTypes = array_map('trim', explode(',', $activityTypes));
        $access = getSettingByScope($connection2, 'Activities', 'access');
        $enrolmentType = getSettingByScope($connection2, 'Activities', 'enrolmentType');
        $backupChoice = getSettingByScope($connection2, 'Activities', 'backupChoice');

        foreach ($activityTypes as $type) {
            $inserted = $activityTypeGateway->insert(['name' => $type, 'access' => $access, 'enrolmentType' => $enrolmentType, 'backupChoice' => $backupChoice]);
            $continue &= $inserted;
        }

        if ($continue) {
            $settingGateway->updateSettingByScope('Activities', 'activityTypes', '');
        }
    }

    // QUERY
    $criteria = $activityTypeGateway->newQueryCriteria()
        ->sortBy(['name'])
        ->fromArray($_POST);

    $activityTypes = $activityTypeGateway->queryActivityTypes($criteria);

    // DATA TABLE
    $table = DataTable::createPaginated('activityTypes', $criteria);
    $table->setTitle(__('Activity Types'));
    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/School Admin/activitySettings_type_add.php')
        ->displayLabel();

    $table->addColumn('name', __('Name'));
    $table->addColumn('access', __('Access'));
    $table->addColumn('enrolmentType', __('Enrolment Type'));
    $table->addColumn('maxPerStudent', __('Max per Student'))->width('10%');
    $table->addColumn('waitingList', __('Waiting List'))->width('10%');
    $table->addColumn('backupChoice', __('Backup Choice'))->width('10%');

    // ACTIONS
    $table->addActionColumn()
        ->addParam('gibbonActivityTypeID')
        ->format(function ($values, $actions) {
            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/School Admin/activitySettings_type_edit.php');

            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/School Admin/activitySettings_type_delete.php');
        });

    echo $table->render($activityTypes);


    $form = Form::create('activitySettings', $_SESSION[$guid]['absoluteURL'].'/modules/'.$_SESSION[$guid]['module'].'/activitySettingsProcess.php');
    $form->setTitle(__('Settings'));
    $form->addHiddenValue('address', $_SESSION[$guid]['address']);
    $form->addHiddenValue('activityTypes', '');

    $accessTypes = array(
        'None' => __('None'),
        'View' => __('View'),
        'Register' =>  __('Register')
    );
    $setting = getSettingByScope($connection2, 'Activities', 'access', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addSelect($setting['name'])->fromArray($accessTypes)->selected($setting['value'])->required();

    $dateTypes = array(
        'Date' => __('Date'),
        'Term' =>  __('Term')
    );
    $setting = getSettingByScope($connection2, 'Activities', 'dateType', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addSelect($setting['name'])->fromArray($dateTypes)->selected($setting['value'])->required();

    $form->toggleVisibilityByClass('perTerm')->onSelect($setting['name'])->when('Term');

    $setting = getSettingByScope($connection2, 'Activities', 'maxPerTerm', true);
    $row = $form->addRow()->addClass('perTerm');
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addSelect($setting['name'])->fromString('0,1,2,3,4,5')->selected($setting['value'])->required();

    $paymentTypes = array(
        'None' => __('None'),
        'Single' => __('Single'),
        'Per Activity' =>  __('Per Activity'),
        'Single + Per Activity' =>  __('Single + Per Activity')
    );
    $setting = getSettingByScope($connection2, 'Activities', 'payment', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addSelect($setting['name'])->fromArray($paymentTypes)->selected($setting['value'])->required();

    $setting = getSettingByScope($connection2, 'Activities', 'disableExternalProviderSignup', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addYesNo($setting['name'])->selected($setting['value'])->required();

    $setting = getSettingByScope($connection2, 'Activities', 'hideExternalProviderCost', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addYesNo($setting['name'])->selected($setting['value'])->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
