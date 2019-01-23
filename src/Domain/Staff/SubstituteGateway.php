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

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\Traits\TableQueryAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Substitute Gateway
 *
 * @version v18
 * @since   v18
 */
class SubstituteGateway extends QueryableGateway
{
    use TableAware;
    use TableQueryAware;

    private static $tableName = 'gibbonSubstitute';
    private static $primaryKey = 'gibbonSubstituteID';

    private static $searchableColumns = ['preferredName', 'surname', 'username'];
    
    /**
     * Queries the list of users for the Manage Substitutes page.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAllSubstitutes(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonSubstitute.gibbonSubstituteID', 'gibbonSubstitute.type', 'gibbonSubstitute.details', 'gibbonSubstitute.priority', 'gibbonSubstitute.active',
                'gibbonPerson.gibbonPersonID', 'gibbonPerson.title', 'gibbonPerson.surname', 'gibbonPerson.preferredName', 'gibbonPerson.status', 'gibbonPerson.image_240',
                
            ])
            ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=gibbonSubstitute.gibbonPersonID');

        $criteria->addFilterRules([
            'active' => function ($query, $active) {
                return $query
                    ->where('gibbonSubstitute.active = :active')
                    ->bindValue('active', $active);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonPerson.status = :status')
                    ->bindValue('status', ucfirst($status));
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryUnavailableDatesBySub(QueryCriteria $criteria, $gibbonPersonIDCoverage)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonSubstituteUnavailable')
            ->cols([
                'date as groupBy', 'gibbonSubstituteUnavailable.*',
            ])
            ->where('gibbonSubstituteUnavailable.gibbonPersonID = :gibbonPersonIDCoverage')
            ->bindValue('gibbonPersonIDCoverage', $gibbonPersonIDCoverage);

        return $this->runQuery($query, $criteria);
    }

    public function selectAvailableSubsByDate($date, $timeStart = null, $timeEnd = null)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonSubstitute')
            ->cols([
                'gibbonPerson.gibbonPersonID as groupBy', 'gibbonSubstitute.*', 'gibbonPerson.title', 'gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonPerson.status', 'gibbonPerson.image_240', 'timetable.ID'
            ])
            ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=gibbonSubstitute.gibbonPersonID');

        if (!empty($timeStart) && !empty($timeEnd)) {
            $query->bindValue('timeStart', $timeStart)
                  ->bindValue('timeEnd', $timeEnd);

            $query->leftJoin('gibbonSubstituteUnavailable as unavailable', "unavailable.gibbonPersonID=gibbonSubstitute.gibbonPersonID AND unavailable.date = :date 
                    AND (unavailable.allDay='Y' OR (unavailable.allDay='N' AND unavailable.timeStart <= :timeEnd AND unavailable.timeEnd >= :timeStart))");

            $query->joinSubSelect(
                'LEFT',
                "SELECT gibbonStaffAbsenceDateID, gibbonStaffCoverage.gibbonPersonIDCoverage, gibbonStaffAbsenceDate.date, allDay, timeStart, timeEnd
                    FROM gibbonStaffCoverage 
                    JOIN gibbonStaffAbsenceDate ON (gibbonStaffAbsenceDate.gibbonStaffCoverageID=gibbonStaffCoverage.gibbonStaffCoverageID)",
                'coverage',
                "coverage.gibbonPersonIDCoverage=gibbonSubstitute.gibbonPersonID AND coverage.date = :date 
                    AND (coverage.allDay='Y' OR (coverage.allDay='N' AND coverage.timeStart <= :timeEnd AND coverage.timeEnd >= :timeStart))"
            );

            $query->joinSubSelect(
                'LEFT',
                "SELECT gibbonStaffAbsenceDateID, gibbonStaffAbsence.gibbonPersonID, gibbonStaffAbsenceDate.date, allDay, timeStart, timeEnd
                    FROM gibbonStaffAbsence 
                    JOIN gibbonStaffAbsenceDate ON (gibbonStaffAbsenceDate.gibbonStaffAbsenceID=gibbonStaffAbsence.gibbonStaffAbsenceID)",
                'absence',
                "absence.gibbonPersonID=gibbonSubstitute.gibbonPersonID AND absence.date = :date 
                    AND (absence.allDay='Y' OR (absence.allDay='N' AND absence.timeStart <= :timeEnd AND absence.timeEnd >= :timeStart))"
            );

            $query->joinSubSelect(
                'LEFT',
                "SELECT gibbonTTColumnRow.gibbonTTColumnRowID as ID, gibbonCourseClassPerson.gibbonPersonID, gibbonTTDayDate.date, timeStart, timeEnd
                    FROM gibbonCourseClassPerson 
                    JOIN gibbonTTDayRowClass ON (gibbonTTDayRowClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID)
                    JOIN gibbonTTDayDate ON (gibbonTTDayDate.gibbonTTDayID=gibbonTTDayRowClass.gibbonTTDayID)
                    JOIN gibbonTTDay ON (gibbonTTDay.gibbonTTDayID=gibbonTTDayDate.gibbonTTDayID)
                    JOIN gibbonTTColumnRow ON (gibbonTTColumnRow.gibbonTTColumnRowID=gibbonTTDayRowClass.gibbonTTColumnRowID 
                        AND gibbonTTDay.gibbonTTColumnID=gibbonTTColumnRow.gibbonTTColumnID)
                    WHERE gibbonCourseClassPerson.role = 'Teacher'",
                'timetable',
                "timetable.gibbonPersonID=gibbonSubstitute.gibbonPersonID AND timetable.date = :date 
                    AND timetable.timeStart <= :timeEnd AND timetable.timeEnd >= :timeStart"
            );
        } else {
            $query->leftJoin('gibbonSubstituteUnavailable as unavailable', 'unavailable.date = :date 
                AND unavailable.gibbonPersonID=gibbonSubstitute.gibbonPersonID');

            $query->joinSubSelect(
                'LEFT',
                "SELECT gibbonStaffAbsenceDateID, gibbonStaffCoverage.gibbonPersonIDCoverage, gibbonStaffAbsenceDate.date
                    FROM gibbonStaffCoverage 
                    JOIN gibbonStaffAbsenceDate ON (gibbonStaffAbsenceDate.gibbonStaffCoverageID=gibbonStaffCoverage.gibbonStaffCoverageID)",
                'coverage',
                'coverage.gibbonPersonIDCoverage=gibbonSubstitute.gibbonPersonID AND coverage.date = :date'
            );

            $query->joinSubSelect(
                'LEFT',
                "SELECT gibbonStaffAbsenceDateID, gibbonStaffAbsence.gibbonPersonID, gibbonStaffAbsenceDate.date
                    FROM gibbonStaffAbsence 
                    JOIN gibbonStaffAbsenceDate ON (gibbonStaffAbsenceDate.gibbonStaffAbsenceID=gibbonStaffAbsence.gibbonStaffAbsenceID)",
                'absence',
                'absence.gibbonPersonID=gibbonSubstitute.gibbonPersonID AND absence.date = :date'
            );

            $query->joinSubSelect(
                'LEFT',
                "SELECT gibbonTTDayDate.gibbonTTDayDateID as ID, gibbonCourseClassPerson.gibbonPersonID, gibbonTTDayDate.date
                    FROM gibbonCourseClassPerson 
                    JOIN gibbonTTDayRowClass ON (gibbonTTDayRowClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID)
                    JOIN gibbonTTDayDate ON (gibbonTTDayDate.gibbonTTDayID=gibbonTTDayRowClass.gibbonTTDayID)
                    WHERE gibbonCourseClassPerson.role = 'Teacher'",
                'timetable',
                'timetable.gibbonPersonID=gibbonSubstitute.gibbonPersonID AND timetable.date = :date'
            );
        }

        $query
            ->where("gibbonSubstitute.active='Y'")
            ->where("gibbonPerson.status='Full'")
            ->where('absence.gibbonStaffAbsenceDateID IS NULL')
            ->where('coverage.gibbonStaffAbsenceDateID IS NULL')
            ->where('unavailable.gibbonSubstituteUnavailableID IS NULL')
            ->where('timetable.ID IS NULL')
            ->bindValue('date', $date)
            ->orderBy(['gibbonSubstitute.priority DESC', 'surname', 'preferredName']);
        
        return $this->runSelect($query);
    }

    public function selectUnavailableDatesBySub($gibbonPersonID, $gibbonStaffCoverageIDExclude = null)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'gibbonStaffCoverageIDExclude' => $gibbonStaffCoverageIDExclude];
        $sql = "(
                SELECT date as groupBy, 'Not Available' as status, allDay, timeStart, timeEnd
                FROM gibbonSubstituteUnavailable 
                WHERE gibbonSubstituteUnavailable.gibbonPersonID=:gibbonPersonID 
                ORDER BY DATE
            ) UNION ALL (
                SELECT date as groupBy, 'Already Booked' as status, allDay, timeStart, timeEnd
                FROM gibbonStaffCoverage
                JOIN gibbonStaffAbsenceDate ON (gibbonStaffAbsenceDate.gibbonStaffCoverageID=gibbonStaffCoverage.gibbonStaffCoverageID)
                WHERE gibbonStaffCoverage.gibbonPersonIDCoverage=:gibbonPersonID 
                AND (gibbonStaffCoverage.status='Accepted' OR gibbonStaffCoverage.status='Requested')
                AND gibbonStaffCoverage.gibbonStaffCoverageID <> :gibbonStaffCoverageIDExclude
            ) UNION ALL (
                SELECT date as groupBy, 'Absent' as status, allDay, timeStart, timeEnd
                FROM gibbonStaffAbsence
                JOIN gibbonStaffAbsenceDate ON (gibbonStaffAbsenceDate.gibbonStaffAbsenceID=gibbonStaffAbsence.gibbonStaffAbsenceID)
                WHERE gibbonStaffAbsence.gibbonPersonID=:gibbonPersonID 
            )";

        return $this->db()->select($sql, $data);
    }

    public function insertUnavailability($data)
    {
        $query = $this
            ->newInsert()
            ->into('gibbonSubstituteUnavailable')
            ->cols($data);

        return $this->runInsert($query);
    }

    public function deleteUnavailability($gibbonSubstituteUnavailableID)
    {
        $query = $this
            ->newDelete()
            ->from('gibbonSubstituteUnavailable')
            ->where('gibbonSubstituteUnavailableID=:gibbonSubstituteUnavailableID')
            ->bindValue('gibbonSubstituteUnavailableID', $gibbonSubstituteUnavailableID);

        return $this->runDelete($query);
    }
}
