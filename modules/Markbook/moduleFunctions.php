<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

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

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Module\Markbook\MarkbookView;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\School\SchoolYearTermGateway;

function sidebarExtra($guid, $pdo, $gibbonPersonID, $gibbonCourseClassID = '', $basePage = '')
{
    global $session;

    $output = '';

    if (empty($basePage)) $basePage = 'markbook_view.php';

    //Show class picker in sidebar
    $output .= '<div class="column-no-break">';
    $output .= '<h2>';
    $output .= __('Choose A Class');
    $output .= '</h2>';

    $form = Form::create('searchForm', $session->get('absoluteURL').'/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('q', '/modules/Markbook/'.$basePage);
    $form->setClass('smallIntBorder w-full');

    $row = $form->addRow();
        $row->addSelectClass('gibbonCourseClassID', $session->get('gibbonSchoolYearID'), $gibbonPersonID)
            ->selected($gibbonCourseClassID)
            ->placeholder()
            ->setClass('fullWidth');
        $row->addSubmit(__('Go'));

    $output .= $form->getOutput();
    $output .= '</div>';

    return $output;
}

function classChooser($guid, $pdo, $gibbonCourseClassID)
{
    global $session, $container;

    $settingGateway = $container->get(SettingGateway::class);
    $enableColumnWeighting = $settingGateway->getSettingByScope('Markbook', 'enableColumnWeighting');
    $enableGroupByTerm = $settingGateway->getSettingByScope('Markbook', 'enableGroupByTerm');
    $enableRawAttainment = $settingGateway->getSettingByScope('Markbook', 'enableRawAttainment');
    $defaultAssessmentScale = $settingGateway->getSettingByScope('System', 'defaultAssessmentScale');

    $output = '';

    $output .= "<h3 style='margin-top: 0px'>";
    $output .= __('Choose Class');
    $output .= '</h3>';

    $form = Form::create('searchForm', $session->get('absoluteURL').'/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/markbook_view.php');

    $col = $form->addRow()->addColumn()->addClass('inline right');

    // SEARCH
    $search = $_GET['search'] ?? '';

    $col->addContent(__('Search').':');
    $col->addTextField('search')
        ->setClass('shortWidth')
        ->setValue($search);

    // TERM
    if ($enableGroupByTerm == 'Y' ) {
        $selectTerm = ($session->has('markbookTerm'))? $session->get('markbookTerm') : 0;
        $selectTerm = (isset($_GET['gibbonSchoolYearTermID']))? $_GET['gibbonSchoolYearTermID'] : $selectTerm;

        if (!isset($_GET['gibbonSchoolYearTermID'])) { //Set to current term if not already set
            $schoolYearTermGateway = $container->get(SchoolYearTermGateway::class);
            $currentTerm = $schoolYearTermGateway->getCurrentTermByDate(date('Y-m-d'));
            if (isset($currentTerm['gibbonSchoolYearTermID'])) {
                $selectTerm = $currentTerm['gibbonSchoolYearTermID'];
            }

        }
        
        $data = array("gibbonSchoolYearID" => $session->get('gibbonSchoolYearID'));
        $sql = "SELECT gibbonSchoolYearTermID as value, name FROM gibbonSchoolYearTerm WHERE gibbonSchoolYearID=:gibbonSchoolYearID ORDER BY sequenceNumber";
        $result = $pdo->executeQuery($data, $sql);
        $terms = ($result->rowCount() > 0)? $result->fetchAll(\PDO::FETCH_KEY_PAIR) : array();

        $col->addContent(__('Term').':')->prepend('&nbsp;&nbsp;');
        $col->addSelect('gibbonSchoolYearTermID')
            ->fromArray(array('-1' => __('All Terms')))
            ->fromArray($terms)
            ->selected($selectTerm)
            ->setClass('shortWidth');

        $session->set('markbookTermName', isset($terms[$selectTerm])? $terms[$selectTerm] : $selectTerm);
        $session->set('markbookTerm', $selectTerm);
    } else {
        $session->set('markbookTerm', 0);
        $session->set('markbookTermName', __('All Columns'));
    }

    // SORT BY
    $data = array('gibbonCourseClassID' => $gibbonCourseClassID, 'gibbonSchoolYearID'=>$session->get('gibbonSchoolYearID') );
    $sql = "SELECT COUNT(DISTINCT rollOrder) FROM gibbonCourseClassPerson INNER JOIN gibbonPerson ON (gibbonCourseClassPerson.gibbonPersonID=gibbonPerson.gibbonPersonID) LEFT JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonCourseClassPerson.gibbonPersonID) WHERE role='Student' AND gibbonCourseClassID=:gibbonCourseClassID AND status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND gibbonSchoolYearID=:gibbonSchoolYearID";
    $result = $pdo->executeQuery($data, $sql);
    $rollOrderCount = ($result->rowCount() > 0)? $result->fetchColumn(0) : 0;
    if ($rollOrderCount > 0) {
        $selectOrderBy = ($session->has('markbookOrderBy'))? $session->get('markbookOrderBy') : 'surname';
        $selectOrderBy = (isset($_GET['markbookOrderBy']))? $_GET['markbookOrderBy'] : $selectOrderBy;

        $orderBy = array(
            'rollOrder'     => __('Roll Order'),
            'surname'       => __('Surname'),
            'preferredName' => __('Preferred Name'),
        );
        $col->addContent(__('Sort By').':')->prepend('&nbsp;&nbsp;');
        $col->addSelect('markbookOrderBy')->fromArray($orderBy)->selected($selectOrderBy)->setClass('shortWidth');

        $session->set('markbookOrderBy', $selectOrderBy);
    }

    // SHOW
    $selectFilter = ($session->has('markbookFilter'))? $session->get('markbookFilter') : '';
    $selectFilter = (isset($_GET['markbookFilter']))? $_GET['markbookFilter'] : $selectFilter;

    $session->set('markbookFilter', $selectFilter);

    $filters = array('' => __('All Columns'));
    $filters['summative'] = __('Summative');
    $filters['formative'] = __('Formative');
    if ($enableColumnWeighting == 'Y' && $defaultAssessmentScale == 4) $filters['averages'] = __('Overall Grades');
    if ($enableRawAttainment == 'Y') $filters['raw'] = __('Raw Marks');
    $filters['marked'] = __('Marked');
    $filters['unmarked'] = __('Unmarked');

    $col->addContent(__('Show').':')->prepend('&nbsp;&nbsp;');
    $col->addSelect('markbookFilter')
        ->fromArray($filters)
        ->selected($selectFilter)
        ->setClass('shortWidth');

    // CLASS
    $col->addContent(__('Class').':')->prepend('&nbsp;&nbsp;');
    $col->addSelectClass('gibbonCourseClassID', $session->get('gibbonSchoolYearID'), $session->get('gibbonPersonID'))
        ->setClass('mediumWidth')
        ->selected($gibbonCourseClassID);

    $col->addSubmit(__('Go'));

    if (!empty($search)) {
        $clearURL = $session->get('absoluteURL').'/index.php?q='.$session->get('address');
        $clearLink = sprintf('<a href="%s" class="small" style="">%s</a> &nbsp;', $clearURL, __('Clear Search'));

        $form->addRow()->addContent($clearLink)->addClass('right');
    }

    $output .= $form->getOutput();

    return $output;
}

function isDepartmentCoordinator( $pdo, $gibbonPersonID ) {

        $data = array('gibbonPersonID' => $gibbonPersonID );
        $sql = "SELECT count(*) FROM gibbonDepartmentStaff WHERE gibbonPersonID=:gibbonPersonID AND (role='Coordinator' OR role='Assistant Coordinator' OR role='Teacher (Curriculum)')";
        $result = $pdo->executeQuery($data, $sql);


    return ($result->rowCount() > 0)? ($result->fetchColumn() >= 1) : false;
}

function getAnyTaughtClass( $pdo, $gibbonPersonID, $gibbonSchoolYearID ) {

        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID, 'gibbonPersonID' => $gibbonPersonID);
        $sql = 'SELECT gibbonCourse.nameShort AS course, gibbonCourseClass.nameShort AS class, gibbonCourseClass.gibbonCourseClassID FROM gibbonCourse, gibbonCourseClass, gibbonCourseClassPerson WHERE gibbonSchoolYearID=:gibbonSchoolYearID AND gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID AND gibbonCourseClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID AND gibbonCourseClassPerson.gibbonPersonID=:gibbonPersonID ORDER BY course, class LIMIT 1';
        $result = $pdo->executeQuery($data, $sql);

    return ($result->rowCount() > 0)? $result->fetch() : NULL;
}

