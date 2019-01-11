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
 * Staff Coverage Gateway
 *
 * @version v18
 * @since   v18
 */
class StaffCoverageGateway extends QueryableGateway
{
    use TableAware;
    use TableQueryAware;

    private static $tableName = 'gibbonStaffCoverage';
    private static $primaryKey = 'gibbonStaffCoverageID';

    private static $searchableColumns = [];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryCoverageByPersonCovering(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffCoverage.gibbonStaffCoverageID', 'gibbonStaffCoverage.status',  'gibbonStaffAbsenceType.name as type', 'reason', 'date', 'COUNT(*) as days', 'MIN(date) as dateStart', 'MAX(date) as dateEnd', 'allDay', 'timeStart', 'timeEnd', 'timestampRequested', 'gibbonStaffCoverage.gibbonPersonIDCoverage', 'gibbonStaffAbsence.gibbonPersonID', 
                'absence.title AS titleAbsence', 'absence.preferredName AS preferredNameAbsence', 'absence.surname AS surnameAbsence', 'gibbonStaffCoverage.notesRequested'
            ])
            ->innerJoin('gibbonStaffAbsenceDate', 'gibbonStaffAbsenceDate.gibbonStaffCoverageID=gibbonStaffCoverage.gibbonStaffCoverageID')
            ->innerJoin('gibbonStaffAbsence', 'gibbonStaffAbsenceDate.gibbonStaffAbsenceID=gibbonStaffAbsence.gibbonStaffAbsenceID')
            ->innerJoin('gibbonStaffAbsenceType', 'gibbonStaffAbsence.gibbonStaffAbsenceTypeID=gibbonStaffAbsenceType.gibbonStaffAbsenceTypeID')
            ->leftJoin('gibbonPerson AS absence', 'gibbonStaffAbsence.gibbonPersonID=absence.gibbonPersonID')
            ->where("gibbonStaffCoverage.status <> 'Requested'")
            ->where('gibbonStaffCoverage.gibbonPersonIDCoverage = :gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->groupBy(['gibbonStaffCoverage.gibbonStaffCoverageID']);

        return $this->runQuery($query, $criteria);
    }

    public function queryCoverageByPersonAbsent(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffCoverage.gibbonStaffCoverageID', 'gibbonStaffCoverage.status',  'gibbonStaffAbsenceType.name as type', 'reason', 'date', 'COUNT(*) as days', 'MIN(date) as dateStart', 'MAX(date) as dateEnd', 'allDay', 'timeStart', 'timeEnd', 'timestampRequested', 'gibbonStaffCoverage.gibbonPersonIDCoverage', 'gibbonStaffAbsence.gibbonPersonID', 
                'coverage.title as titleCoverage', 'coverage.preferredName as preferredNameCoverage', 'coverage.surname as surnameCoverage', 'gibbonStaffCoverage.notesCoverage'
            ])
            ->innerJoin('gibbonStaffAbsenceDate', 'gibbonStaffAbsenceDate.gibbonStaffCoverageID=gibbonStaffCoverage.gibbonStaffCoverageID')
            ->innerJoin('gibbonStaffAbsence', 'gibbonStaffAbsenceDate.gibbonStaffAbsenceID=gibbonStaffAbsence.gibbonStaffAbsenceID')
            ->innerJoin('gibbonStaffAbsenceType', 'gibbonStaffAbsence.gibbonStaffAbsenceTypeID=gibbonStaffAbsenceType.gibbonStaffAbsenceTypeID')
            ->leftJoin('gibbonPerson AS coverage', 'gibbonStaffCoverage.gibbonPersonIDCoverage=coverage.gibbonPersonID')
            ->where('gibbonStaffAbsence.gibbonPersonID = :gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->groupBy(['gibbonStaffCoverage.gibbonStaffCoverageID']);

        return $this->runQuery($query, $criteria);
    }

    public function queryCoverageWithNoPersonAssigned(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffCoverage.gibbonStaffCoverageID', 'gibbonStaffCoverage.status',  'gibbonStaffAbsenceType.name as type', 'reason', 'date', 'COUNT(*) as days', 'MIN(date) as dateStart', 'MAX(date) as dateEnd', 'allDay', 'timeStart', 'timeEnd', 'timestampRequested', 'gibbonStaffCoverage.gibbonPersonIDCoverage', 'gibbonStaffAbsence.gibbonPersonID', 
                'absence.title AS titleAbsence', 'absence.preferredName AS preferredNameAbsence', 'absence.surname AS surnameAbsence',
            ])
            ->innerJoin('gibbonStaffAbsenceDate', 'gibbonStaffAbsenceDate.gibbonStaffCoverageID=gibbonStaffCoverage.gibbonStaffCoverageID')
            ->innerJoin('gibbonStaffAbsence', 'gibbonStaffAbsenceDate.gibbonStaffAbsenceID=gibbonStaffAbsence.gibbonStaffAbsenceID')
            ->innerJoin('gibbonStaffAbsenceType', 'gibbonStaffAbsence.gibbonStaffAbsenceTypeID=gibbonStaffAbsenceType.gibbonStaffAbsenceTypeID')
            ->leftJoin('gibbonPerson AS absence', 'gibbonStaffAbsence.gibbonPersonID=absence.gibbonPersonID')
            ->where("gibbonStaffCoverage.status = 'Requested'")
            ->where('(gibbonStaffCoverage.gibbonPersonIDCoverage=:gibbonPersonID OR gibbonStaffCoverage.gibbonPersonIDCoverage IS NULL)')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->groupBy(['gibbonStaffCoverage.gibbonStaffCoverageID']);

        return $this->runQuery($query, $criteria);
    }
}
