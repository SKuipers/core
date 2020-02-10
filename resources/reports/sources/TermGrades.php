<?php

use Gibbon\Module\Reports\DataSource;

class TermGrades extends DataSource
{
    protected $gibbonStudentEnrolmentID;
    protected $data;

    public function getSchema()
    {
        return [
            'terms' => [
                1 => [
                    'effort'    => [
                        'value' => 'VG',
                        'descriptor' => 'Very Good',
                    ],
                    'comment'    => [
                        'value' => ['paragraph', 6],
                    ],
                ],
                2 => [
                    'effort'    => [
                        'value' => 'VG',
                        'descriptor' => 'Very Good',
                    ],
                    'comment'    => [
                        'value' => ['paragraph', 6],
                    ],
                ],
                3 => [
                    'effort'    => [
                        'value' => 'VG',
                        'descriptor' => 'Very Good',
                    ],
                    'comment'    => [
                        'value' => ['paragraph', 6],
                    ],
                ],
                4 => [
                    'effort'    => [
                        'value' => 'VG',
                        'descriptor' => 'Very Good',
                    ],
                    'comment'    => [
                        'value' => ['paragraph', 6],
                    ],
                ],
            ],
        ];
    }

    public function getData($ids = [])
    {
        if ($this->isCacheValid($ids) == false) {
            $this->data = $this->getAllData($ids);
            $this->gibbonStudentEnrolmentID = $ids['gibbonStudentEnrolmentID'];
        }

        return (isset($this->data))? $this->data : array();
    }

    protected function isCacheValid($ids)
    {
        return isset($this->data) && $this->gibbonStudentEnrolmentID == $ids['gibbonStudentEnrolmentID'];
    }

    protected function getAllData($ids = [])
    {
        $data = array('gibbonStudentEnrolmentID' => $ids['gibbonStudentEnrolmentID']);
        $sql = "SELECT gibbonReportingCycle.cycleNumber, gibbonReportingCycle.gibbonReportingCycleID, gibbonReportingValue.gibbonCourseClassID, gibbonReportingCriteriaType.name as criteriaType, gibbonReportingCriteriaType.valueType, gibbonReportingCriteria.category, gibbonReportingCriteria.name as criteriaName, gibbonReportingValue.value as gradeID, gibbonReportingValue.comment,  gibbonScaleGrade.descriptor, gibbonScaleGrade.value, gibbonCourse.weight, gibbonCourse.gibbonCourseID, gibbonReportingCriteriaType.gibbonScaleID as gradesetID
                FROM gibbonStudentEnrolment
                JOIN gibbonCourseClassPerson ON (gibbonStudentEnrolment.gibbonPersonID=gibbonCourseClassPerson.gibbonPersonID)
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID AND gibbonCourse.gibbonSchoolYearID=gibbonStudentEnrolment.gibbonSchoolYearID )
                JOIN gibbonReportingCycle ON (gibbonReportingCycle.gibbonSchoolYearID=gibbonCourse.gibbonSchoolYearID)
                JOIN gibbonReportingCriteria ON (gibbonReportingCriteria.gibbonReportingCycleID=gibbonReportingCycle.gibbonReportingCycleID AND gibbonCourse.gibbonCourseID=gibbonReportingCriteria.gibbonCourseID)
                LEFT JOIN gibbonReportingValue ON (gibbonReportingValue.gibbonPersonIDStudent=gibbonStudentEnrolment.gibbonPersonID AND gibbonReportingCriteria.gibbonReportingCriteriaID=gibbonReportingValue.gibbonReportingCriteriaID)
                LEFT JOIN gibbonReportingCriteriaType ON (gibbonReportingCriteriaType.gibbonReportingCriteriaTypeID=gibbonReportingCriteria.gibbonReportingCriteriaTypeID)
                LEFT JOIN gibbonScaleGrade ON (gibbonScaleGrade.gibbonScaleID=gibbonReportingCriteriaType.gibbonScaleID AND      
                    gibbonScaleGrade.gibbonScaleGradeID=gibbonReportingValue.gibbonScaleGradeID)
                WHERE gibbonStudentEnrolment.gibbonStudentEnrolmentID=:gibbonStudentEnrolmentID
                AND FIND_IN_SET(gibbonStudentEnrolment.gibbonYearGroupID, gibbonReportingCycle.gibbonYearGroupIDList)
                AND gibbonReportingCriteria.target='Per Student'
                ORDER BY gibbonReportingCycle.cycleNumber";

        $result = $this->db()->executeQuery($data, $sql);
        $values = array();
        $reports = array();

        if ($result->rowCount() > 0) {
            while ($item = $result->fetch()) {
                $report = $item['cycleNumber'];
                $class = $item['gibbonCourseID'];

                $reports[$report] = $item['gibbonReportingCycleID'];
                $values[$class][$report]['weight'] = $item['weight'];

                if ($item['criteriaType'] == 'Secondary Effort') { // Effort
                    $values[$class][$report]['effort'] = array(
                        'value' => $item['value'],
                        'descriptor' => $item['descriptor'],
                    ); 
                } else if ($item['valueType'] == 'Comment') { // Effort
                    $values[$class][$report]['comment'] = array(
                        'value' => $item['comment'],
                        'descriptor' => $item['criteriaName'],
                    ); 
                } elseif (!empty($item['category'])) {
                    $category = $item['category'];
                    $criteria = $item['criteriaName'];
                    $values[$class][$category][$criteria][$report] = !empty($item['descriptor'])? $item['descriptor'] : $item['gradeID'];
                } else {
                    $criteria = strtolower($item['criteriaName']);
                    $values[$class][$report][$criteria] = array(
                        'value' => !empty($item['value'])? $item['value'] : $item['gradeID'],
                        'descriptor' => !empty($item['descriptor'])? $item['descriptor'] : $item['gradeID'],
                    );
                }
            }
        }

        return array('classGrades' => $values, 'reports' => $reports);
    }
}