function getClass( $pdo, $gibbonPersonID, $gibbonCourseClassID, $highestAction ) {
    try {
        if ($highestAction == 'View Markbook_allClassesAllData') {
            $data = array('gibbonCourseClassID' => $gibbonCourseClassID);
            $sql = 'SELECT gibbonCourse.nameShort AS course, gibbonCourse.name AS courseName, gibbonCourseClass.nameShort AS class, gibbonCourseClass.gibbonCourseClassID, gibbonCourse.gibbonDepartmentID, gibbonYearGroupIDList FROM gibbonCourse, gibbonCourseClass WHERE gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID AND gibbonCourseClass.gibbonCourseClassID=:gibbonCourseClassID ORDER BY course, class';
        } else if ($highestAction == 'View Markbook_myClasses') {
            $data = array( 'gibbonPersonID' => $gibbonPersonID, 'gibbonCourseClassID' => $gibbonCourseClassID);
            $sql = "SELECT gibbonCourse.nameShort AS course, gibbonCourse.name AS courseName, gibbonCourseClass.nameShort AS class, gibbonCourse.gibbonYearGroupIDList, gibbonCourseClass.gibbonCourseClassID FROM gibbonCourse, gibbonCourseClass, gibbonCourseClassPerson WHERE gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID AND gibbonCourseClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID AND gibbonCourseClassPerson.gibbonPersonID=:gibbonPersonID AND role='Teacher' AND gibbonCourseClass.gibbonCourseClassID=:gibbonCourseClassID ORDER BY course, class";
        } else {
            return null;
        }
        $result = $pdo->executeQuery($data, $sql);
    } catch (PDOException $e) {
        return null;
    }

    return ($result->rowCount() > 0)? $result->fetch() : NULL;
}

