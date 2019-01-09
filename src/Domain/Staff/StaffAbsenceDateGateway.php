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

namespace Gibbon\Domain\Staff;

use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\Traits\TableQueryAware;

/**
 * Staff Absence Date Gateway
 *
 * @version v18
 * @since   v18
 */
class StaffAbsenceDateGateway extends QueryableGateway
{
    use TableAware;
    use TableQueryAware;

    private static $tableName = 'gibbonStaffAbsenceDate';
    private static $primaryKey = 'gibbonStaffAbsenceDateID';

    private static $searchableColumns = [];

    public function selectDatesByAbsence($gibbonStaffAbsenceID)
    {
        $data = ['gibbonStaffAbsenceID' => $gibbonStaffAbsenceID];
        $sql = 'SELECT * FROM gibbonStaffAbsenceDate WHERE gibbonStaffAbsenceID=:gibbonStaffAbsenceID';

        return $this->db()->select($sql, $data);
    }

    public function selectAbsenceDatesByPerson($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = 'SELECT gibbonStaffAbsenceDate.date as groupBy, gibbonStaffAbsence.*, gibbonStaffAbsenceDate.*, gibbonStaffAbsenceType.name as type, gibbonStaffAbsenceType.sequenceNumber
                FROM gibbonStaffAbsence 
                JOIN gibbonStaffAbsenceDate ON (gibbonStaffAbsenceDate.gibbonStaffAbsenceID=gibbonStaffAbsence.gibbonStaffAbsenceID) 
                JOIN gibbonStaffAbsenceType ON (gibbonStaffAbsenceType.gibbonStaffAbsenceTypeID=gibbonStaffAbsence.gibbonStaffAbsenceTypeID)
                WHERE gibbonStaffAbsence.gibbonPersonID=:gibbonPersonID';

        return $this->db()->select($sql, $data);
    }

    public function selectAbsenceDatesByDateRange($dateStart, $dateEnd = null)
    {
        if (empty($dateEnd)) $dateEnd = $dateStart;

        $data = ['dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = 'SELECT gibbonStaffAbsenceDate.date as groupBy, gibbonStaffAbsence.*, gibbonStaffAbsenceDate.*, gibbonStaffAbsenceType.name as type, gibbonStaffAbsenceType.sequenceNumber
                FROM gibbonStaffAbsence 
                JOIN gibbonStaffAbsenceDate ON (gibbonStaffAbsenceDate.gibbonStaffAbsenceID=gibbonStaffAbsence.gibbonStaffAbsenceID) 
                JOIN gibbonStaffAbsenceType ON (gibbonStaffAbsenceType.gibbonStaffAbsenceTypeID=gibbonStaffAbsence.gibbonStaffAbsenceTypeID)
                WHERE gibbonStaffAbsenceDate.date BETWEEN :dateStart AND :dateEnd';

        return $this->db()->select($sql, $data);
    }
}
