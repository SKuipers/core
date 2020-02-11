<?php

use Gibbon\Module\Reports\DataSource;

class CourseComments extends DataSource
{
    public function getSchema()
    {
        return [
            'criteriaName'        => 'Course Description',
            'criteriaDescription' => ['sentence'],
            'value'               => ['randomDigit'],
            'comment'             => ['paragraph', 6],
            'valueType'           => 'Comment',
        ];
    }

    public function getData($ids = [])
    {
        $data = ['gibbonReportingCycleID' => $ids['gibbonReportingCycleID'], 'gibbonCourseID' => $ids['gibbonCourseID'], 'gibbonCourseClassID' => $ids['gibbonCourseClassID']];
        $sql = "SELECT gibbonReportingCriteria.name as criteriaName, gibbonReportingCriteria.description as criteriaDescription, gibbonReportingValue.value, gibbonReportingValue.comment, gibbonReportingCriteriaType.valueType
                FROM gibbonReportingCriteria 
                JOIN gibbonReportingValue ON (gibbonReportingCriteria.gibbonReportingCriteriaID=gibbonReportingValue.gibbonReportingCriteriaID)
                JOIN gibbonReportingCriteriaType ON (gibbonReportingCriteriaType.gibbonReportingCriteriaTypeID=gibbonReportingCriteria.gibbonReportingCriteriaTypeID)
                JOIN gibbonReportingScope ON (gibbonReportingScope.gibbonReportingScopeID=gibbonReportingCriteria.gibbonReportingScopeID)
                WHERE gibbonReportingValue.gibbonReportingCycleID=:gibbonReportingCycleID
                AND gibbonReportingScope.scopeType='Course'
                AND gibbonReportingCriteria.target='Per Group'
                AND gibbonReportingCriteria.gibbonCourseID=:gibbonCourseID
                AND gibbonReportingValue.gibbonCourseClassID=:gibbonCourseClassID
                ORDER BY gibbonReportingScope.sequenceNumber, gibbonReportingCriteria.sequenceNumber";

        return $this->db()->select($sql, $data)->fetch();
    }
}