function getTeacherList( $pdo, $gibbonCourseClassID ) {

        $data = array('gibbonCourseClassID' => $gibbonCourseClassID);
        $sql = "SELECT gibbonPerson.gibbonPersonID, title, surname, preferredName, gibbonCourseClassPerson.reportable FROM gibbonCourseClassPerson JOIN gibbonPerson ON (gibbonCourseClassPerson.gibbonPersonID=gibbonPerson.gibbonPersonID) WHERE role='Teacher' AND gibbonPerson.status='Full' AND gibbonCourseClassID=:gibbonCourseClassID ORDER BY surname, preferredName";
        $result = $pdo->executeQuery($data, $sql);


    $teacherList = array();
    if ($result->rowCount() > 0) {
        foreach ($result->fetchAll() as $teacher) {
            if ($teacher['reportable'] != 'Y') continue;

            $teacherList[ $teacher['gibbonPersonID'] ] = Format::name($teacher['title'], $teacher['preferredName'], $teacher['surname'], 'Staff', false, false);
        }
    }

    return $teacherList;
}

function getAlertStyle( $alert, $concern ) {

    if ($concern == 'Y') {
        return "style='color: ".$alert['color'].'; font-weight: bold; border: 2px solid '.$alert['color'].'; padding: 2px 4px; background-color: '.$alert['colorBG'].";margin:0 auto;'";
    } else if ($concern == 'P') {
        return "style='color: #390; font-weight: bold; border: 2px solid #390; padding: 2px 4px; background-color: #D4F6DC;margin:0 auto;'";
    } else {
        return '';
    }
}

function getReportGrade($pdo, $reportName, $gibbonSchoolYearID, $gibbonPersonIDStudent, $gibbonCourseClassID) {

    // read criteria for this subject
    $data = array(
        'gibbonCourseClassID' => $gibbonCourseClassID,
        'gibbonPersonIDStudent' => $gibbonPersonIDStudent,
        'reportName' => $reportName,
        'gibbonSchoolYearID' => $gibbonSchoolYearID,
        'today' => date('Y-m-d'),
    );
    $sql = "SELECT arrReportGrade.gradeID
        FROM arrCriteria
        JOIN arrReport ON (arrCriteria.reportID=arrReport.reportID)
        JOIN arrReportGrade ON (arrReportGrade.criteriaID=arrCriteria.criteriaID)
        JOIN gibbonCourseClass ON (arrCriteria.subjectID=gibbonCourseClass.gibbonCourseID)
        WHERE arrReport.reportName=:reportName
        AND arrReport.schoolYearID=:gibbonSchoolYearID
        AND arrReport.endDate<=:today
        AND arrCriteria.criteriaType = 2
        AND arrReportGrade.studentID=:gibbonPersonIDStudent
        AND gibbonCourseClass.gibbonCourseClassID=:gibbonCourseClassID";
    $rs = $pdo->executeQuery($data, $sql);

    return ($rs && $rs->rowCount() >= 1)? $rs->fetchColumn(0) : false;
}

