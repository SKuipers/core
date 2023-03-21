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
use Gibbon\Services\Format;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/System Admin/systemSettings.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs->add(__('All Settings'));
    $page->return->addReturns(['error6' => __('The uploaded file was missing or only partially uploaded.')]);

    $search = $_GET['search'] ?? '';

    // QUERY
    $settingGateway = $container->get(SettingGateway::class);
    $criteria = $settingGateway->newQueryCriteria()
        ->searchBy($settingGateway->getSearchableColumns(), $search)
        ->sortBy('name')
        ->fromPOST();

    $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/System Admin/settings.php');

    $row = $form->addRow();
        $row->addLabel('search', __('Search For'));
        $row->addTextField('search')->setValue($criteria->getSearchText());

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Search'));

    echo $form->getOutput();


    // FORM
    $form = Form::create('manageSettings', $session->get('absoluteURL').'/modules/System Admin/systemSettingsProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));

    $scopes = ['Activities', 'Markbook', 'User Admin'];

    $settingsList = $settingGateway->querySettings($criteria, $scopes);

    $settingsGrouped = array_reduce($settingsList->toArray(), function ($group, $item) {
        $group[$item['scope']][] = $item;
        return $group;
    }, []);

    // What needs to go into the database?
    // Value type, required, options, heading, defaultValue, sequenceNumber 

    foreach ($settingsGrouped as $scope => $settings) {

        $row = $form->addRow()->addHeading($scope, __($scope).' ('.count($settings).')');

        foreach ($settings as $setting) {
            $row = $form->addRow();
            $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
            $row->addTextField($setting['name'])->setValue($setting['value'])->maxLength(100)->required();

            $form->addHiddenValue("setting[{$setting['scope']}][{$setting['name']}]", 'Y');
        }

    }
    


    echo $form->getOutput();
}
