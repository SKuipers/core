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

    private static $searchableColumns = ['gibbonStaffAbsence.reason', 'gibbonStaffAbsence.comment', 'gibbonStaffAbsenceType.name', 'gibbonPerson.preferredName', 'gibbonPerson.surname'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAbsencesByPerson(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffAbsence.gibbonStaffAbsenceID', 'gibbonStaffAbsence.gibbonPersonID', 'gibbonStaffAbsenceType.name as type', 'reason', 'comment', 'date', 'COUNT(*) as days', 'MIN(date) as dateStart', 'MAX(date) as dateEnd', 'allDay', 'timeStart', 'timeEnd', 'timestampCreator', 'gibbonStaffAbsence.gibbonPersonIDCreator', 'creator.preferredName AS preferredNameCreator', 'creator.surname AS surnameCreator', 'gibbonStaffCoverage.status as coverage'
            ])
            ->innerJoin('gibbonStaffAbsenceType', 'gibbonStaffAbsence.gibbonStaffAbsenceTypeID=gibbonStaffAbsenceType.gibbonStaffAbsenceTypeID')
            ->innerJoin('gibbonStaffAbsenceDate', 'gibbonStaffAbsenceDate.gibbonStaffAbsenceID=gibbonStaffAbsence.gibbonStaffAbsenceID')
            ->leftJoin('gibbonStaffCoverage', 'gibbonStaffCoverage.gibbonStaffCoverageID=gibbonStaffAbsenceDate.gibbonStaffCoverageID')
            ->leftJoin('gibbonPerson AS creator', 'gibbonStaffAbsence.gibbonPersonIDCreator=creator.gibbonPersonID')
            ->where('gibbonStaffAbsence.gibbonPersonID = :gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->groupBy(['gibbonStaffAbsence.gibbonStaffAbsenceID']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAbsencesByDateRange(QueryCriteria $criteria, $dateStart, $dateEnd = null, $grouped = true)
    {
        if (empty($dateEnd)) $dateEnd = $dateStart;
        
        $query = $this
            ->newQuery()
            ->from('gibbonStaffAbsenceDate')
            ->cols([
                'gibbonStaffAbsence.gibbonStaffAbsenceID', 'gibbonStaffAbsence.gibbonPersonID', 'gibbonStaffAbsenceType.name as type', 'reason', 'comment', 'gibbonStaffAbsenceDate.date',  'gibbonStaffAbsenceDate.allDay', 'gibbonStaffAbsenceDate.timeStart', 'gibbonStaffAbsenceDate.timeEnd', 'timestampCreator',   'MIN(gibbonStaffCoverage.status) as coverage',
                'gibbonPerson.title', 'gibbonPerson.preferredName', 'gibbonPerson.surname', 
                'creator.title AS titleCreator', 'creator.preferredName AS preferredNameCreator', 'creator.surname AS surnameCreator', 'gibbonStaffAbsence.gibbonPersonIDCreator',
                'coverage.title as titleCoverage', 'coverage.preferredName as preferredNameCoverage', 'coverage.surname as surnameCoverage', 'gibbonStaffCoverage.gibbonPersonIDCoverage',
            ])
            ->innerJoin('gibbonStaffAbsence', 'gibbonStaffAbsence.gibbonStaffAbsenceID=gibbonStaffAbsenceDate.gibbonStaffAbsenceID')
            ->innerJoin('gibbonStaffAbsenceType', 'gibbonStaffAbsence.gibbonStaffAbsenceTypeID=gibbonStaffAbsenceType.gibbonStaffAbsenceTypeID')
            ->leftJoin('gibbonStaffAbsenceDate AS dates', 'dates.gibbonStaffAbsenceID=gibbonStaffAbsence.gibbonStaffAbsenceID')
            ->leftJoin('gibbonStaffCoverage', 'gibbonStaffCoverage.gibbonStaffCoverageID=gibbonStaffAbsenceDate.gibbonStaffCoverageID')
            ->leftJoin('gibbonPerson', 'gibbonStaffAbsence.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson AS creator', 'gibbonStaffAbsence.gibbonPersonIDCreator=creator.gibbonPersonID')
            ->leftJoin('gibbonPerson AS coverage', 'gibbonStaffCoverage.gibbonPersonIDCoverage=coverage.gibbonPersonID')
            ->where('gibbonStaffAbsenceDate.date BETWEEN :dateStart AND :dateEnd')
            ->bindValue('dateStart', $dateStart)
            ->bindValue('dateEnd', $dateEnd);

        if ($grouped) {
            $query->cols(['COUNT(DISTINCT dates.gibbonStaffAbsenceDateID) as days', 'MIN(dates.date) as dateStart', 'MAX(dates.date) as dateEnd'])
                ->groupBy(['gibbonStaffAbsence.gibbonStaffAbsenceID']);
        } else {
            $query->cols(['1 as days', 'gibbonStaffAbsenceDate.date as dateStart', 'gibbonStaffAbsenceDate.date as dateEnd'])
                ->groupBy(['gibbonStaffAbsenceDate.gibbonStaffAbsenceDateID']);
        }

        $criteria->addFilterRules([
            'type' => function ($query, $type) {
                return $query
                    ->where('gibbonStaffAbsence.gibbonStaffAbsenceTypeID = :type')
                    ->bindValue('type', $type);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryAbsencesBySchoolYear($criteria, $gibbonSchoolYearID, $grouped = true)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffAbsence.*', 'gibbonStaffAbsenceDate.*', 'gibbonStaffAbsenceType.name as type', 'gibbonPerson.gibbonPersonID', 'gibbonPerson.title', 'gibbonPerson.preferredName', 'gibbonPerson.surname', 'creator.preferredName AS preferredNameCreator', 'creator.surname AS surnameCreator', 'MIN(gibbonStaffCoverage.status) as coverage',
            ])
            ->innerJoin('gibbonStaffAbsenceType', 'gibbonStaffAbsence.gibbonStaffAbsenceTypeID=gibbonStaffAbsenceType.gibbonStaffAbsenceTypeID')
            ->innerJoin('gibbonStaffAbsenceDate', 'gibbonStaffAbsenceDate.gibbonStaffAbsenceID=gibbonStaffAbsence.gibbonStaffAbsenceID')
            ->innerJoin('gibbonSchoolYear', 'date BETWEEN firstDay AND lastDay')
            ->leftJoin('gibbonStaffCoverage', 'gibbonStaffCoverage.gibbonStaffCoverageID=gibbonStaffAbsenceDate.gibbonStaffCoverageID')
            ->leftJoin('gibbonPerson', 'gibbonStaffAbsence.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson AS creator', 'gibbonStaffAbsence.gibbonPersonIDCreator=creator.gibbonPersonID')
            ->where('gibbonSchoolYear.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        if ($grouped) {
            $query->cols(['COUNT(*) as days', 'MIN(date) as dateStart', 'MAX(date) as dateEnd'])
                ->groupBy(['gibbonStaffAbsence.gibbonStaffAbsenceID']);
        } else {
            $query->cols(['1 as days', 'date as dateStart', 'date as dateEnd'])
                ->groupBy(['gibbonStaffAbsenceDate.gibbonStaffAbsenceDateID']);
        }

        $criteria->addFilterRules([
            'type' => function ($query, $type) {
                return $query
                    ->where('gibbonStaffAbsence.gibbonStaffAbsenceTypeID = :type')
                    ->bindValue('type', $type);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }
}