function getCriteriaGrade($pdo, $criteriaType, $gibbonSchoolYearID, $gibbonPersonIDStudent, $gibbonCourseClassID) {

    // read criteria for this subject
    $data = array(
        'gibbonCourseClassID' => $gibbonCourseClassID,
        'gibbonPersonIDStudent' => $gibbonPersonIDStudent,
        'criteriaType' => $criteriaType,
        'gibbonSchoolYearID' => $gibbonSchoolYearID,
        'today' => date('Y-m-d'),
    );
    $sql = "SELECT arrReportGrade.gradeID
        FROM arrCriteria
        JOIN arrReport ON (arrCriteria.reportID=arrReport.reportID)
        JOIN arrReportGrade ON (arrReportGrade.criteriaID=arrCriteria.criteriaID)
        JOIN gibbonCourseClass ON (arrCriteria.subjectID=gibbonCourseClass.gibbonCourseID)
        WHERE arrCriteria.criteriaType =:criteriaType
        AND arrReportGrade.studentID=:gibbonPersonIDStudent
        AND gibbonCourseClass.gibbonCourseClassID=:gibbonCourseClassID
        AND arrReport.schoolYearID=:gibbonSchoolYearID
        AND arrReport.endDate<=:today
        ORDER BY arrCriteria.reportID DESC LIMIT 1";
    $rs = $pdo->executeQuery($data, $sql);

    return ($rs->rowCount() >= 1)? $rs->fetchColumn(0) : false;
}

function getLegacyGrade($pdo, $reportName, $gibbonSchoolYearID, $gibbonPersonIDStudent, $gibbonCourseClassID) {
    // read criteria for this subject
    $data = array(
        'reportName' => $reportName,
        'gibbonCourseClassID' => $gibbonCourseClassID,
        'gibbonPersonID' => $gibbonPersonIDStudent,
        'gibbonSchoolYearID' => $gibbonSchoolYearID,
    );
    $sql = "SELECT grade
        FROM arrLegacyGrade
        WHERE arrLegacyGrade.reportTerm=:reportName
        AND arrLegacyGrade.gibbonSchoolYearID=:gibbonSchoolYearID
        AND arrLegacyGrade.gibbonPersonID=:gibbonPersonID
        AND arrLegacyGrade.gibbonCourseClassID=:gibbonCourseClassID";
    $rs = $pdo->executeQuery($data, $sql);

    return ($rs && $rs->rowCount() >= 1)? $rs->fetchColumn(0) : false;
}

function renderStudentGPA( $pdo, $guid, $gibbonPersonIDStudent, $gibbonSchoolYearID, $finalizedReports = true) {

    $data = array(
        'gibbonPersonIDStudent' => $gibbonPersonIDStudent,
        'gibbonSchoolYearID' => $gibbonSchoolYearID,
        // 'today' => date('Y-m-d'),
    );
    $sql = "SELECT arrReportGPA.GPA, arrReportGPA.status, arrReport.reportName, gibbonSchoolYear.name as schoolYearName
        FROM arrReportGPA
        JOIN arrReport ON (arrReportGPA.reportID=arrReport.reportID)
        JOIN gibbonSchoolYear ON (gibbonSchoolYear.gibbonSchoolYearID=arrReport.schoolYearID)
        WHERE arrReport.schoolYearID=:gibbonSchoolYearID";

    if ($finalizedReports) {
        $data['today'] = date('Y-m-d');
        $sql .= " AND arrReport.endDate<=:today ";
    }
    
    $sql .= " 
        AND arrReportGPA.studentID=:gibbonPersonIDStudent
        ORDER BY arrReport.reportID ASC";

    $rs = $pdo->executeQuery($data, $sql);

    if ($rs->rowCount() == 0) return;

    $marks = $rs->fetchAll();

    echo '<h4>'.current($marks)['schoolYearName'].' GPA</h4>';

    echo '<table class="mini fullWidth" cellspacing="0">';
        echo '<tr class="head">';

        foreach ($marks as $row) {
            if (empty($row['GPA'])) continue;
            echo '<th class="columnLabel" style="border: 0; padding: 10px !important;text-align: center; width: 65px;font-size: 11px;">'.$row['reportName'].'</td>';
        }

        echo '<td rowspan="2" style="padding: 10px 30px !important; border: 0; border-left: 1px solid #dfdfdf;font-weight: normal;">';
            echo '<span class="small emphasis">A student\'s GPA is the weighted average of course marks, taking into account the credit value of each course. The GPA\'s listed here are from  posted report card marks.</span>';
        echo '</td>';
    echo '</tr>';

    echo '<tr>';

        foreach ($marks as $row) {
            if (empty($row['GPA'])) continue;
            echo '<td style="padding: 10px !important; text-align: center;line-height: 1.5;">'.round( $row['GPA'], 1 ).'%<br/>'.$row['status'].'</td>';
        }
    echo '</tr>';

    echo '</table>';
}

