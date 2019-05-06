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

require getcwd().'/../config.php';
require getcwd().'/../functions.php';

getSystemSettings($guid, $connection2);

setCurrentSchoolYear($guid, $connection2);

//Check for CLI, so this cannot be run through browser
if (php_sapi_name() != 'cli') { echo __($guid, 'This script cannot be run from a browser, only via CLI.');
} else {

    $photoPathStudents = $_SESSION[$guid]['absolutePath'] .'/uploads/photosStudents';
    $photoPathStudentsOutput = $_SESSION[$guid]['absolutePath'] .'/uploads/photosStudentsDSEJ';

    if (is_dir($photoPathStudents)==FALSE || is_dir($photoPathStudentsOutput)==FALSE) {
        exit('Missing student photo folder');
    }


    $photos = [];
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($photoPathStudents, FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $filename => $fileInfo) {
        if (!$fileInfo->isDir()) {
            $photos[] = $filename;
        }
    }

    if (empty($photos) || !is_array($photos)) {
        exit('No contents in staff photo folder');
    }

    echo "Photos found: " . count($photos) . "\n";

    $countRenamed = 0;

    foreach ($photos as $filename) {

        $photoName = basename($filename);

        $studentDSEJID = str_ireplace('.jpg', '', $photoName);

        // echo "DSEJID: ".$studentDSEJID."\n";

        try {
            $data = array( 'studentDSEJID' => $studentDSEJID);
            $sql = "SELECT username FROM gibbonPerson as p WHERE (CASE WHEN (@find2 := LOCATE('s:3:\"009\";s', p.fields)) > 0 THEN REPLACE(LEFT( @var2 := SUBSTRING(p.fields, @find2 + 15), LOCATE('\";', @var2)-1), '\"', '') ELSE '' END) = :studentDSEJID";
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            die("Your request failed due to a database error. ".$e->getMessage()."\n");
        }

        if ($result->rowCount() == 1) {
            $row = $result->fetch();

            $newFilename = $photoPathStudentsOutput.'/'.$row['username'].'.jpg';
            rename($filename, $newFilename);

            $countRenamed++;
        }
        else if ($result->rowCount() > 1) {
            echo "Ambiguous results for: " . $filename . "\n"; 
        }
    }

    echo "\n";
    echo "Photos renamed: " . $countRenamed . "\n";
}
