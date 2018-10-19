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

use Gibbon\Domain\DataSet;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\DataAuditGateway;

if (isActionAccessible($guid, $connection2, '/modules/System Admin/systemSettings.php') == false) {
    //Acess denied
    echo "<div class='error'>";
    echo __('You do not have access to this action.');
    echo '</div>';
} else {
    //Proceed!
    $gibbonDataAuditID = isset($_GET['gibbonDataAuditID'])? $_GET['gibbonDataAuditID'] : '';

    if (empty($gibbonDataAuditID)) {
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

    $eventData = json_decode($dataAudit['eventData'], true);

    $table = DataTable::create('dataAudit');

    if ($dataAudit['event'] == 'Updated') {
        $eventData = array_map(function ($key, $value) {
            return array('field' => $key, 'valueOld' => $value['old'], 'valueNew' => $value['new'] );
        }, array_keys($eventData), $eventData);

        $table->setTitle(__('Changes'));
        $table->addColumn('field', __('Field'))->width('30%');
        $table->addColumn('valueOld', __('Original Value'))->width('35%');
        $table->addColumn('valueNew', __('New Value'))->width('35%');
    } else {
        $eventData = array_map(function ($key, $value) {
            return array('field' => $key, 'value' => $value);
        }, array_keys($eventData), $eventData);

        $table->setTitle(__('Details'));
        $table->addColumn('field', __('Field'))->width('30%');
        $table->addColumn('value', __('Value'))->width('70%');
    }

    echo $table->render(new DataSet($eventData));
}