function renderStudentCourseAverage($pdo, $guid, $gibbonPersonIDStudent)
{
    return '';

    global $gibbon, $container;
    require_once __DIR__ . '/src/MarkbookView.php';

    $gibbonSchoolYearID = (!empty($gibbonSchoolYearID))? $gibbonSchoolYearID : $_SESSION[$guid]['gibbonSchoolYearID'];

    $data = array(
        'gibbonPersonID' => $gibbonPersonIDStudent,
        'gibbonSchoolYearID' => $gibbonSchoolYearID,
    );
    $sql = "SELECT gibbonCourseClassPerson.gibbonCourseClassID, gibbonCourse.weight as courseWeight, (CASE WHEN gibbonCourse.orderBy > 0 THEN gibbonCourse.orderBy ELSE 80 end) as courseOrder
            FROM gibbonCourseClassPerson
            JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID = gibbonCourseClassPerson.gibbonCourseClassID)
            JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID = gibbonCourseClass.gibbonCourseID)
            JOIN gibbonMarkbookColumn ON (gibbonMarkbookColumn.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
            WHERE gibbonCourseClassPerson.gibbonPersonID = :gibbonPersonID
            AND gibbonCourseClassPerson.role = 'Student'
            AND gibbonCourseClass.reportable = 'Y'
            AND gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID
            GROUP BY gibbonCourseClass.gibbonCourseClassID
            ORDER BY courseOrder";

    $result = $pdo->executeQuery($data, $sql);

    if ($result->rowCount() == 0) return;

    $total = 0;
    $cumulative = 0;

    while ($course = $result->fetch())
    {
        // Build the markbook object for this class & student
        $markbook = new MarkbookView($gibbon, $pdo, $course['gibbonCourseClassID'], $container->get(SettingGateway::class));
        $markbook->cacheWeightings($gibbonPersonIDStudent);
        
        // Grab the course weight and grade
        $weight = $course['courseWeight'];
        $grade = $markbook->getCumulativeAverage($gibbonPersonIDStudent);
        
        // Skip any empty or incomplete marks
        if ($grade == '' || $grade == '-' || $grade == 'INC') continue;

        // Sum the cumulative weight & grades
        $total += $weight;
        $cumulative += ($grade * $weight);
    }

    if (empty($total) || empty($cumulative) ) return;
    
    // Calculate the GPA
    $gpa = ( $cumulative / $total );
    $gpa = round( min(100.0, max(0.0, $gpa)), 2);

    if ($gpa >= 95.0) {
        $status = 'Scholars';
    } else if ($gpa >= 90.0) {
        $status = 'Distinction';
    } else if ($gpa >= 80.0) {
        $status = 'Honours';
    } else if ($gpa >= 60.0) {
        $status = 'Good Standing';
    } else {
        $status = 'At Risk';
    }
    
    echo '<h4>Current Cumulative Average</h4>';
    
    echo '<table class="mini fullWidth" cellspacing="0">';
        echo '<tr class="head">';

        echo '<th class="columnLabel" style="border: 0; padding: 10px !important;text-align: center; width: 85px;font-size: 11px;">'.__('Average').'</td>';
        echo '<th class="columnLabel" style="border: 0; padding: 10px !important;text-align: center; width: 85px;font-size: 11px;">'.__('Status').'</td>';

        echo '<td rowspan="2" style="padding: 10px 30px !important; border: 0; border-left: 1px solid #dfdfdf;">';
            echo '<span class="small emphasis">The current average is weighted per course and calculated from ongoing course work. All markbook grades are subject to change. The average listed here is not a posted grade and may differ from the final GPA for this term. <b>Only visible to teachers and staff at this time.</b></span>';
        echo '</td>';
    echo '</tr>';
    echo '<tr>';
        echo '<td style="padding: 10px !important; text-align: center;">'.round( $gpa, 1 ).'%</td>';
        echo '<td style="padding: 10px !important; text-align: center;">'.$status.'</td>';
    echo '</tr>';
    echo '</table>';
}

