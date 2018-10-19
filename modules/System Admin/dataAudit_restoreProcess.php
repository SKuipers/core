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

include '../../gibbon.php';

use Gibbon\Domain\System\DataAuditGateway;

$gibbonDataAuditID = isset($_POST['gibbonDataAuditID'])? $_POST['gibbonDataAuditID'] : '';
$redirect = isset($_POST['redirect'])? $_POST['redirect'] : '';

$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q='.$redirect;

if (isActionAccessible($guid, $connection2, '/modules/System Admin/systemSettings.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else if (empty($gibbonDataAuditID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    
    $dataAuditGateway = $container->get(DataAuditGateway::class);
    $dataAudit = $dataAuditGateway->getDataAuditByID($gibbonDataAuditID);

    if (empty($dataAudit)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    if (isActionAccessible($guid, $connection2, '/modules/'.$dataAudit['moduleName'].'/'.$dataAudit['gibbonActionURL']) == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    $eventData = json_decode($dataAudit['eventData'], true);

    if (empty($eventData)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }


    $success = $dataAuditGateway->restoreRecord($dataAudit['foreignTable'], $eventData);

    $dataAudit = $dataAuditGateway->get($gibbonDataAuditID);
    $dataAudit['event'] = 'Restored';
    $dataAudit['timestamp'] = date('Y-m-d H:i:s');
    $dataAudit['gibbonPersonID'] = $gibbon->session->get('gibbonPersonID');
    $dataAudit['gibbonRoleID'] = $gibbon->session->get('gibbonRoleIDCurrent');
    $dataAuditGateway->update($dataAudit);

    if (!$success) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    } else {
        $URL .= '&return=success0';
        header("Location: {$URL}");
        exit;
    }
}
