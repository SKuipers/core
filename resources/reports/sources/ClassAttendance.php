<?php

use Gibbon\Module\Reports\DataSource;

class ClassAttendance extends DataSource
{
    protected $gibbonPersonID;
    protected $data;

    public function getSchema()
    {
        return [
            'absent' => 0,
            'late' => 0,
        ];
    }

    public function getData($ids = [])
    {
        if ($this->isCacheValid($ids) == false) {
            $this->data = $this->getAllData($ids);
            $this->gibbonPersonID = $ids['gibbonPersonID'];
        }

        return (isset($this->data[$ids['gibbonCourseClassID']]))? $this->data[$ids['gibbonCourseClassID']] : array();
    }

    protected function isCacheValid($ids)
    {
        return isset($this->data) && $this->gibbonPersonID == $ids['gibbonPersonID'];
    }

    protected function getAllData($ids = [])
    {
        $data = array(
            'gibbonPersonID'     => $ids['gibbonPersonID'],
            'gibbonSchoolYearID' => $ids['gibbonSchoolYearID'],
            'reportID'           => $ids['reportID'],
        );
        $sql = "SELECT gibbonAttendanceLogPerson.gibbonCourseClassID, gibbonAttendanceLogPerson.date, gibbonAttendanceLogPerson.type
                FROM gibbonAttendanceLogPerson
                JOIN gibbonSchoolYear ON (gibbonSchoolYear.gibbonSchoolYearID=:gibbonSchoolYearID)
                JOIN arrReport ON (arrReport.reportID=:reportID)
                WHERE gibbonAttendanceLogPerson.gibbonPersonID=:gibbonPersonID
                AND gibbonAttendanceLogPerson.gibbonCourseClassID>0
                AND gibbonAttendanceLogPerson.date>=gibbonSchoolYear.firstDay
                AND gibbonAttendanceLogPerson.date<=arrReport.endDate
                AND gibbonAttendanceLogPerson.date<=CURDATE()
                ORDER BY gibbonAttendanceLogPerson.date, gibbonAttendanceLogPerson.timestampTaken
                ";

        $result = $this->pdo->executeQuery($data, $sql);

        $values = ($result->rowCount() > 0)? $result->fetchAll(): array();

        // Group by class, then date
        $attendance = array_reduce($values, function($carry, $item) {
            $carry[$item['gibbonCourseClassID']][$item['date']][] = $item['type'];
            return $carry;
        }, array());

        $attendance = array_map(function($dates) {
            // Filter to end of class only
            $endOfClass = array_map(function($logs) { 
                return end($logs); 
            }, $dates);

            // Count up the absenses and lates
            $counts = array_reduce($endOfClass, function($carry, $item) {
                if ($item == 'Absent - Excused' || $item == 'Absent - Unexcused') $carry['absent']++;
                if ($item == 'Present - Late') $carry['late']++;
                return $carry;
            }, array('absent' => 0, 'late' => 0));

            return $counts;
        }, $attendance);

        return $attendance;
    }
}