function renderStudentCumulativeMarks($gibbon, $pdo, $gibbonPersonIDStudent, $gibbonCourseClassID, $gibbonSchoolYearID = '') {
    global $container;

    $guid = $gibbon->guid();
    $gibbonSchoolYearID = (!empty($gibbonSchoolYearID))? $gibbonSchoolYearID : $_SESSION[$guid]['gibbonSchoolYearID'];

    $termNames = array('Secondary Term 1', 'Secondary Term 2', 'Secondary Term 3');

    // Gibbon Reporting Grades
    $sem1Mid = getReportGrade($pdo, $termNames[0], $gibbonSchoolYearID, $gibbonPersonIDStudent, $gibbonCourseClassID);
    $sem1End = getReportGrade($pdo, $termNames[1], $gibbonSchoolYearID, $gibbonPersonIDStudent, $gibbonCourseClassID);
    $sem2Mid = getReportGrade($pdo, $termNames[2], $gibbonSchoolYearID, $gibbonPersonIDStudent, $gibbonCourseClassID);

    $finalMark = getCriteriaGrade($pdo, 4, $gibbonSchoolYearID, $gibbonPersonIDStudent, $gibbonCourseClassID);

    if (!empty($finalMark)) {

        $message = '<b>Course complete</b>: Final marks listed are from report card grades.';

        $courseMark = '';
        $examMark = '';
        // $examMark = getCriteriaGrade($pdo, 1, $gibbonSchoolYearID, $gibbonPersonIDStudent, $gibbonCourseClassID);
    } else {

        $enableColumnWeighting = $container->get(SettingGateway::class)->getSettingByScope('Markbook', 'enableColumnWeighting');
        if ($enableColumnWeighting != 'Y') return;

        require_once __DIR__ . '/src/MarkbookView.php';

        // Build the markbook object for this class & student
        $markbook = new MarkbookView($gibbon, $pdo, $gibbonCourseClassID, $container->get(SettingGateway::class));
        $markbook->cacheWeightings( $gibbonPersonIDStudent );

        $message = '<b>Current course</b>: Overall mark is a cumulative grade from ongoing course work.';

        $courseMark = round( $markbook->getCumulativeAverage( $gibbonPersonIDStudent ) );
        $examMark = ''; //round( $markbook->getTermAverage($gibbonPersonIDStudent, 'final') );
        $finalMark = ''; //round( $markbook->getFinalGradeAverage( $gibbonPersonIDStudent ) );
    }
    

    // Only display if there are marks
    if (!empty($courseMark) || !empty($examMark) || !empty($finalMark) ) {
        echo '<tr>';

        echo '<td colspan=7 style="padding:0;">';
        echo '<table class="mini fullWidth" style="margin: 0; border: 0;" cellspacing="0">';
        echo '<tr class="head">';

        echo '<th class="columnLabel" style="border: 0; padding: 10px !important;text-align: center; width: 64px;font-size: 11px;">'.$termNames[0].'</td>';
        echo '<th class="columnLabel" style="border: 0; padding: 10px !important;text-align: center; width: 64px;font-size: 11px;">'.$termNames[1].'</td>';
        echo '<th class="columnLabel" style="border: 0; padding: 10px !important;text-align: center; width: 64px;font-size: 11px;">'.$termNames[2].'</td>';

        echo '<td rowspan="2" style="padding: 10px 30px !important;">';
            echo '<span class="small emphasis">'.$message.'</span>';
        echo '</td>';

        if (!empty($courseMark)) {
            echo '<th class="columnLabel" style="border: 0; padding: 10px !important;text-align: center; width: 65px;">'.__($guid, 'Course').'</td>';
        }
        if (!empty($examMark)) {
            echo '<th class="columnLabel" style="border: 0; padding: 10px !important;text-align: center; width: 65px;">'.__($guid, 'Exam').'</td>';
        }
        if (!empty($finalMark)) {
            echo '<th class="columnLabel" style="border: 0; padding: 10px !important;text-align: center; width: 65px;">'.__($guid, 'Final').'</td>';
        }
        echo '</tr>';

        echo '<tr>';

        echo '<td style="padding: 10px !important; text-align: center;">'.( !empty($sem1Mid)? round( $sem1Mid ).'%' : '' ) .'</td>';

        echo '<td style="padding: 10px !important; text-align: center;">'.( !empty($sem1End)? round( $sem1End ).'%' : '' ) .'</td>';

        echo '<td style="padding: 10px !important; text-align: center;">'.( !empty($sem2Mid)? round( $sem2Mid ).'%' : '' ) .'</td>';

        // Display the cumulative average
        if (!empty($courseMark)) {
            echo '<td style="background: -moz-linear-gradient(top, #f2f2f2, #f0f0f0); padding: 10px !important; text-align: center;">';
            echo round( $courseMark ).'%' .'</td>';
        }

        // Display final exam mark
        if (!empty($examMark)) {
            echo '<td style="background: -moz-linear-gradient(top, #f2f2f2, #f0f0f0); padding: 10px !important; text-align: center;">';
            echo round( $examMark ).'%' .'</td>';
        }

        // Display final course mark
        if (!empty($finalMark)) {
            echo '<td style="background: -moz-linear-gradient(top, #f2f2f2, #f0f0f0); padding: 10px !important; text-align: center;">';
            echo round( $finalMark ).'%' .'</td>';
        }

        echo '</tr></table>';
        echo '</td>';
        echo '</tr>';

        return true;
    } else {
        return false;
    }
}

