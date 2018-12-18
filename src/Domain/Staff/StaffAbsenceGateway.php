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
 * Staff Absence Gateway
 *
 * @version v18
 * @since   v18
 */
class StaffAbsenceGateway extends QueryableGateway
{
    use TableAware;
    use TableQueryAware;

    private static $tableName = 'gibbonStaffAbsence';
    private static $primaryKey = 'gibbonStaffAbsenceID';

    private static $searchableColumns = [];

    /**
     * Queries the list of users for the Manage Staff page.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAbsencesByPerson(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffAbsence.gibbonStaffAbsenceID', 'gibbonStaffAbsence.gibbonPersonID', 'gibbonStaffAbsenceType.name as type', 'reason', 'comment', 'date', 'COUNT(*) as days', 'allDay', 'timestampStart', 'timestampEnd', 'timestampCreator', 'gibbonStaffAbsence.gibbonPersonIDCreator', 'gibbonPerson.title', 'gibbonPerson.preferredName', 'gibbonPerson.surname', 
            ])
            ->innerJoin('gibbonStaffAbsenceType', 'gibbonStaffAbsence.gibbonStaffAbsenceTypeID=gibbonStaffAbsenceType.gibbonStaffAbsenceTypeID')
            ->leftJoin('gibbonPerson', 'gibbonStaffAbsence.gibbonPersonIDCreator=gibbonPerson.gibbonPersonID')
            ->where('gibbonStaffAbsence.gibbonPersonID = :gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->groupBy(['timestampStart', 'timestampEnd']);

        return $this->runQuery($query, $criteria);
    }
}
