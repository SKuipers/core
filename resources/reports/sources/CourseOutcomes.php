<?php

use Gibbon\Module\Reports\DataSource;

class CourseOutcomes extends DataSource
{
    public function getSchema()
    {
        return [
            [
                'name'      => 'Grade 1 Science',
                'nameShort' => 'GR1SCIENCE',
                //'comments'  => $this->factory->get('CourseComments')->getSchema(),
                'teachers'  => $this->getFactory()->get('ClassTeachers')->getSchema(),
                'outcomes'   => [
                    0 => [
                        'name'     => 'Understands and applies scientific concepts being studied',
                        'category' => '',
                        'values'   => [
                            1 => 'Approaching',
                            2 => 'Meeting',
                        ],
                    ],
                    1 => [
                        'name'     => 'Listening to others, and sharing ideas, thoughts, and feelings',
                        'category' => '',
                        'values'   => [
                            1 => 'Approaching',
                            2 => 'Meeting',
                        ],
                    ],
                    2 => [
                        'name'     => 'Continuing through challenges and/or difficulties',
                        'category' => '',
                        'values'   => [
                            1 => 'Approaching',
                            2 => 'Meeting',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getData($ids = [])
    {
        $data = array('gibbonStudentEnrolmentID' => $ids['gibbonStudentEnrolmentID'], 'gibbonReportID' => $ids['gibbonReportID']);
        $sql = "SELECT gibbonCourse.gibbonCourseID, gibbonCourse.gibbonCourseIDParent, gibbonCourseClass.gibbonCourseClassID, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, gibbonCourse.description, gibbonCourseClass.name as className, gibbonCourseClass.nameShort as classNameShort, gibbonCourseClassPerson.role as role, gibbonReportingCriteriaType.name as criteriaType, gibbonReportingCriteriaType.valueType as valueType, gibbonReportingCriteria.category, gibbonReportingCriteria.gibbonReportingCriteriaID as criteriaID, gibbonReportingCriteria.name as criteriaName, gibbonReportingValue.comment, gibbonReportingValue.value, gibbonScaleGrade.descriptor, gibbonReportingCycle.cycleNumber as reportNum, gibbonReportingCycle.gibbonReportingCycleID as reportID
                FROM gibbonReport
                JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonSchoolYearID=gibbonReport.gibbonSchoolYearID)
                JOIN gibbonCourseClassPerson ON (gibbonStudentEnrolment.gibbonPersonID=gibbonCourseClassPerson.gibbonPersonID)
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                LEFT JOIN gibbonReportingCriteria ON (gibbonReportingCriteria.gibbonCourseID=gibbonCourse.gibbonCourseID)
                LEFT JOIN gibbonReportingCriteriaType ON (gibbonReportingCriteriaType.gibbonReportingCriteriaTypeID=gibbonReportingCriteria.gibbonReportingCriteriaTypeID)
                LEFT JOIN gibbonReportingValue ON (
                    gibbonReportingValue.gibbonReportingCriteriaID=gibbonReportingCriteria.gibbonReportingCriteriaID
                    AND
                    (
                        (gibbonReportingCriteria.target='Per Student' AND gibbonReportingValue.gibbonPersonIDStudent=gibbonStudentEnrolment.gibbonPersonID) 
                        OR (gibbonReportingCriteria.target='Per Group' AND gibbonReportingValue.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
                    ))
                LEFT JOIN gibbonScaleGrade ON (gibbonScaleGrade.gibbonScaleID=gibbonReportingCriteriaType.gibbonScaleID AND gibbonScaleGrade.gibbonScaleGradeID=gibbonReportingValue.gibbonScaleGradeID)
                LEFT JOIN gibbonReportingCycle ON (gibbonReportingCycle.gibbonReportingCycleID=gibbonReportingCriteria.gibbonReportingCycleID)
                WHERE gibbonReport.gibbonReportID=:gibbonReportID
                AND gibbonStudentEnrolment.gibbonStudentEnrolmentID=:gibbonStudentEnrolmentID
                AND gibbonCourse.gibbonSchoolYearID=gibbonStudentEnrolment.gibbonSchoolYearID
                AND gibbonCourseClass.reportable='Y'
                AND (gibbonCourseClassPerson.role = 'Student')
                AND (valueType IS NULL OR valueType <> 'Comment' OR (valueType = 'Comment' AND gibbonReportingCriteria.gibbonReportingCycleID=gibbonReport.gibbonReportingCycleID))
                ORDER BY gibbonCourse.orderBy, gibbonCourse.nameShort, gibbonReportingCriteria.sequenceNumber";

        $result = $this->db()->select($sql, $data);

        if ($result->rowCount() > 0) {
            $groupedByCourse = $result->fetchAll();

            // Build a set of top-level courses and an array of classes/outcomes for each
            $groupedByCourse = array_reduce($groupedByCourse, function ($courses, $item) {
                $id = $item['gibbonCourseID'];
                // $reportNum = "Cycle ".($item['reportNum'] ?? '1');
                $reportNum = ($item['reportNum'] ?? '1');

                $courses[$id]['name'] = $item['courseName'];
                $courses[$id]['nameShort'] = $item['courseNameShort'];
                $courses[$id]['gibbonCourseID'] = $item['gibbonCourseID'];
                $courses[$id]['gibbonCourseClassID'] = $item['gibbonCourseClassID'];
                $courses[$id]['role'] = $item['role'];
                $courses[$id]['reportID'] = !empty($item['reportID'])? $item['reportID'] : ($courses[$id]['reportID'] ?? '');

                if ($item['criteriaType'] == 'Elementary Indicator' || $item['criteriaType'] == 'Kindergarten Indicator' || $item['criteriaType'] == 'Preschool Skills' || $item['criteriaType'] == 'Kindergarten Skills' || $item['criteriaType'] == 'Elementary Skills'  || $item['criteriaType'] == 'ELL Skills') {
                    $courses[$id]['outcomes'][$item['criteriaName']]['name'] = $item['criteriaName'];
                    $courses[$id]['outcomes'][$item['criteriaName']]['category'] = $item['category'];
                    $courses[$id]['outcomes'][$item['criteriaName']]['values'][$reportNum] = $item['descriptor'];
                } elseif ($item['valueType'] != 'Remark') {
                    $courses[$id]['criteria'][$item['criteriaID']] = [
                        'name'         => $item['criteriaName'],
                        'criteriaType' => $item['criteriaType'],
                        'valueType'    => $item['valueType'],
                        'value'        => $item['value'],
                        'comment'      => $item['comment'],
                    ];
                }
                
                
                return $courses;
            }, array());

            // Get the course data and nested data sources
            foreach ($groupedByCourse as $gibbonCourseID => &$course) {
                $ids['gibbonCourseID'] = $gibbonCourseID;
                $ids['gibbonCourseClassID'] = $course['gibbonCourseClassID'];
                $ids['reportID'] = $course['reportID']; // Added for secondary comments to work

                $course['teachers'] = $this->getFactory()->get('ClassTeachers')->getData($ids);
                //$course['comments'] = $this->factory->get('CourseComments')->getData($ids);

                $values[] = $course;
            }
        }

        return $groupedByCourse;
    }
}
