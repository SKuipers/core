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
                    'gpa'       => '70',
                    'status'    => 'Good Standing',
                ],
                2 => [
                    'gpa'       => '70',
                    'status'    => 'Good Standing',
                ],
                3 => [
                    'gpa'       => '70',
                    'status'    => 'Good Standing',
                ],
                4 => [
                    'gpa'       => '70',
                    'status'    => 'Good Standing',
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
        $sql = "SELECT gibbonReportingCycle.cycleNumber, gibbonReportingCycle.gibbonReportingCycleID, gibbonReportingValue.gibbonCourseClassID, gibbonReportingCriteriaType.name as criteriaType, gibbonReportingCriteriaType.valueType, gibbonReportingCriteria.name as criteriaName, gibbonReportingValue.value as gradeID, gibbonScaleGrade.descriptor, gibbonScaleGrade.value, gibbonCourse.weight, gibbonCourse.gibbonCourseID, gibbonReportingCriteriaType.gibbonScaleID as gradesetID
                FROM gibbonStudentEnrolment
                JOIN gibbonReportingCycle ON (gibbonReportingCycle.gibbonSchoolYearID=gibbonStudentEnrolment.gibbonSchoolYearID)
                LEFT JOIN gibbonReportingValue ON (gibbonReportingValue.gibbonPersonIDStudent=gibbonStudentEnrolment.gibbonPersonID AND gibbonReportingValue.gibbonReportingCycleID=gibbonReportingCycle.gibbonReportingCycleID)
                LEFT JOIN gibbonReportingCriteria ON (gibbonReportingCriteria.gibbonReportingCriteriaID=gibbonReportingValue.gibbonReportingCriteriaID)
                LEFT JOIN gibbonReportingCriteriaType ON (gibbonReportingCriteriaType.gibbonReportingCriteriaTypeID=gibbonReportingCriteria.gibbonReportingCriteriaTypeID)
                LEFT JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonReportingCriteria.gibbonCourseID)
                LEFT JOIN gibbonScaleGrade ON (gibbonScaleGrade.gibbonScaleID=gibbonReportingCriteriaType.gibbonScaleID AND      
                    gibbonScaleGrade.gibbonScaleGradeID=gibbonReportingValue.gibbonScaleGradeID)
                WHERE gibbonStudentEnrolment.gibbonStudentEnrolmentID=:gibbonStudentEnrolmentID
                AND FIND_IN_SET(gibbonStudentEnrolment.gibbonYearGroupID, gibbonReportingCycle.gibbonYearGroupIDList)
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

                if ($item['criteriaType'] == 2) { // Term Grade
                    $values[$class][$report]['grade'] = ($item['gradesetID'] == 1 || $item['gradesetID'] == 4)? $item['gradeID'] : $item['value']; 
                } else if ($item['criteriaType'] == 7) { // Percent Grade
                    $values[$class][$report]['percent'] = $item['gradeID']; 
                } else if ($item['criteriaType'] == 1) { // Final Exam
                    $values[$class][$report]['exam'] = $item['gradeID']; 
                } else if ($item['criteriaType'] == 4) { // Final Grade
                    $values[$class][$report]['final'] = ($item['gradesetID'] == 1 || $item['gradesetID'] == 4)? $item['gradeID'] : $item['value'];
                } else if ($item['criteriaType'] == 10) { // Final Percent
                    $values[$class][$report]['finalPercent'] = $item['gradeID'];
                } else if ($item['criteriaType'] == 'Secondary Effort') { // Effort
                    $values[$class][$report]['effort'] = array(
                        'value' => $item['value'],
                        'descriptor' => $item['descriptor'],
                    ); 
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
