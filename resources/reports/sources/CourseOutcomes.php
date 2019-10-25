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
                //'teachers'  => $this->factory->get('ClassTeachers')->getSchema(),
                'outcomes'   => [
                    [
                        'criteriaName' => 'Understands and applies scientific concepts being studied',
                        'grades'      => [
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
        $data = array('gibbonStudentEnrolmentID' => $ids['gibbonStudentEnrolmentID']);
        $sql = "SELECT gibbonCourse.gibbonCourseID, gibbonCourse.gibbonCourseIDParent, gibbonCourseClass.gibbonCourseClassID, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, gibbonCourse.description, gibbonCourseClass.name as className, gibbonCourseClass.nameShort as classNameShort, gibbonCourseClassPerson.role as role, gibbonReportingCriteriaType.name as criteriaType,gibbonReportingCriteria.name as criteriaName, gibbonReportingValue.value as gradeID, gibbonScaleGrade.descriptor, gibbonReportingCycle.cycleNumber as reportNum, gibbonReportingCycle.gibbonReportingCycleID as reportID
                FROM gibbonStudentEnrolment
                JOIN gibbonCourseClassPerson ON (gibbonStudentEnrolment.gibbonPersonID=gibbonCourseClassPerson.gibbonPersonID)
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                LEFT JOIN gibbonReportingCriteria ON (gibbonReportingCriteria.gibbonCourseID=gibbonCourse.gibbonCourseID)
                LEFT JOIN gibbonReportingCriteriaType ON (gibbonReportingCriteriaType.gibbonReportingCriteriaTypeID=gibbonReportingCriteria.gibbonReportingCriteriaTypeID)
                LEFT JOIN gibbonReportingValue ON (gibbonReportingValue.gibbonPersonIDStudent=gibbonStudentEnrolment.gibbonPersonID AND gibbonReportingValue.gibbonReportingCriteriaID=gibbonReportingCriteria.gibbonReportingCriteriaID)
                LEFT JOIN gibbonScaleGrade ON (gibbonScaleGrade.gibbonScaleID=gibbonReportingCriteriaType.gibbonScaleID AND gibbonScaleGrade.gibbonScaleGradeID=gibbonReportingValue.gibbonScaleGradeID)
                LEFT JOIN gibbonReportingCycle ON (gibbonReportingCycle.gibbonReportingCycleID=gibbonReportingValue.gibbonReportingCycleID)
                WHERE gibbonStudentEnrolment.gibbonStudentEnrolmentID=:gibbonStudentEnrolmentID
                AND gibbonCourse.gibbonSchoolYearID=gibbonStudentEnrolment.gibbonSchoolYearID
                AND gibbonReportingCriteriaType.name='Elementary Indicator'
                AND gibbonCourseClass.reportable='Y'
                AND (gibbonCourseClassPerson.role = 'Student' OR gibbonCourseClassPerson.role = 'Student - Left')
                ORDER BY gibbonCourse.orderBy, gibbonCourse.nameShort, gibbonReportingCriteria.sequenceNumber";

        $result = $this->db()->select($sql, $data);

        if ($result->rowCount() > 0) {
            $groupedByCourse = $result->fetchAll();

            // Build a set of top-level courses and an array of classes/outcomes for each
            $groupedByCourse = array_reduce($groupedByCourse, function ($courses, $item) {
                $id = $item['gibbonCourseID'];
                $reportNum = "Cycle ".($item['reportNum'] ?? '1');

                $courses[$id]['name'] = $item['courseName'];
                $courses[$id]['nameShort'] = $item['courseNameShort'];
                $courses[$id]['gibbonCourseID'] = $item['gibbonCourseID'];
                $courses[$id]['gibbonCourseClassID'] = $item['gibbonCourseClassID'];
                $courses[$id]['role'] = $item['role'];
                $courses[$id]['reportID'] = !empty($item['reportID'])? $item['reportID'] : ($courses[$id]['reportID'] ?? '');
                if ($item['criteriaType'] == 1 || $item['criteriaType'] == 2 || $item['criteriaType'] == 4) {
                    $courses[$id]['outcomes'][$item['criteriaName']][$reportNum] = !empty($item['gradeID'])? $item['gradeID'].'%' : 'N/A';
                } elseif ($item['criteriaType'] == 6) {
                    $courses[$id]['outcomes'][$item['criteriaName']][$reportNum] = !empty($item['gradeID'] && $item['gradeID'] == 'Y')? 'Yes' : '';
                } else {
                    $courses[$id]['outcomes'][$item['criteriaName']][$reportNum] = $item['descriptor'];
                }
                
                return $courses;
            }, array());

            // Get the course data and nested data sources
            foreach ($groupedByCourse as $gibbonCourseID => &$course) {
                $ids['gibbonCourseID'] = $gibbonCourseID;
                $ids['gibbonCourseClassID'] = $course['gibbonCourseClassID'];
                $ids['reportID'] = $course['reportID']; // Added for secondary comments to work

                //$course['teachers'] = $this->factory->get('ClassTeachers')->getData($ids);
                //$course['comments'] = $this->factory->get('CourseComments')->getData($ids);

                $values[] = $course;
            }
        }

        return $groupedByCourse;
    }
}
