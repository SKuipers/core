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

use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\Markbook\MarkbookView;
use Gibbon\Domain\Planner\PlannerEntryGateway;
use Gibbon\Domain\Departments\DepartmentGateway;
use Gibbon\Domain\Markbook\MarkbookColumnGateway;
use Gibbon\Domain\Planner\PlannerEntryHomeworkGateway;
use Gibbon\Forms\Form;


// Lock the file so other scripts cannot call it
if (MARKBOOK_VIEW_LOCK !== sha1( $highestAction . $session->get('gibbonPersonID') ) . date('zWy') ) return;

require_once __DIR__ . '/src/MarkbookView.php';
require_once __DIR__ . '/src/MarkbookColumn.php';


    //reset ordering
    if(isset($_GET['reset'], $_GET['gibbonCourseClassID']) && $_GET['reset']==1){
        $data = array('gibbonCourseClassID' => $_GET['gibbonCourseClassID']);
        $sql = 'SET @count:=0;UPDATE gibbonMarkbookColumn SET `sequenceNumber`=@count:=@count+1 WHERE `gibbonCourseClassID` = :gibbonCourseClassID order by gibbonMarkbookColumnID ASC';
        $result = $pdo->executeQuery($data, $sql);
        $_GET['return'] = 'success0';
    } elseif(isset($_GET['reset'], $_GET['gibbonCourseClassID']) && $_GET['reset']==2){
        $data = array('gibbonCourseClassID' => $_GET['gibbonCourseClassID']);
        $sql = 'SET @count:=0;UPDATE gibbonMarkbookColumn SET `sequenceNumber`=@count:=@count+1 WHERE `gibbonCourseClassID` = :gibbonCourseClassID order by `date` ASC';
        $result = $pdo->executeQuery($data, $sql);
        $_GET['return'] = 'success0';
    }

   //Check for access to multiple column add
    $multiAdd = false;
    //Add multiple columns
    if (isActionAccessible($guid, $connection2, '/modules/Markbook/markbook_edit.php')) {
        if ($highestAction2 == 'Edit Markbook_multipleClassesAcrossSchool' or $highestAction2 == 'Edit Markbook_multipleClassesInDepartment' or $highestAction2 == 'Edit Markbook_everything') {
            //Check highest role in any department
            $isCoordinator = isDepartmentCoordinator( $pdo, $session->get('gibbonPersonID') );

            if ($isCoordinator == true or $highestAction2 == 'Edit Markbook_multipleClassesAcrossSchool' or $highestAction2 == 'Edit Markbook_everything') {
                $multiAdd = true;
            }
        }
    }

    //Get class variable
    $gibbonCourseClassID = null;
    if (isset($_GET['gibbonCourseClassID'])) {
        $gibbonCourseClassID = $_GET['gibbonCourseClassID'] ?? '';
    }

    if ($gibbonCourseClassID == '') {
    	$gibbonCourseClassID = $session->get('markbookClass') ?? '';
    }

    // Grab any taught class
    if ($gibbonCourseClassID == '') {
        $row = getAnyTaughtClass( $pdo, $session->get('gibbonPersonID'), $session->get('gibbonSchoolYearID') );
        $gibbonCourseClassID = (isset($row['gibbonCourseClassID']))? $row['gibbonCourseClassID'] : '';
    }

    if ($gibbonCourseClassID == '') {
        $page->breadcrumbs->add(__('View Markbook'));

        //Add multiple columns
        if ($multiAdd) {
            $params = [
                "gibbonCourseClassID" => $gibbonCourseClassID
            ];
            $page->navigator->addHeaderAction('addMulti', __('Add Multiple Columns'))
                ->setURL('/modules/Markbook/markbook_edit_addMulti.php')
                ->addParams($params)
                ->setIcon('page_new_multi')
                ->displayLabel();
        }
        //Get class chooser
        echo classChooser($guid, $pdo, $gibbonCourseClassID);
        return;
    }

    $session->set('markbookClass', $gibbonCourseClassID);

    //Check existence of and access to this class.
    $class = getClass($pdo, $session->get('gibbonPersonID'), $gibbonCourseClassID, $highestAction );

    if ($class == NULL) {
        $page->breadcrumbs->add(__('View Markbook'));

        //Get class chooser
        echo classChooser($guid, $pdo, $gibbonCourseClassID);

        if ($multiAdd == true) {
            $page->addError(__('The specified record does not exist.'));
        } else {
            $page->addError(__('Your request failed because you do not have access to this action.'));
        }

        return;
    }


    $courseName = $class['courseName'];
    $gibbonYearGroupIDList = $class['gibbonYearGroupIDList'];

    $page->breadcrumbs->add(empty($class)? __('View Markbook') : __('View {courseClass} Markbook', [
        'courseClass' => Format::courseClassName($class['course'], $class['class']),
    ]));


    //Get class chooser
    echo classChooser($guid, $pdo, $gibbonCourseClassID);

    $departmentAccess = $container->get(DepartmentGateway::class)->selectMemberOfDepartmentByRole($class['gibbonDepartmentID'], $session->get('gibbonPersonID'), ['Coordinator', 'Teacher (Curriculum)'])->fetch();

    //Get teacher list
    $teacherList = getTeacherList( $pdo, $gibbonCourseClassID );
	$canEditThisClass = (isset($teacherList[ $session->get('gibbonPersonID') ]) || $highestAction2 == 'Edit Markbook_everything' || ($highestAction2 == 'Edit Markbook_multipleClassesInDepartment' && !empty($departmentAccess)));

    // Get criteria filter values, including session defaults
    $search = $_GET['search'] ?? '';
    $gibbonSchoolYearTermID = $_GET['gibbonSchoolYearTermID'] ?? $session->get('markbookTerm') ?? '';
    $columnFilter = $_GET['markbookFilter'] ?? $session->get('markbookFilter') ?? '';
    $studentOrderBy = $_GET['markbookOrderBy'] ?? $session->get('markbookOrderBy') ?? 'preferredName';

    //Get the current page number
    $pageNum = $_GET['page'] ?? $session->get('markbookPage') ?? 0;
    $session->set('markbookPage', $pageNum);

    $markbookGateway = $container->get(MarkbookColumnGateway::class);
    $plannerGateway = $container->get(PlannerEntryGateway::class);
    $plannerHomeworkGateway = $container->get(PlannerEntryHomeworkGateway::class);

    // Build the markbook object for this class
    $markbook = new MarkbookView($gibbon, $pdo, $gibbonCourseClassID, $container->get(SettingGateway::class));

    // QUERY
    $criteria = $markbookGateway->newQueryCriteria(true)
        ->searchBy($markbookGateway->getSearchableColumns(), $search)
        ->sortBy(['gibbonMarkbookColumn.sequenceNumber', 'gibbonMarkbookColumn.date', 'gibbonMarkbookColumn.complete', 'gibbonMarkbookColumn.completeDate'])
        ->filterBy('term', $gibbonSchoolYearTermID)
        ->filterBy('show', $columnFilter)
        ->pageSize($markbook->getColumnsPerPage())
        ->page($pageNum+1)
        ->fromPOST();

    $columns = $markbookGateway->queryMarkbookColumnsByClass($criteria, $gibbonCourseClassID);
    $columns->transform(function (&$column) use ($plannerGateway) {
        if (isset($column['gibbonPlannerEntryID'])) {
            $column['gibbonPlannerEntry'] = $plannerGateway->getPlannerEntryByID($column['gibbonPlannerEntryID']);
        }
    });

    // Load the columns for the current page
    $markbook->loadColumnsFromDataSet($columns);

    // Collect columns array for template
    $columnsArray = [];
    for ($i = 0; $i < $markbook->getColumnCountThisPage(); ++$i) {
        $columnsArray[] = $markbook->getColumn($i);
    }

    // Load and cache data
    if (isActionAccessible($guid, $connection2, '/modules/Markbook/markbook_view.php') ) {

        // Pre-load homework data
        $homework = $plannerHomeworkGateway->selectHomeworkByClass($gibbonCourseClassID)->fetchGroupedUnique();

        // Cache all personalized target data
        $markbook->cachePersonalizedTargets();
        $markbook->cacheMarkbookEntries();

        // Cache all weighting data for efficient use below
        if ($markbook->getSetting('enableColumnWeighting') == 'Y') {
            $markbook->cacheWeightings();
        }

        // Work out details for external assessment display
        if (isActionAccessible($guid, $connection2, '/modules/Formal Assessment/externalAssessment_details.php')) {
            $markbook->cacheExternalAssessments($courseName, $gibbonYearGroupIDList);
        }

    }

    // Initialize template data array structure
    $templateData = [
        // Session and URLs
        'absoluteURL' => $session->get('absoluteURL'),
        'absolutePath' => $session->get('absolutePath'),
        'guid' => $guid,
        'connection2' => $connection2,
        
        // Class context
        'gibbonCourseClassID' => $gibbonCourseClassID,
        'courseName' => $courseName,
        'className' => $class['class'],
        'course' => $class['course'],
        'class' => $class['class'],
        
        // Markbook instance and settings
        'markbook' => $markbook,
        'enableColumnWeighting' => $markbook->getSetting('enableColumnWeighting'),
        'enableModifiedAssessment' => $enableModifiedAssessment ?? $markbook->getSetting('enableModifiedAssessment'),
        'enableRubrics' => $enableRubrics ?? $markbook->getSetting('enableRubrics'),
        'enableRawAttainment' => $markbook->getSetting('enableRawAttainment'),
        'enableGroupByTerm' => $markbook->getSetting('enableGroupByTerm'),
        'enableTypeWeighting' => $markbook->getSetting('enableTypeWeighting'),
        'attainmentName' => $markbook->getSetting('attainmentName'),
        'attainmentAbrev' => $markbook->getSetting('attainmentAbrev'),
        'effortName' => $markbook->getSetting('effortName'),
        'effortAbrev' => $markbook->getSetting('effortAbrev'),
        
        // Filters and pagination
        'columnFilter' => $columnFilter,
        'studentOrderBy' => $studentOrderBy,
        'gibbonSchoolYearTermID' => $gibbonSchoolYearTermID,
        'pageNum' => $pageNum,
        'markbookTermName' => $session->get('markbookTermName'),
        
        // Permissions
        'canEditThisClass' => $canEditThisClass,
        'multiAdd' => $multiAdd,
        
        // External assessments
        'hasExternalAssessments' => $markbook->hasExternalAssessments(),
        'externalAssessmentFields' => $markbook->getExternalAssessments(),
        
        // Personalized targets
        'hasPersonalizedTargets' => $markbook->hasPersonalizedTargets(),
        
        // Columns
        'columns' => $columnsArray,
        'columnCountTotal' => $markbook->getColumnCountTotal(),
        'columnCountThisPage' => $markbook->getColumnCountThisPage(),
        'columnsPerPage' => $markbook->getColumnsPerPage(),
        'minimumSequenceNumber' => $markbook->getMinimumSequenceNumber(),
        
        // Students (to be populated)
        'students' => [],
        
        // Teacher list
        'teacherList' => $teacherList,
        
        // Totals (to be populated)
        'totals' => [],
        'count' => 0,
    ];


    // Display Pagination
    echo "<div class='linkTop flex justify-between items-center mt-4'>";
    
    // Print table header info
    echo '<p class="pr-4 text-xs text-gray-600 text-left">';
        if (!empty($teacherList)) {
            echo '<span class="text-sm font-semibold text-gray-800">'.sprintf(__('Class taught by %1$s'), implode(', ', $teacherList) ).'</span>.<br/>';
        }
        if ($markbook->getColumnCountTotal() > $markbook->getColumnsPerPage()) {
            echo __('To see more detail on an item (such as a comment or a grade), hover your mouse over it. To see more columns, use the Newer and Older links.');
        } else {
            echo __('To see more detail on an item (such as a comment or a grade), hover your mouse over it.');
        }
        
        if ($markbook->hasExternalAssessments() == true) {
            echo ' '.__('The Baseline column is populated based on student performance in external assessments, and can be used as a reference point for the grades in the markbook.');
        }
    echo '</p>';

    // Display the Top Links
    if (isActionAccessible($guid, $connection2, '/modules/Markbook/markbook_edit.php') and $canEditThisClass) {
        echo '<script>
            function resetOrder(){
                $( "#dialog" ).dialog();
            }
            function resetOrderAction(order){
                if(order==1){
                    window.location.href = window.location.href.substr(0,window.location.href.length-1) + "&gibbonCourseClassID='.$gibbonCourseClassID.'&reset=1";
                }else if(order==2){
                    window.location.href = window.location.href.substr(0,window.location.href.length-1) + "&gibbonCourseClassID='.$gibbonCourseClassID.'&reset=2";
                }
            }
        </script>';
        echo '<div id="dialog" title="'.__('Reset Order').'" style="display:none;">
            '.__('Are you sure you want to reset the ordering of all the columns in this class?').'<br>
            <button onclick="resetOrderAction(1)" class="my-2 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">'.__('Reset by entry order').'</button><br>
            <button onclick="resetOrderAction(2)" class="my-2 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">'.__('Reset by date').'</button>
        </div>';

        $form = Form::create('links', '');

        $form->addHeaderAction('add', __('Add'))
            ->setURL('/modules/Markbook/markbook_edit_add.php')
            ->addParam('gibbonCourseClassID', $gibbonCourseClassID)
            ->displayLabel();

        if ($multiAdd) {
            $form->addHeaderAction('addMulti', __('Add Multiple'))
                ->setURL('/modules/Markbook/markbook_edit_addMulti.php')
                ->addParam('gibbonCourseClassID', $gibbonCourseClassID)
                ->setIcon('page_new_multi')
                ->displayLabel();
        }

        $form->addHeaderAction('target', __('Targets'))
            ->setURL('/modules/Markbook/markbook_edit_targets.php')
            ->addParam('gibbonCourseClassID', $gibbonCourseClassID)
            ->displayLabel();

        if ($markbook->getSetting('enableColumnWeighting') == 'Y' && isActionAccessible($guid, $connection2, '/modules/Markbook/weighting_manage.php') == true) {
            $form->addHeaderAction('config', __('Weightings'))
                ->setURL('/modules/Markbook/weighting_manage.php')
                ->addParam('gibbonCourseClassID', $gibbonCourseClassID)
                ->displayLabel();
        }

        if ($markbook->getColumnCountTotal() > $markbook->getColumnsPerPage()) {
            $form->addHeaderAction('refresh', __('Reset Order'))
                ->onClick('resetOrder()')
                ->setURL('#')
                ->displayLabel();
        }

        if ($markbook->getColumnCountTotal() > 0) {
            $form->addHeaderAction('export', __('Export'))
                ->setURL('/modules/Markbook/markbook_viewExportAll.php')
                ->addParam('gibbonCourseClassID', $gibbonCourseClassID)
                ->addParam('return', 'markbook_view.php')
                ->directLink()
                ->displayLabel();
        }

        echo $form->getOutput();
    }
    echo '</div>';

    if ($markbook == NULL || $markbook->getColumnCountTotal() < 1) {
        echo Format::alert(__('There are no records to display.'), 'empty');
        return;
    } else {

        

        // Check to see if we have no columns to display. This can happen if the page number is incorrect.
        // Do this here so users still have access to buttons.
        if ($markbook->getColumnCountThisPage() <= 0) {
            echo Format::alert(__('There are no records to display.'), 'empty');
            return;
        }

        // Collect column metadata for template
        $columnMetadata = [];
        for ($i = 0; $i < $markbook->getColumnCountThisPage(); ++$i) {
            $column = $markbook->getColumn( $i );
            $columnType = $column->getData('type');
            $unit = getUnit($connection2, $column->getData('gibbonUnitID'), '', $column->getData('gibbonCourseClassID') );

            // Build tooltip info
            $info = '<div class="font-bold text-sm leading-6 mb-2">'.$column->getData('description').'</div>';
            $info .= '<ul class="m-0 ml-4 w-48 text-xs">';
            $info .= '<li>'.__('Type').' - '.$markbook->getTypeDescription( $columnType ) .'</li>';

            $weightInfo = '';
            $includeMarks = !empty($column->getData('completeDate'));

            if (isset($unit[0])) {
                $info .= '<li>'.__('Unit').' - '. $unit[0] .'</li>';
            }

            if ($markbook->getSetting('enableGroupByTerm') == 'Y' && $column->getData('date') != '') {
                $info .= '<li>'. __('Assigned on ').' '.Format::date($column->getData('date') ).'</li>';
            }

            if ($column->getData('completeDate') != '') {
                $info .= '<li>'. __('Marked on').' '.Format::date($column->getData('completeDate') ).'</li>';
            } else {
                $info .= '<li>'. __('Unmarked').'</li>';
                $weightInfo .= __('Unmarked').'<br/>';
                $includeMarks = false;
            }

            if ($markbook->getSetting('enableColumnWeighting') == 'Y' ) {
                $info .= '<li>'. __('Column Weighting').' '.floatval( $column->getData('attainmentWeighting') ).'</li>';

                if ($column->hasAttainmentWeighting() == false) {
                    $weightInfo .= __('Column Weighting').' '.floatval( $column->getData('attainmentWeighting') ).'<br/>';
                    $includeMarks = false;
                }
            }

            if ($markbook->getSetting('enableTypeWeighting') == 'Y' ) {
                $info .= '<li>'. __('Type Weighting').' '.floatval( $markbook->getWeightingByType($columnType) ).'</li>';

                if ( empty($markbook->getWeightingByType($columnType))) {
                    $weightInfo .= __('Type Weighting').' '.floatval( $markbook->getWeightingByType($columnType) ).'<br/>';
                    $includeMarks = false;
                }
            }

            if ($markbook->getReportableByType($columnType) == 'N'  ) {
                $weightInfo .= __('Reportable').'? '.$markbook->getReportableByType($columnType).'<br/>';
                $includeMarks = false;
            }

            $info .= '</ul>';

            // Collect scale information for attainment
            $attainmentScale = '';
            if ($column->displayAttainment()) {
                if ($markbook->getSetting('enableRawAttainment') == 'Y' && $session->has('markbookFilter') ) {
                    if ($session->get('markbookFilter') == 'raw' && $column->displayRawMarks() and $column->hasAttainmentRawMax()) {
                        $attainmentScale = ' - ' . __('Raw Marks') .' '. __('out of') .': '. floatval($column->getData('attainmentRawMax') );
                    }
                }

                if (empty($attainmentScale)) {
                    $dataScale = array('gibbonScaleID' => $column->getData('gibbonScaleIDAttainment'));
                    $sqlScale = 'SELECT * FROM gibbonScale WHERE gibbonScaleID=:gibbonScaleID';
                    $resultScale = $connection2->prepare($sqlScale);
                    $resultScale->execute($dataScale);

                    if ($resultScale->rowCount() == 1) {
                        $rowScale = $resultScale->fetch();
                        $attainmentScale = ' - '.$rowScale['name'];
                        if ($rowScale['usage'] != '') {
                            $attainmentScale = $attainmentScale.': '.$rowScale['usage'];
                        }
                    }
                }
            }

            // Collect scale information for effort
            $effortScale = '';
            if ($column->displayEffort()) {
                $dataScale = array('gibbonScaleID' => $column->getData('gibbonScaleIDEffort'));
                $sqlScale = 'SELECT * FROM gibbonScale WHERE gibbonScaleID=:gibbonScaleID';
                $resultScale = $connection2->prepare($sqlScale);
                $resultScale->execute($dataScale);
                
                if ($resultScale->rowCount() == 1) {
                    $rowScale = $resultScale->fetch();
                    $effortScale = ' - '.$rowScale['name'];
                    if ($rowScale['usage'] != '') {
                        $effortScale = $effortScale.': '.$rowScale['usage'];
                    }
                }
            }

            if ($includeMarks) {
                $weightInfo = __('Marked on').' '.Format::date($column->getData('completeDate') ).'<br/>'.$weightInfo;
            } else {
                if ($markbook->getSetting('enableColumnWeighting') == 'Y' ) {
                    $weightInfo = '<strong>'.__('Excluded from averages').':</strong><br/>'. $weightInfo;
                }
            }

            $columnMetadata[] = [
                'column' => $column,
                'unit' => $unit,
                'info' => $info,
                'weightInfo' => $weightInfo,
                'includeMarks' => $includeMarks,
                'attainmentScale' => $attainmentScale,
                'effortScale' => $effortScale,
            ];
        }

        $templateData['columnMetadata'] = $columnMetadata;

        // Get external assessment fields for template
        $externalAssessmentFields = $markbook->getExternalAssessments();
        $templateData['externalAssessmentFields'] = $externalAssessmentFields;

        // Get default assessment scale for template
        $templateData['defaultAssessmentScale'] = $markbook->getDefaultAssessmentScale();

        try {
            if ($studentOrderBy == 'rollOrder') {
                $dataStudents = array('gibbonCourseClassID' => $gibbonCourseClassID, 'gibbonSchoolYearID'=>$session->get('gibbonSchoolYearID') );
                $sqlStudents = "SELECT title, surname, preferredName, gibbonPerson.gibbonPersonID, dateStart, rollOrder, dateEnrolled, dateUnenrolled FROM gibbonCourseClassPerson INNER JOIN gibbonPerson ON (gibbonCourseClassPerson.gibbonPersonID=gibbonPerson.gibbonPersonID) LEFT JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonCourseClassPerson.gibbonPersonID) WHERE role='Student' AND gibbonCourseClassID=:gibbonCourseClassID AND status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND gibbonSchoolYearID=:gibbonSchoolYearID ORDER BY ISNULL(rollOrder), rollOrder, surname, preferredName";
            } else if ($studentOrderBy == 'surname') {
                $dataStudents = array('gibbonCourseClassID' => $gibbonCourseClassID);
                $sqlStudents = "SELECT title, surname, preferredName, gibbonPerson.gibbonPersonID, dateStart, dateEnrolled, dateUnenrolled FROM gibbonCourseClassPerson JOIN gibbonPerson ON (gibbonCourseClassPerson.gibbonPersonID=gibbonPerson.gibbonPersonID) WHERE role='Student' AND gibbonCourseClassID=:gibbonCourseClassID AND status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') ORDER BY surname, preferredName";
            } else {
                $dataStudents = array('gibbonCourseClassID' => $gibbonCourseClassID);
                $sqlStudents = "SELECT title, surname, preferredName, gibbonPerson.gibbonPersonID, dateStart, dateEnrolled, dateUnenrolled FROM gibbonCourseClassPerson JOIN gibbonPerson ON (gibbonCourseClassPerson.gibbonPersonID=gibbonPerson.gibbonPersonID) WHERE role='Student' AND gibbonCourseClassID=:gibbonCourseClassID AND status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') ORDER BY preferredName, surname";
            }

            $resultStudents = $connection2->prepare($sqlStudents);
            $resultStudents->execute($dataStudents);
        } catch (PDOException $e) {
        }

        $count = 0;
        $totals = array();

        if ($resultStudents->rowCount() < 1) {
            // No students to display - will be handled in template
            $templateData['students'] = [];
        } else {
            while ($rowStudents = $resultStudents->fetch()) {
                ++$count;

                // Initialize student data structure
                $studentData = [
                    'gibbonPersonID' => $rowStudents['gibbonPersonID'],
                    'title' => $rowStudents['title'],
                    'surname' => $rowStudents['surname'],
                    'preferredName' => $rowStudents['preferredName'],
                    'rollOrder' => $rowStudents['rollOrder'] ?? null,
                    'dateStart' => $rowStudents['dateStart'],
                    'dateEnrolled' => $rowStudents['dateEnrolled'] ?? null,
                    'dateUnenrolled' => $rowStudents['dateUnenrolled'] ?? null,
                    'rowNumber' => $count,
                    'baseline' => null,
                    'target' => null,
                    'entries' => [],
                    'averages' => [
                        'typeAverages' => [],
                        'termAverages' => [],
                        'cumulativeAverage' => '',
                        'finalGradeAverage' => '',
                    ],
                ];

                // Collect baseline data when external assessments exist
                if ($markbook->hasExternalAssessments() == true) {
                    $studentData['baseline'] = $markbook->getExternalAssessmentByStudent($rowStudents['gibbonPersonID']);
                }

                // Collect target data when personalized targets exist
                if ($markbook->hasPersonalizedTargets()) {
                    $studentData['target'] = $markbook->getTargetForStudent($rowStudents['gibbonPersonID']);
                }

                // The main markbook loop - iterate over each student's markbook entry per column
                for ($i = 0; $i < $markbook->getColumnCountThisPage(); ++$i) {

                	$column = $markbook->getColumn( $i );

                    $rowEntry = $markbook->getMarkbookEntryByColumnAndStudent($column->gibbonMarkbookColumnID, $rowStudents['gibbonPersonID']);

                    $rowWork = [];
                    if ($column->displaySubmission()) {
                        $key = $column->gibbonMarkbookColumnID.'-'.$rowStudents['gibbonPersonID'];
                        $rowWork = $homework[$key] ?? [];
                    }

                    $newEnrollment = false;

                    // Check if class enrolment date exists and is after the Go Live date for this column
                    if (!empty($rowStudents['dateEnrolled']) && !empty($column->getData('completeDate')) && $rowStudents['dateEnrolled'] > $column->getData('completeDate')) {
                        $newEnrollment = true;
                    }
                    
                    // Check if student enrolment date is after the Go Live date for this column
                    if (!empty($column->getData('completeDate')) && $rowStudents['dateStart'] > $column->getData('completeDate')) {
                        $newEnrollment = true;
                    }

                    // Check if this student doesn't have an entry, and the Go Live date has passed
                    if (empty($rowEntry) && empty($rowWork) && !empty($column->getData('completeDate')) && date('Y-m-d') >= $column->getData('completeDate') && (empty($column->getData('lessonDate')) || $rowStudents['dateStart'] >= $column->getData('lessonDate') ) ) {
                        $newEnrollment = true;
                    }

                    // Check if student does actually have data for this column
                    if ($newEnrollment && (!empty($rowEntry['attainmentValue']) || !empty($rowEntry['effortValue']) || !empty($rowEntry['comment']) || !empty($rowEntry['response'])) || !empty($rowWork)) {
                        $newEnrollment = false;
                    }

                    // Build entry data structure
                    $entryData = [
                        'columnIndex' => $i,
                        'newEnrollment' => $newEnrollment,
                        'exists' => !empty($rowEntry),
                        'submission' => !empty($rowWork) ? $rowWork : null,
                    ];

                    // Collect entry data if it exists
                    if (!empty($rowEntry)) {
                        $entryData['modifiedAssessment'] = $rowEntry['modifiedAssessment'] ?? null;
                        $entryData['attainmentValue'] = $rowEntry['attainmentValue'] ?? null;
                        $entryData['attainmentDescriptor'] = $rowEntry['attainmentDescriptor'] ?? null;
                        $entryData['attainmentConcern'] = $rowEntry['attainmentConcern'] ?? null;
                        $entryData['attainmentValueRaw'] = $rowEntry['attainmentValueRaw'] ?? null;
                        $entryData['effortValue'] = $rowEntry['effortValue'] ?? null;
                        $entryData['effortDescriptor'] = $rowEntry['effortDescriptor'] ?? null;
                        $entryData['effortConcern'] = $rowEntry['effortConcern'] ?? null;
                        $entryData['comment'] = $rowEntry['comment'] ?? null;
                        $entryData['response'] = $rowEntry['response'] ?? null;

                        if ($entryData['attainmentValue'] == 'Complete') $entryData['attainmentValue'] = __('Com');
                        if ($entryData['attainmentValue'] == 'Incomplete') $entryData['attainmentValue'] = __('Inc');
                        
                        if ($entryData['effortValue'] == 'Complete') $entryData['effortValue'] = __('Com');
                        if ($entryData['effortValue'] == 'Incomplete') $entryData['effortValue'] = __('Inc');

                        // Calculate totals for attainment
                        if ($column->hasAttainmentGrade()) {
                            $attainment = '';
                            if ($rowEntry['attainmentValue'] != '') {
                                $attainment = $rowEntry['attainmentValue'];
                            }

                            if ($markbook->getSetting('enableRawAttainment') == 'Y' && $column->displayRawMarks() && $column->hasAttainmentRawMax()) {
                                if (isset($rowEntry['attainmentValueRaw']) && !empty($rowEntry['attainmentValueRaw'])) {
                                    if ($session->get('markbookFilter') == 'raw') {
                                        $attainment = $rowEntry['attainmentValueRaw'];
                                    }
                                }
                            }

                            if ($attainment !== '' &&  is_numeric(rtrim($attainment, "%"))) {
                                @$totals['attainment'][$i]['total'] += floatval($attainment);
                                @$totals['attainment'][$i]['count'] += 1;
                            }
                        }
                    } else {
                        // Entry doesn't exist - set all fields to null
                        $entryData['modifiedAssessment'] = null;
                        $entryData['attainmentValue'] = null;
                        $entryData['attainmentDescriptor'] = null;
                        $entryData['attainmentConcern'] = null;
                        $entryData['attainmentValueRaw'] = null;
                        $entryData['effortValue'] = null;
                        $entryData['effortDescriptor'] = null;
                        $entryData['effortConcern'] = null;
                        $entryData['comment'] = null;
                        $entryData['response'] = null;
                    }

                    // Add entry to student's entries array
                    $studentData['entries'][$i] = $entryData;
                }

                // Collect averages data for this student
                if ($markbook->getSetting('enableColumnWeighting') == 'Y' && $columnFilter != 'unmarked') {

                    // Collect overall term and category averages
                    if ($columnFilter == 'averages') {

                        if ($markbook->getSetting('enableTypeWeighting') == 'Y' ) {
                            if ( ($markbook->getSetting('enableGroupByTerm') == 'Y' && $gibbonSchoolYearTermID > 0) ||
                                 ($markbook->getSetting('enableGroupByTerm') == 'N' && $gibbonSchoolYearTermID <= 0) ) {

                                // Collect all used column types
                                foreach ($markbook->getGroupedMarkbookTypes('term') as $type) {
                                    $typeAverage = $markbook->getTypeAverage($rowStudents['gibbonPersonID'], $gibbonSchoolYearTermID, $type);
                                    $studentData['averages']['typeAverages'][$type] = $typeAverage;
                                    @$totals['typeAverage'][$type] += floatval($typeAverage);
                                }
                            }
                        } else if (count($markbook->getGroupedMarkbookTypes('year')) > 0 && $gibbonSchoolYearTermID > 0) {
                            foreach ($markbook->getGroupedMarkbookTypes('year') as $type) {
                                $typeAverage = $markbook->getTypeAverage($rowStudents['gibbonPersonID'], $gibbonSchoolYearTermID, $type);
                                $studentData['averages']['typeAverages'][$type] = $typeAverage;
                            }
                        }
                    }

                    if ( ($markbook->getSetting('enableGroupByTerm') == 'Y' && $gibbonSchoolYearTermID <= 0) ) {
                        foreach ($markbook->getCurrentTerms() as $term) {
                            $termAverage = $markbook->getTermAverage($rowStudents['gibbonPersonID'], $term['gibbonSchoolYearTermID']);
                            $studentData['averages']['termAverages'][$term['gibbonSchoolYearTermID']] = $termAverage;
                            @$totals['termAverage'][$term['gibbonSchoolYearTermID']] += floatval($termAverage);
                        }
                    }
                    

                    if ($markbook->getSetting('enableGroupByTerm') == 'Y' && $gibbonSchoolYearTermID > 0) {
                        $termAverage = $markbook->getTermAverage($rowStudents['gibbonPersonID'], $gibbonSchoolYearTermID);
                        $studentData['averages']['termAverages'][$gibbonSchoolYearTermID] = $termAverage;
                        @$totals['termAverage'][$gibbonSchoolYearTermID] += floatval($termAverage);
                    }

                    $cumulativeAverage = $markbook->getCumulativeAverage($rowStudents['gibbonPersonID']);
                    $studentData['averages']['cumulativeAverage'] = $cumulativeAverage;
                    if ($cumulativeAverage != '') {
                        @$totals['cumulativeAverage'] += floatval($cumulativeAverage);
                        @$totals['count'] += 1;
                    }

                    if ($markbook->getSetting('enableTypeWeighting') == 'Y' && count($markbook->getGroupedMarkbookTypes('year')) > 0 && $gibbonSchoolYearTermID <= 0) {

                        if ($columnFilter == 'averages') {
                            foreach ($markbook->getGroupedMarkbookTypes('year') as $type) {
                                $typeAverage = $markbook->getTypeAverage($rowStudents['gibbonPersonID'], 'final', $type);
                                $studentData['averages']['typeAverages'][$type . '_final'] = $typeAverage;
                                @$totals[$type] += floatval($typeAverage);
                            }
                        }

                        $finalGradeAverage = $markbook->getFinalGradeAverage($rowStudents['gibbonPersonID']);
                        $studentData['averages']['finalGradeAverage'] = $finalGradeAverage;
                        @$totals['finalGrade'] += floatval($finalGradeAverage);
                    }
                }

                // Add student data to the students array
                $templateData['students'][] = $studentData;
            }
        }

        // Store totals and count in template data
        $templateData['totals'] = $totals;
        $templateData['count'] = $count;

        // Render the template
        echo $page->fetchFromTemplate('markbook_view.twig.html', $templateData);

    }
