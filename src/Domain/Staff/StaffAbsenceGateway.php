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
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAbsencesByPerson(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffAbsence.gibbonStaffAbsenceID', 'gibbonStaffAbsence.gibbonPersonID', 'gibbonStaffAbsenceType.name as type', 'reason', 'comment', 'date', 'COUNT(*) as days', 'allDay', 'timestampStart', 'timestampEnd', 'timestampCreator', 'gibbonStaffAbsence.gibbonPersonIDCreator', 'creator.preferredName AS preferredNameCreator', 'creator.surname AS surnameCreator', 
            ])
            ->innerJoin('gibbonStaffAbsenceType', 'gibbonStaffAbsence.gibbonStaffAbsenceTypeID=gibbonStaffAbsenceType.gibbonStaffAbsenceTypeID')
            ->leftJoin('gibbonPerson AS creator', 'gibbonStaffAbsence.gibbonPersonIDCreator=creator.gibbonPersonID')
            ->where('gibbonStaffAbsence.gibbonPersonID = :gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->groupBy(['timestampStart', 'timestampEnd']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAbsencesByDateRange(QueryCriteria $criteria, $dateStart, $dateEnd)
    {
        if (empty($dateEnd)) $dateEnd = $dateStart;
        
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffAbsence.gibbonStaffAbsenceID', 'gibbonStaffAbsence.gibbonPersonID', 'gibbonStaffAbsenceType.name as type', 'reason', 'comment', 'date', '1 as days', 'allDay', 'timestampStart', 'timestampEnd', 'timestampCreator', 'gibbonStaffAbsence.gibbonPersonIDCreator', 'gibbonPerson.title', 'gibbonPerson.preferredName', 'gibbonPerson.surname', 'creator.preferredName AS preferredNameCreator', 'creator.surname AS surnameCreator', 
            ])
            ->innerJoin('gibbonStaffAbsenceType', 'gibbonStaffAbsence.gibbonStaffAbsenceTypeID=gibbonStaffAbsenceType.gibbonStaffAbsenceTypeID')
            ->leftJoin('gibbonPerson', 'gibbonStaffAbsence.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson AS creator', 'gibbonStaffAbsence.gibbonPersonIDCreator=creator.gibbonPersonID')
            ->where('gibbonStaffAbsence.date BETWEEN :dateStart AND :dateEnd')
            ->bindValue('dateStart', $dateStart)
            ->bindValue('dateEnd', $dateEnd);

        $criteria->addFilterRules([
            'type' => function ($query, $type) {
                return $query
                    ->where('gibbonStaffAbsence.gibbonStaffAbsenceTypeID = :type')
                    ->bindValue('type', $type);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryAbsencesBySchoolYear($criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffAbsence.*',
            ])
            ->innerJoin('gibbonSchoolYear', 'date BETWEEN firstDay AND lastDay')
            ->where('gibbonSchoolYear.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

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
