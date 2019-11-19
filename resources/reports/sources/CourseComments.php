<?php

use Gibbon\Module\Reports\DataSource;

class CourseComments extends DataSource
{
    public function getSchema()
    {
        return [
            'classNotes'      => 'The first units focused on photosynthesis and cellular respiration, as well as the human body system. Students explored the biochemistry behind photosynthesis and cellular respiration. Additionally, they investigated the human digestive system, circulatory system, motor system, respiratory system, and the excretory system.',
        ];
    }

    public function getData($ids = [])
    {
        $data = ['gibbonReportingCycleID' => $ids['gibbonReportingCycleID'], 'gibbonCourseID' => $ids['gibbonCourseID']];
        $sql = "SELECT gibbonReportingCriteria.name as criteriaName, gibbonReportingCriteria.description as criteriaDescription, gibbonReportingValue.value, gibbonReportingValue.comment, gibbonReportingCriteriaType.valueType
                FROM gibbonReportingCriteria 
                JOIN gibbonReportingValue ON (gibbonReportingCriteria.gibbonReportingCriteriaID=gibbonReportingValue.gibbonReportingCriteriaID)
                JOIN gibbonReportingCriteriaType ON (gibbonReportingCriteriaType.gibbonReportingCriteriaTypeID=gibbonReportingCriteria.gibbonReportingCriteriaTypeID)
                JOIN gibbonReportingScope ON (gibbonReportingScope.gibbonReportingScopeID=gibbonReportingCriteria.gibbonReportingScopeID)
                WHERE gibbonReportingValue.gibbonReportingCycleID=:gibbonReportingCycleID
                AND gibbonReportingScope.scopeType='Course'
                AND gibbonReportingCriteria.target='Per Group'
                AND gibbonReportingCriteria.gibbonCourseID=:gibbonCourseID
                ORDER BY gibbonReportingScope.sequenceNumber, gibbonReportingCriteria.sequenceNumber";

        return $this->db()->select($sql, $data)->fetch();
    }
}
