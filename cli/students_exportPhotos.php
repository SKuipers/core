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

require getcwd().'/../gibbon.php';

//Check for CLI, so this cannot be run through browser
if (!isCommandLineInterface()) { 
    echo __($guid, 'This script cannot be run from a browser, only via CLI.');
} else {

    $photoPathBase = $session->get('absolutePath') .'/../archive/photos/photosFormGroup/';
    if (is_dir($photoPathBase)==FALSE) {
        mkdir($photoPathBase, 0755, TRUE);
    }

    try {
        $data = array('gibbonSchoolYearID' => $session->get('gibbonSchoolYearID') );
        $sql = "SELECT username, surname, preferredName, studentID, image_240, gibbonPerson.fields, gibbonFormGroup.nameShort as formGroup from gibbonPerson
        JOIN gibbonStudentEnrolment ON (gibbonPerson.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID)
        JOIN gibbonFormGroup ON (gibbonStudentEnrolment.gibbonFormGroupID=gibbonFormGroup.gibbonFormGroupID)
        WHERE gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
        AND gibbonPerson.status = 'Full'
        ORDER BY surname, preferredName";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        die($e->getMessage());
    }

    if ($result->rowCount() == 0) die("Could not load student info.\n");

    $restrainedQuality = 95; //0 = lowest, 100 = highest. ~75 = default
    $sizeLimit = 1024 * 100;

    $countStudents = 0;
    $countFormGroupID = 0;
    $countCopied = 0;
    $countResized = 0;

    $missingFormGroup = '';
    $missingPhoto = '';

    while ($row = $result->fetch()) {
        $countStudents++;

        $photoPath = $session->get('absolutePath') .'/'. $row['image_240'];
        $photoPathFormGroup = $photoPathBase . $row['formGroup'] .'/';

        if (is_dir( $photoPathFormGroup )==FALSE) {
            mkdir( $photoPathFormGroup , 0777, TRUE) ;
        }

        $photoName = $row['username'] . ' - '.$row['surname'].', '. $row['preferredName'] . '.jpg';

        if (is_file($photoPath)) {
            
            if (copy($photoPath, $photoPathFormGroup . $photoName)) {
                $countCopied++;
            }
        } else {
            $missingPhoto .= $row['surname'].', '. $row['preferredName'] .' ('.$row['formGroup'].', Student ID:'. $row['studentID'] .')' . "\n";
        }
    }

    if (!empty($missingPhoto)) {
        echo "Missing Photos: \n";
        echo $missingPhoto . "\n\n";
    }

    echo "Students Total: " . $countStudents . "\n";
    echo "Photos copied: " . $countCopied . "\n";
}