function renderStudentSubmission($student, $submission, $markbookColumn)
{
    global $guid, $session;

    $output = '';

    if (!empty($submission)) {
        if ($submission['status'] == 'Exemption') {
            $linkText = __('Exe');
        } elseif ($submission['version'] == 'Final') {
            $linkText = __('Fin');
        } else {
            $linkText = __('Dra').$submission['count'];
        }

        $style = '';
        $status = __('On Time');
        if ($submission['status'] == 'Exemption') {
            $status = __('Exemption');
        } elseif ($submission['status'] == 'Late') {
            $style = "style='color: #ff0000; font-weight: bold; border: 2px solid #ff0000; padding: 2px 4px'";
            $status = __('Late');
        }

        if ($submission['type'] == 'File') {
            $output .= "<span title='".$submission['version'].". $status. ".__('Submitted at').' '.substr($submission['timestamp'], 11, 5).' '.__('on').' '.Format::date(substr($submission['timestamp'], 0, 10))."' $style><a target='_blank' href='".$session->get('absoluteURL').'/'.$submission['location']."'>$linkText</a></span>";
        } elseif ($submission['type'] == 'Link') {
            $output .= "<span title='".$submission['version'].". $status. ".__('Submitted at').' '.substr($submission['timestamp'], 11, 5).' '.__('on').' '.Format::date(substr($submission['timestamp'], 0, 10))."' $style><a target='_blank' href='".$submission['location']."'>$linkText</a></span>";
        } else {
            $output .= "<span title='$status. ".__('Recorded at').' '.substr($submission['timestamp'], 11, 5).' '.__('on').' '.Format::date(substr($submission['timestamp'], 0, 10))."' $style>$linkText</span>";
        }
    } else {
        if (date('Y-m-d H:i:s') < $markbookColumn['homeworkDueDateTime']) {
            $output .= "<span title='".__('Pending')."'>".__('Pen').'</span>';
        } else {
            if (!empty($student['dateStart']) && $student['dateStart'] > $markbookColumn['lessonDate']) {
                $output .= "<span title='".__('Student joined school after assessment was given.')."' style='color: #000; font-weight: normal; border: 2px none #ff0000; padding: 2px 4px'>NA</span>";
            } else {
                if ($markbookColumn['homeworkSubmissionRequired'] == 'Required') {
                    $output .= "<span title='".__('Incomplete')."' style='color: #ff0000; font-weight: bold; border: 2px solid #ff0000; padding: 2px 4px'>".__('Inc').'</span>';
                } else {
                    $output .= "<span title='".__('Not submitted online')."'>".__('NA').'</span>';
                }
            }
        }
    }

    return $output;
}
