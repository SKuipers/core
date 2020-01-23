<?php

use Gibbon\Module\Reports\DataSource;

class Transcript extends DataSource
{
    public function getSchema()
    {
        $schema = [];

        $departments = ['English', 'Social', 'Science', 'Math', 'Modern Languages', 'Fine Arts', 'Physical Education', 'CTS'];
        for ($i = 0; $i < rand(6, 8); $i++) {
            $key = $departments[$i];
            $schema[$key] = [];

            for ($n = 0; $n < rand(3, 10); $n++) {
                $schema[$key][$n] = [
                    'year'         => '2018-2019',
                    'name'         => 'Biology 20',
                    'nameShort'    => 'SCN2231',
                    'credits'      => '5',
                    'interim' => ['numberBetween', 0, 1],
                    'grade'   => ['numberBetween', 50, 100],
                ];
            }
        }

        return $schema;
    }

    public function getData($ids = [])
    {
        $data = ['gibbonReportID' => $ids['gibbonReportID']];
        $gibbonYearGroupIDList = $this->db()->selectOne("SELECT gibbonYearGroupIDList FROM gibbonReport WHERE gibbonReportID=:gibbonReportID", $data);

        $data = ['gibbonStudentEnrolmentID' => $ids['gibbonStudentEnrolmentID'], 'gibbonYearGroupIDList' => $gibbonYearGroupIDList];
        $sql = "
            ( 
                SELECT DISTINCT gibbonDepartment.name as department, gibbonSchoolYear.name as year, gibbonCourse.nameShort, gibbonCourse.name, gibbonCourse.credits, (CASE WHEN gibbonReportingCriteria.name LIKE '%Final%' THEN 0 ELSE 1 END) as interim, gibbonReportingValue.value as grade, 'Standard' as gradeType, gibbonSchoolYear.sequenceNumber as yearOrder, (CASE WHEN gibbonCourse.orderBy > 0 THEN gibbonCourse.orderBy ELSE 80 end) as courseOrder, gibbonDepartment.sequenceNumber as departmentOrder, gibbonReportingValue.gibbonReportingValueID as debug
                FROM gibbonStudentEnrolment
                JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID AND gibbonCourseClassPerson.role='Student')
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                JOIN gibbonStudentEnrolment as courseEnrolment ON (courseEnrolment.gibbonSchoolYearID=gibbonCourse.gibbonSchoolYearID AND courseEnrolment.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID)
                JOIN gibbonSchoolYear ON (gibbonCourse.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID)
                JOIN gibbonDepartment ON (gibbonCourse.gibbonDepartmentID=gibbonDepartment.gibbonDepartmentID)
                LEFT JOIN gibbonReportingCriteria ON (gibbonReportingCriteria.gibbonCourseID=gibbonCourse.gibbonCourseID AND gibbonReportingCriteria.target='Per Student')
                LEFT JOIN gibbonReportingCriteriaType ON (gibbonReportingCriteriaType.gibbonReportingCriteriaTypeID=gibbonReportingCriteria.gibbonReportingCriteriaTypeID)
                LEFT JOIN gibbonReportingValue ON (gibbonReportingValue.gibbonReportingCriteriaID=gibbonReportingCriteria.gibbonReportingCriteriaID
                    AND gibbonReportingValue.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID 
                    AND gibbonReportingValue.gibbonPersonIDStudent=gibbonCourseClassPerson.gibbonPersonID)
                WHERE gibbonStudentEnrolment.gibbonStudentEnrolmentID=:gibbonStudentEnrolmentID
                AND FIND_IN_SET(courseEnrolment.gibbonYearGroupID, :gibbonYearGroupIDList)
                AND gibbonCourseClass.reportable='Y'
                AND (gibbonReportingValue.gibbonReportingValueID IS NULL OR (gibbonReportingValue.gibbonReportingValueID IS NOT NULL AND gibbonReportingValue.value <> '' AND gibbonReportingCriteriaType.name = 'Secondary Percent Grade'))
                AND courseEnrolment.gibbonSchoolYearID >= 014
            )
            UNION ALL
            (
                SELECT 
                DISTINCT gibbonDepartment.name as department, gibbonSchoolYear.name as year, gibbonCourse.nameShort, gibbonCourse.name, (CASE WHEN (arrCriteria.criteriaType=4 OR arrCriteria.criteriaType=10) AND gradeID >= 50.0 THEN gibbonCourse.credits WHEN gradeID = '' THEN '' ELSE 0 END) as credits, 0 as interim, gradeID as grade, 'Standard' as gradeType, 
                gibbonSchoolYear.sequenceNumber as yearOrder, (CASE WHEN gibbonCourse.orderBy > 0 THEN gibbonCourse.orderBy ELSE 80 end) as courseOrder, gibbonDepartment.sequenceNumber as departmentOrder, 'Reporting' as debug
                FROM gibbonStudentEnrolment
                JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID AND gibbonCourseClassPerson.role='Student')
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                JOIN gibbonStudentEnrolment as courseEnrolment ON (courseEnrolment.gibbonSchoolYearID=gibbonCourse.gibbonSchoolYearID AND courseEnrolment.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID)
                JOIN gibbonDepartment ON (gibbonCourse.gibbonDepartmentID=gibbonDepartment.gibbonDepartmentID)
                JOIN gibbonSchoolYear ON (gibbonCourse.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID)
                JOIN arrCriteria ON (gibbonCourse.gibbonCourseID=arrCriteria.subjectID AND (
                    (arrCriteria.reportID < 26 AND arrCriteria.criteriaType=4) OR (arrCriteria.reportID >= 26 AND arrCriteria.criteriaType=10))
                )
                LEFT JOIN arrReportGrade ON (arrReportGrade.criteriaID=arrCriteria.criteriaID AND arrReportGrade.studentID = gibbonCourseClassPerson.gibbonPersonID )
                LEFT JOIN arrReport ON (arrReport.reportID=arrReportGrade.reportID AND arrReport.schoolYearID=gibbonCourse.gibbonSchoolYearID )
                WHERE gibbonStudentEnrolment.gibbonStudentEnrolmentID=:gibbonStudentEnrolmentID
                AND FIND_IN_SET(courseEnrolment.gibbonYearGroupID, :gibbonYearGroupIDList)
                AND gibbonCourseClass.reportable='Y'
                AND (arrReport.reportName IS NOT NULL || arrReportGrade.reportGradeID IS NULL)
            )
            UNION ALL
            (
                SELECT DISTINCT gibbonDepartment.name as department, gibbonSchoolYear.name as year, gibbonCourse.nameShort, gibbonCourse.name, gibbonCourse.credits, 0 as interim, grade, gradeType, gibbonSchoolYear.sequenceNumber as yearOrder, (CASE WHEN gibbonCourse.orderBy > 0 THEN gibbonCourse.orderBy ELSE 80 end) as courseOrder, gibbonDepartment.sequenceNumber as departmentOrder, 'Legacy' as debug
                FROM gibbonStudentEnrolment
                JOIN arrLegacyGrade ON (arrLegacyGrade.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=arrLegacyGrade.gibbonCourseID)
                JOIN gibbonStudentEnrolment as courseEnrolment ON (courseEnrolment.gibbonSchoolYearID=gibbonCourse.gibbonSchoolYearID AND courseEnrolment.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID)
                JOIN gibbonDepartment ON (gibbonCourse.gibbonDepartmentID=gibbonDepartment.gibbonDepartmentID)
                JOIN gibbonSchoolYear ON (gibbonCourse.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID)
                WHERE gibbonStudentEnrolment.gibbonStudentEnrolmentID=:gibbonStudentEnrolmentID
                AND FIND_IN_SET(courseEnrolment.gibbonYearGroupID, :gibbonYearGroupIDList)
            ) ORDER BY departmentOrder, yearOrder, courseOrder, nameShort
        ";
        
        $values = $this->db()->select($sql, $data)->fetchAll();

        $courses = [];

        foreach ($values as $course) {
            if (!$this->isTranscriptCourse($course['nameShort'])) continue;

            if ($course['gradeType'] == 'Transfer') {
                $course['name'] .= ' T';
            } elseif ($course['gradeType'] == 'Retroactive') {
                $course['name'] .= ' R';
            }

            $courses[$course['department']][] = $course;
        }

        return $courses;
    }

    function isTranscriptCourse($courseCode) {

        if (strpos($courseCode, 'ECA') !== false) return false;
        if (strpos($courseCode, 'HOME') !== false) return false;
        if (strpos($courseCode, 'Advis') !== false) return false;
        if (strpos($courseCode, 'MAM-') !== false) return false;
        if (strpos($courseCode, 'MTM') !== false) return false;
        if (strpos($courseCode, 'ERP-') !== false) return false;
        if (strpos($courseCode, 'ADD-') !== false) return false;
        if (strpos($courseCode, 'OUT-') !== false) return false;
        if (strpos($courseCode, 'FIN-') !== false) return false;
        if (strpos($courseCode, 'ENV-') !== false) return false;
        if (strpos($courseCode, 'SPRT') !== false) return false;
        if (strpos($courseCode, 'TAP') !== false) return false;
        if (strpos($courseCode, 'EXP') !== false) return false;
        if (strpos($courseCode, 'BUS-') !== false) return false;
        if (strpos($courseCode, 'FILM-') !== false) return false;
        if (strpos($courseCode, 'CSE-') !== false) return false;
        if ($courseCode == 'STUDY') return false;
        if ($courseCode == 'CHI') return false;

        return true;
    }
}
