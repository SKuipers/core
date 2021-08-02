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

$gibbonFileExtensionID = $_GET['gibbonFileExtensionID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/fileExtensions_manage_delete.php&gibbonFileExtensionID='.$gibbonFileExtensionID;
$URLDelete = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/fileExtensions_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/School Admin/fileExtensions_manage_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if school year specified
    if ($gibbonFileExtensionID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('gibbonFileExtensionID' => $gibbonFileExtensionID);
            $sql = 'SELECT * FROM gibbonFileExtension WHERE gibbonFileExtensionID=:gibbonFileExtensionID';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        if ($result->rowCount() != 1) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
        } else {
            //Write to database
            try {
                $data = array('gibbonFileExtensionID' => $gibbonFileExtensionID);
                $sql = 'DELETE FROM gibbonFileExtension WHERE gibbonFileExtensionID=:gibbonFileExtensionID';
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
