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


CREATE TABLE `dooraccess_log` ( 
    `dooraccessID` INT(16) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT , 
    `entrance` VARCHAR(60) NULL , 
    `timestamp` TIMESTAMP NULL , 
    `direction` ENUM('In','Out') NOT NULL DEFAULT 'Out', 
    `cardName` VARCHAR(60) NULL , 
    `cardID` VARCHAR(60) NULL , 
    `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NULL , 
    PRIMARY KEY (`dooraccessID`),
    UNIQUE KEY `log` (`entrance`, `timestamp`, `cardID`)
) ENGINE = InnoDB CHARSET=utf8 COLLATE utf8_general_ci;

*/

use Gibbon\Domain\User\UserGateway;

$_POST['address'] = '/modules/'.($argv[3] ?? 'System Admin').'/index.php';

require __DIR__.'/../gibbon.php';

// Cancel out now if we're not running via CLI
if (!isCommandLineInterface()) {
    die(__('This script cannot be run from a browser, only via CLI.'));
}

$logOriginalLocation = '/Users/sankuipe/Desktop/Localhost/gibbon/uploads/DOORACCESS.TXT';
$logBackupLocation = '/Users/sankuipe/Desktop/Localhost/gibbon/uploads/DOORACCESS_BAK.TXT';

if (!is_file($logOriginalLocation)) {
    echo "File not found: $logOriginalLocation \n";
    exit;
}

if (!copy($logOriginalLocation, $logBackupLocation)) {
    echo "Failed to copy: $logOriginalLocation \n";
    exit;
}

$lines = [];

// Read the contents of the backed-up file into an array
if (($handle = fopen($logBackupLocation, "r")) !== false) {
    while (($data = fgetcsv($handle, 250, ' ', '"')) !== false) {
        if (empty($data[0])) continue;

        $lines[] = [
            'date'     => $data[0] ?? '',
            'time'     => $data[1] ?? '',
            'entrance' => $data[2] ?? '',
            'cardID'   => $data[3] ?? '',
            'cardName' => $data[4] ?? '',
        ];
    }
    fclose($handle);
} else {
    echo "Failed to read: $logBackupLocation \n";
}

$userGateway = $container->get(UserGateway::class);
$success = 0;

// Sync logs with the database
foreach ($lines as $line) {
    $person = $userGateway->selectBy(['username' => $line['cardID']])->fetch();
    if (empty($person)) {
        echo "Could not find person: {$line['cardID']} \n";
    }

    // Parse log entries into useable info
    $date = '20'.substr($line['date'], 0, 2).'-'.substr($line['date'], 2, 2).'-'.substr($line['date'], 4, 2);
    $time = substr($line['time'], 0, 2).':'.substr($line['time'], 2, 2).':'.substr($line['time'], 4, 2);
    $direction = stripos($line['entrance'], 'Out') !== false ? 'Out' : 'In';

    $data = [
        'entrance'       => $line['entrance'],
        'timestamp'      => $date.' '.$time,
        'direction'      => $direction,
        'cardName'       => $line['cardName'],
        'cardID'         => $line['cardID'],
        'gibbonPersonID' => $person['gibbonPersonID'] ?? null,
    ];

    // Insert or update the logs in the database
    $sql = "INSERT INTO `dooraccess_log` (`entrance`, `timestamp`, `direction`, `cardName`, `cardID`, `gibbonPersonID`) VALUES (:entrance, :timestamp, :direction, :cardName, :cardID, :gibbonPersonID) ON DUPLICATE KEY UPDATE cardName=:cardName, cardID=:cardID";

    if ($pdo->statement($sql, $data)) {
        $success++;
    } else {
        echo "Failed to insert/update: {$data['entrance']} {$data['timestamp']} {$data['cardID']} {$data['cardName']} \n";
    }
}

if ($success > 0) {
    echo "Log sync successful: $success lines parsed. \n";
} else {
    echo "Log sync failed. \n";
}
