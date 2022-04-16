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
use Gibbon\Data\Validator;

include '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['body' => 'HTML']);

$address = $_POST['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address).'/cannedResponse_manage_add.php';

if (isActionAccessible($guid, $connection2, '/modules/Messenger/cannedResponse_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Validate Inputs
    $subject = $_POST['subject'] ?? '';
    $body = $_POST['body'] ?? '';

    if ($body == '' or $body == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        //Check unique inputs for uniqueness
        try {
            $data = array('subject' => $subject);
            $sql = 'SELECT * FROM gibbonMessengerCannedResponse WHERE subject=:subject';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        if ($result->rowCount() > 0) {
            $URL .= '&return=error3';
            header("Location: {$URL}");
        } else {
            //Write to database
            try {
                $data = array('subject' => $subject, 'body' => $body, 'gibbonPersonIDCreator' => $session->get('gibbonPersonID'));
                $sql = 'INSERT INTO gibbonMessengerCannedResponse SET subject=:subject, body=:body, gibbonPersonIDCreator=:gibbonPersonIDCreator';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            //Last insert ID
            $AI = str_pad($connection2->lastInsertID(), 10, '0', STR_PAD_LEFT);

            //Success 0
            $URL .= "&return=success0&editID=$AI";
            header("Location: {$URL}");
        }
    }
}
