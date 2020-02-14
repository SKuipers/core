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

namespace Gibbon\Module\Reports\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/*
CREATE TABLE `gibbonReportingGPA` ( 
    `gibbonReportingGPAID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT, 
    `gibbonReportingCycleID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonIDStudent` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `gibbonYearGroupID` INT(3) UNSIGNED ZEROFILL NOT NULL,
    `gpa` VARCHAR(30) NULL,
    `status` VARCHAR(90) NULL,
    `timestamp` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`gibbonReportingGPAID`),
    UNIQUE KEY (`gibbonReportingCycleID`, `gibbonPersonIDStudent`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8;end
*/
class ReportingGPAGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonReportingGPA';
    private static $primaryKey = 'gibbonReportingGPAID';
    private static $searchableColumns = [''];

}
