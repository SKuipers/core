<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

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

$gibbonAttendanceCodeID = (isset($_GET['gibbonAttendanceCodeID']))? $_GET['gibbonAttendanceCodeID'] : NULL;
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/attendanceSettings_manage_delete.php&gibbonAttendanceCodeID='.$gibbonAttendanceCodeID;
$URLDelete = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/attendanceSettings.php';

if (isActionAccessible($guid, $connection2, '/modules/School Admin/attendanceSettings_manage_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if gibbonAttendanceCodeID specified
    if ($gibbonAttendanceCodeID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('gibbonAttendanceCodeID' => $gibbonAttendanceCodeID);
            $sql = 'SELECT type FROM gibbonAttendanceCode WHERE gibbonAttendanceCodeID=:gibbonAttendanceCodeID';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        $row = $result->fetch();

        if ($result->rowCount() != 1 || $row['type'] == 'Core') {
            $URL .= '&return=error2';
            header("Location: {$URL}");
        } else {
            //Write to database
            try {
                $data = array('gibbonAttendanceCodeID' => $gibbonAttendanceCodeID);
                $sql = 'DELETE FROM gibbonAttendanceCode WHERE gibbonAttendanceCodeID=:gibbonAttendanceCodeID';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            $URLDelete = $URLDelete.'&return=success0';
            header("Location: {$URLDelete}");
        }
    }
}
