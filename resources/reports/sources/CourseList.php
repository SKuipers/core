<?php

use Gibbon\Module\Reports\DataSource;

class CourseList extends DataSource
{
    public function getSchema()
    {
        return [
            [
                'name'      => 'Biology',
                'nameShort' => 'SCN2231',
                'comments'  => $this->getFactory()->get('CourseComments')->getSchema(),
                'teachers'  => $this->getFactory()->get('ClassTeachers')->getSchema(),
                'classes'   => [
                    [
                        'courseName'      => 'Biology 20',
                        'courseNameShort' => 'SCN2231',
                        'className'       => '1',
                        'classNameShort'  => '1',
                        'attendance'      => $this->getFactory()->get('ClassAttendance')->getSchema(),
                        'grades'          => $this->getFactory()->get('ClassGrades')->getSchema(),
                    ],
                ],
            ],
        ];
    }

    public function getData($ids = [])
    {
        $data = array('gibbonStudentEnrolmentID' => $ids['gibbonStudentEnrolmentID'], 'gibbonReportingCycleID' => $ids['gibbonReportingCycleID']);
        $sql = "SELECT gibbonCourse.gibbonCourseID AS groupBy, gibbonCourse.gibbonCourseID, gibbonCourse.gibbonCourseIDParent, gibbonCourseClass.gibbonCourseClassID, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, gibbonCourse.description, gibbonCourseClass.name as className, gibbonCourseClass.nameShort as classNameShort, gibbonCourseClassPerson.role as role, (gibbonCourse.gibbonYearGroupIDList LIKE '%014%' OR gibbonCourse.gibbonYearGroupIDList LIKE '%015%' OR gibbonCourse.gibbonYearGroupIDList LIKE '%016%') as hasCredits, gibbonCourse.credits, gibbonReportingCycle.cycleNumber
                FROM gibbonStudentEnrolment
                JOIN gibbonCourseClassPerson ON (gibbonStudentEnrolment.gibbonPersonID=gibbonCourseClassPerson.gibbonPersonID)
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                JOIN gibbonReportingCycle ON (gibbonReportingCycle.gibbonSchoolYearID=gibbonCourse.gibbonSchoolYearID)
                WHERE gibbonStudentEnrolment.gibbonStudentEnrolmentID=:gibbonStudentEnrolmentID
                AND gibbonReportingCycle.gibbonReportingCycleID=:gibbonReportingCycleID
                AND gibbonCourse.gibbonSchoolYearID=gibbonStudentEnrolment.gibbonSchoolYearID
                AND gibbonCourse.nameShort NOT LIKE '%TAP' AND gibbonCourse.nameShort NOT LIKE '%Advisor'
                AND gibbonCourseClass.reportable='Y'
                AND gibbonCourseClassPerson.role = 'Student'
                ORDER BY (CASE WHEN gibbonCourse.orderBy > 0 THEN gibbonCourse.orderBy ELSE 80 end), gibbonCourse.nameShort";

        $result = $this->db()->executeQuery($data, $sql);

        $values = array();
        if ($result->rowCount() > 0) {
            $groupedByCourse = $result->fetchGroupedUnique();

            // Build a set of top-level courses and an array of classes/modules for each
            $groupedByCourse = array_reduce($groupedByCourse, function ($courses, $item) use (&$groupedByCourse) {
                if (!empty($item['gibbonCourseIDParent']) && isset($groupedByCourse[$item['gibbonCourseIDParent']])) {
                    $courses[$item['gibbonCourseIDParent']]['classes'][] = $item;
                } else {
                    $courses[$item['gibbonCourseID']]['name'] = $item['courseName'];
                    $courses[$item['gibbonCourseID']]['nameShort'] = $item['courseNameShort'];
                    $courses[$item['gibbonCourseID']]['cycleNumber'] = $item['cycleNumber'];
                    $courses[$item['gibbonCourseID']]['description'] = strip_tags(html_entity_decode($item['description']));
                    $courses[$item['gibbonCourseID']]['gibbonCourseID'] = $item['gibbonCourseID'];
                    $courses[$item['gibbonCourseID']]['gibbonCourseClassID'] = $item['gibbonCourseClassID'];
                    $courses[$item['gibbonCourseID']]['role'] = $item['role'];
                    $courses[$item['gibbonCourseID']]['hasCredits'] = $item['hasCredits'] && !empty(intval($item['credits']));
                    $courses[$item['gibbonCourseID']]['classes'][] = $item;
                }
                return $courses;
            }, array());

            // Filter out module courses that have no parent course (likely dropped but not cleaned up)
            $groupedByCourse = array_filter($groupedByCourse, function ($course) {
                return !empty($course['gibbonCourseClassID']);
            });

            // Filter out non-reportable yet transcriptable courses :(
            $groupedByCourse = array_filter($groupedByCourse, function ($course) {
                return !in_array($course['nameShort'], ['LDC3008', 'LDC3147', 'LDC3262', 'LDC3232', 'SSN3185']);
            });

            // if ($ids['reportNum'] == 2 || $ids['reportNum'] == 4) {
            //     // Filter out advisor courses for non-interim reports
            //     $groupedByCourse = array_filter($groupedByCourse, function ($course) {
            //         return stripos($course['nameShort'], 'Advisor') === false;
            //     });
            // }

            // Get the course data and nested data sources
            foreach ($groupedByCourse as $gibbonCourseID => $course) {
                $ids['gibbonCourseID'] = $gibbonCourseID;
                $ids['gibbonCourseClassID'] = $course['gibbonCourseClassID'];

                $course['name'] = $this->filterCourseName($course['name']);
                // $course['comments'] = $this->getFactory()->get('CourseComments')->getData($ids);
                $course['teachers'] = $this->getFactory()->get('ClassTeachers')->getData($ids);
                // $course['outcomes'] = $this->getFactory()->get('ClassOutcomes')->getData($ids);

                foreach ($course['classes'] as &$class) {
                    $ids['gibbonCourseID'] = $class['gibbonCourseID'];
                    $ids['gibbonCourseClassID'] = $class['gibbonCourseClassID'];

                    $class['courseName'] = $this->filterClassName($class['courseName'], $course['cycleNumber']);
                    // $class['attendance'] = $this->getFactory()->get('ClassAttendance')->getData($ids);
                    $classGrades = $this->getFactory()->get('ClassGrades');
                    $class['grades'] = !empty($classGrades) ? $classGrades->getData($ids) : [];
                }

                $values[] = $course;
            }
        }

        return $values;
    }

    /**
     * Trim the stuff in brackets, or course numbers
     * @param string $name
     * @return string
     */
    protected function filterCourseName($name)
    {
        return trim(preg_replace('/\(.*\)|[0-9]+[0-9A-Z\-&]+|Grade [0-9]+[ &-]*[0-9 ]*|Gr[0-9]+/i', '', $name));
    }

    /**
     * Trim the stuff in brackets
     * @param string $name
     * @return string
     */
    protected function filterClassName($name, $reportNum)
    {
        if ($reportNum == 1 || $reportNum == 3) {
            $name = str_replace(['After School Secondary English Tutoring', 'Level 1', 'Level 2'], ['ASSET', 'L1', 'L2'], $name);
        }

        return trim(preg_replace('/\(.*\)/i', '', $name));
    }
}
