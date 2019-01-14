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

    private static $searchableColumns = ['absence.preferredName', 'absence.surname', 'coverage.preferredName', 'coverage.surname', 'gibbonStaffCoverage.status'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryCoverageBySchoolYear(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffCoverage.gibbonStaffCoverageID', 'gibbonStaffCoverage.status',  'gibbonStaffAbsenceType.name as type', 'reason', 'date', 'COUNT(*) as days', 'MIN(date) as dateStart', 'MAX(date) as dateEnd', 'allDay', 'timeStart', 'timeEnd', 'timestampRequested', 'timestampCoverage',  
                'gibbonStaffAbsence.gibbonPersonID', 'absence.title AS titleAbsence', 'absence.preferredName AS preferredNameAbsence', 'absence.surname AS surnameAbsence', 
                'gibbonStaffCoverage.gibbonPersonIDCoverage', 'coverage.title as titleCoverage', 'coverage.preferredName as preferredNameCoverage', 'coverage.surname as surnameCoverage',
                'gibbonStaffCoverage.notesRequested', 'absenceStaff.jobTitle as jobTitleAbsence'
            ])
            ->innerJoin('gibbonStaffAbsence', 'gibbonStaffCoverage.gibbonStaffAbsenceID=gibbonStaffAbsence.gibbonStaffAbsenceID')
            ->innerJoin('gibbonStaffAbsenceType', 'gibbonStaffAbsence.gibbonStaffAbsenceTypeID=gibbonStaffAbsenceType.gibbonStaffAbsenceTypeID')
            ->innerJoin('gibbonStaffAbsenceDate', 'gibbonStaffAbsenceDate.gibbonStaffCoverageID=gibbonStaffCoverage.gibbonStaffCoverageID')
            ->innerJoin('gibbonSchoolYear', 'gibbonStaffAbsenceDate.date BETWEEN firstDay AND lastDay')
            ->leftJoin('gibbonPerson AS coverage', 'gibbonStaffCoverage.gibbonPersonIDCoverage=coverage.gibbonPersonID')
            ->leftJoin('gibbonPerson AS absence', 'gibbonStaffAbsence.gibbonPersonID=absence.gibbonPersonID')
            ->leftJoin('gibbonStaff AS absenceStaff', 'absence.gibbonPersonID=absenceStaff.gibbonPersonID')
            ->where('gibbonSchoolYear.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->groupBy(['gibbonStaffCoverage.gibbonStaffCoverageID']);

        $criteria->addFilterRules($this->getSharedFilterRules());

        return $this->runQuery($query, $criteria);
    }

    public function queryCoverageByPersonCovering(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffCoverage.gibbonStaffCoverageID', 'gibbonStaffCoverage.status',  'gibbonStaffAbsenceType.name as type', 'reason', 'date', 'COUNT(*) as days', 'MIN(date) as dateStart', 'MAX(date) as dateEnd', 'allDay', 'timeStart', 'timeEnd', 'timestampRequested', 'timestampCoverage', 'gibbonStaffCoverage.gibbonPersonIDCoverage', 'gibbonStaffAbsence.gibbonPersonID', 
                'absence.title AS titleAbsence', 'absence.preferredName AS preferredNameAbsence', 'absence.surname AS surnameAbsence', 'gibbonStaffCoverage.notesRequested', 'absenceStaff.jobTitle as jobTitleAbsence'
            ])
            ->innerJoin('gibbonStaffAbsence', 'gibbonStaffCoverage.gibbonStaffAbsenceID=gibbonStaffAbsence.gibbonStaffAbsenceID')
            ->innerJoin('gibbonStaffAbsenceType', 'gibbonStaffAbsence.gibbonStaffAbsenceTypeID=gibbonStaffAbsenceType.gibbonStaffAbsenceTypeID')
            ->leftJoin('gibbonStaffAbsenceDate', 'gibbonStaffAbsenceDate.gibbonStaffCoverageID=gibbonStaffCoverage.gibbonStaffCoverageID')
            ->leftJoin('gibbonPerson AS absence', 'gibbonStaffAbsence.gibbonPersonID=absence.gibbonPersonID')
            ->leftJoin('gibbonStaff AS absenceStaff', 'absence.gibbonPersonID=absenceStaff.gibbonPersonID')
            ->where('gibbonStaffCoverage.gibbonPersonIDCoverage = :gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->groupBy(['gibbonStaffCoverage.gibbonStaffCoverageID'])
            ->orderBy(["gibbonStaffCoverage.status = 'Requested' DESC"]);

        $criteria->addFilterRules($this->getSharedFilterRules());

        return $this->runQuery($query, $criteria);
    }

    public function queryCoverageByPersonAbsent(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffCoverage.gibbonStaffCoverageID', 'gibbonStaffCoverage.status',  'gibbonStaffAbsenceType.name as type', 'reason', 'date', 'COUNT(*) as days', 'MIN(date) as dateStart', 'MAX(date) as dateEnd', 'allDay', 'timeStart', 'timeEnd', 'timestampRequested', 'timestampCoverage', 'gibbonStaffCoverage.gibbonPersonIDCoverage', 'gibbonStaffAbsence.gibbonPersonID', 
                'coverage.title as titleCoverage', 'coverage.preferredName as preferredNameCoverage', 'coverage.surname as surnameCoverage', 'gibbonStaffCoverage.notesCoverage'
            ])
            ->innerJoin('gibbonStaffAbsence', 'gibbonStaffCoverage.gibbonStaffAbsenceID=gibbonStaffAbsence.gibbonStaffAbsenceID')
            ->innerJoin('gibbonStaffAbsenceType', 'gibbonStaffAbsence.gibbonStaffAbsenceTypeID=gibbonStaffAbsenceType.gibbonStaffAbsenceTypeID')
            ->leftJoin('gibbonStaffAbsenceDate', 'gibbonStaffAbsenceDate.gibbonStaffCoverageID=gibbonStaffCoverage.gibbonStaffCoverageID')
            ->leftJoin('gibbonPerson AS coverage', 'gibbonStaffCoverage.gibbonPersonIDCoverage=coverage.gibbonPersonID')
            ->where('gibbonStaffAbsence.gibbonPersonID = :gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->groupBy(['gibbonStaffCoverage.gibbonStaffCoverageID'])
            ->orderBy(["gibbonStaffCoverage.status = 'Requested' DESC"]);

        $criteria->addFilterRules($this->getSharedFilterRules());

        return $this->runQuery($query, $criteria);
    }

    public function queryCoverageWithNoPersonAssigned(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffCoverage.gibbonStaffCoverageID', 'gibbonStaffCoverage.status',  'gibbonStaffAbsenceType.name as type', 'reason', 'date', 'COUNT(*) as days', 'MIN(date) as dateStart', 'MAX(date) as dateEnd', 'allDay', 'timeStart', 'timeEnd', 'timestampRequested', 'timestampCoverage', 'gibbonStaffCoverage.gibbonPersonIDCoverage', 'gibbonStaffAbsence.gibbonPersonID', 
                'absence.title AS titleAbsence', 'absence.preferredName AS preferredNameAbsence', 'absence.surname AS surnameAbsence', 'absenceStaff.jobTitle as jobTitleAbsence'
            ])
            ->innerJoin('gibbonStaffAbsence', 'gibbonStaffCoverage.gibbonStaffAbsenceID=gibbonStaffAbsence.gibbonStaffAbsenceID')
            ->innerJoin('gibbonStaffAbsenceType', 'gibbonStaffAbsence.gibbonStaffAbsenceTypeID=gibbonStaffAbsenceType.gibbonStaffAbsenceTypeID')
            ->leftJoin('gibbonStaffAbsenceDate', 'gibbonStaffAbsenceDate.gibbonStaffCoverageID=gibbonStaffCoverage.gibbonStaffCoverageID')
            ->leftJoin('gibbonPerson AS absence', 'gibbonStaffAbsence.gibbonPersonID=absence.gibbonPersonID')
            ->leftJoin('gibbonStaff AS absenceStaff', 'absence.gibbonPersonID=absenceStaff.gibbonPersonID')
            ->where('gibbonStaffCoverage.gibbonPersonIDCoverage IS NULL')
            ->groupBy(['gibbonStaffCoverage.gibbonStaffCoverageID']);

        $criteria->addFilterRules($this->getSharedFilterRules());

        return $this->runQuery($query, $criteria);
    }

    public function selectUnavailableDatesByPerson($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "(SELECT date as groupBy, 'Not Available' as status 
                FROM gibbonStaffCoverageException 
                WHERE gibbonStaffCoverageException.gibbonPersonIDCoverage=:gibbonPersonID 
                ORDER BY DATE)
            UNION ALL (
                SELECT date as groupBy, 'Exception' as status
                FROM gibbonStaffCoverage
                JOIN gibbonStaffAbsenceDate ON (gibbonStaffAbsenceDate.gibbonStaffCoverageID=gibbonStaffCoverage.gibbonStaffCoverageID)
                WHERE gibbonStaffCoverage.gibbonPersonIDCoverage=:gibbonPersonID 
            )
            UNION ALL (
                SELECT date as groupBy, 'Absent' as status
                FROM gibbonStaffAbsence
                JOIN gibbonStaffAbsenceDate ON (gibbonStaffAbsenceDate.gibbonStaffAbsenceID=gibbonStaffAbsence.gibbonStaffAbsenceID)
                WHERE gibbonStaffAbsence.gibbonPersonID=:gibbonPersonID 
            )";

        return $this->db()->select($sql, $data);
    }

    public function selectCoverageExceptionsByPerson($gibbonPersonIDCoverage)
    {
        $data = ['gibbonPersonIDCoverage' => $gibbonPersonIDCoverage];
        $sql = "SELECT date as groupBy, gibbonStaffCoverageException.* 
                FROM gibbonStaffCoverageException 
                WHERE gibbonPersonIDCoverage=:gibbonPersonIDCoverage 
                ORDER BY DATE";

        return $this->db()->select($sql, $data);
    }

    public function insertCoverageException($data)
    {
        $query = $this
            ->newInsert()
            ->into('gibbonStaffCoverageException')
            ->cols($data);

        return $this->runInsert($query);
    }

    public function deleteCoverageException($gibbonStaffCoverageExceptionID)
    {
        $query = $this
            ->newDelete()
            ->from('gibbonStaffCoverageException')
            ->where('gibbonStaffCoverageExceptionID=:gibbonStaffCoverageExceptionID')
            ->bindValue('gibbonStaffCoverageExceptionID', $gibbonStaffCoverageExceptionID);

        return $this->runDelete($query);
    }

    protected function getSharedFilterRules()
    {
        return [
            'requested' => function ($query, $requested) {
                return $requested == 'Y'
                    ? $query->where("gibbonStaffCoverage.status = 'Requested'")
                    : $query->where("gibbonStaffCoverage.status <> 'Requested'");
            },
            'status' => function ($query, $status) {
                return $query->where('gibbonStaffCoverage.status = :status')
                             ->bindValue('status', $status);
            },
            'date' => function ($query, $date) {
                switch (ucfirst($date)) {
                    case 'Upcoming': return $query->where("gibbonStaffAbsenceDate.date >= CURRENT_DATE()");
                    case 'Today'   : return $query->where("gibbonStaffAbsenceDate.date = CURRENT_DATE()");
                    case 'Past'    : return $query->where("gibbonStaffAbsenceDate.date < CURRENT_DATE()");
                }
            },
        ];
    }
}
