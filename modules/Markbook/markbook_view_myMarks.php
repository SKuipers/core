<?php

/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

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

$page->breadcrumbs->add(__('View Markbook'));

// Lock the file so other scripts cannot call it
if (MARKBOOK_VIEW_LOCK !== sha1( $highestAction . $_SESSION[$guid]['gibbonPersonID'] ) . date('zWy') ) return;

    //Get settings
    $enableEffort = getSettingByScope($connection2, 'Markbook', 'enableEffort');
    $enableRubrics = getSettingByScope($connection2, 'Markbook', 'enableRubrics');
    $showStudentAttainmentWarning = getSettingByScope($connection2, 'Markbook', 'showStudentAttainmentWarning');
    $showStudentEffortWarning = getSettingByScope($connection2, 'Markbook', 'showStudentEffortWarning');
    $attainmentAltName = getSettingByScope($connection2, 'Markbook', 'attainmentAlternativeName');
	$effortAltName = getSettingByScope($connection2, 'Markbook', 'effortAlternativeName');

    $entryCount = 0;
    echo '<p>';
    echo __('This page shows you your academic results throughout your school career. Only subjects with published results are shown.');
    echo '</p>';

    $and = '';
    $and2 = '';
    $dataList = array();
    $dataEntry = array();

    $filter = isset($_REQUEST['filter'])? $_REQUEST['filter'] : $_SESSION[$guid]['gibbonSchoolYearID'];
    if ($filter != '*') {
        $dataList['filter'] = $filter;
        $and .= ' AND gibbonSchoolYearID=:filter';
    }

    $filter2 = isset($_REQUEST['filter2'])? $_REQUEST['filter2'] : '*';
    if ($filter2 != '*') {
        $dataList['filter2'] = $filter2;
        $and .= ' AND gibbonDepartmentID=:filter2';
    }

    $filter3 = isset($_REQUEST['filter3'])? $_REQUEST['filter3'] : '';
    if ($filter3 != '') {
        $dataEntry['filter3'] = $filter3;
        $and2 .= ' AND type=:filter3';
    }

    $form = Form::create('filter', $_SESSION[$guid]['absoluteURL'].'/index.php','get');
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/'.$_SESSION[$guid]['module'].'/markbook_view.php');

    $sqlSelect = "SELECT gibbonDepartmentID as value, name FROM gibbonDepartment WHERE type='Learning Area' ORDER BY name";
    $rowFilter = $form->addRow();
        $rowFilter->addLabel('filter2', __('Learning Areas'));
        $rowFilter->addSelect('filter2')
            ->fromArray(array('*' => __('All Learning Areas')))
            ->fromQuery($pdo, $sqlSelect)
            ->selected($filter2);

    $dataSelect = array('gibbonPersonID' => $_SESSION[$guid]['gibbonPersonID']);
    $sqlSelect = "SELECT gibbonSchoolYear.gibbonSchoolYearID as value, CONCAT(gibbonSchoolYear.name, ' (', gibbonYearGroup.name, ')') AS name FROM gibbonStudentEnrolment JOIN gibbonSchoolYear ON (gibbonStudentEnrolment.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID) JOIN gibbonYearGroup ON (gibbonStudentEnrolment.gibbonYearGroupID=gibbonYearGroup.gibbonYearGroupID) WHERE gibbonPersonID=:gibbonPersonID ORDER BY gibbonSchoolYear.sequenceNumber";
    $rowFilter = $form->addRow();
        $rowFilter->addLabel('filter', __('School Years'));
        $rowFilter->addSelect('filter')
            ->fromArray(array('*' => __('All Years')))
            ->fromQuery($pdo, $sqlSelect, $dataSelect)
            ->selected($filter);

    $types = getSettingByScope($connection2, 'Markbook', 'markbookType');
    if (!empty($types)) {
        $rowFilter = $form->addRow();
        $rowFilter->addLabel('filter3', __('Type'));
        $rowFilter->addSelect('filter3')
            ->fromString($types)
            ->selected($filter3)
            ->placeholder();
    }

    $details = isset($_GET['details'])? $_GET['details'] : 'Yes';
    $form->addHiddenValue('details', 'No');
    $showHide = $form->getFactory()->createCheckbox('details')->addClass('details')->setValue('Yes')->checked($details)->inline(true)
        ->description(__('Show/Hide Details'))->wrap('&nbsp;<span class="small emphasis displayInlineBlock">', '</span> &nbsp;&nbsp;');

    $rowFilter = $form->addRow();
        $rowFilter->addSearchSubmit($gibbon->session, __('Clear Filters'))->prepend($showHide->getOutput());

    echo $form->getOutput();

    ?>
    <script type="text/javascript">
        /* Show/Hide detail control */
        $(document).ready(function(){
            var updateDetails = function (){
                if ($('input[name=details]:checked').val()=="Yes" ) {
                    $(".detailItem").slideDown("fast", $(".detailItem").css("{'display' : 'table-row'}"));
                }
                else {
                    $(".detailItem").slideUp("fast");
                }
            }
            $(".details").click(updateDetails);
            updateDetails();
        });
    </script>
    <?php

    //Get class list
    
        $dataList['gibbonPersonID'] = $_SESSION[$guid]['gibbonPersonID'];
        $dataList['gibbonPersonID2'] = $_SESSION[$guid]['gibbonPersonID'];
        $sqlList = "SELECT gibbonCourse.nameShort AS course, gibbonCourseClass.nameShort AS class, gibbonCourse.name, gibbonCourseClass.gibbonCourseClassID, gibbonScaleGrade.value AS target, gibbonPerson.dateStart
        FROM gibbonCourse
        JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID)
        JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
        JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonCourseClassPerson.gibbonPersonID)
        LEFT JOIN gibbonMarkbookTarget ON (gibbonMarkbookTarget.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND gibbonMarkbookTarget.gibbonPersonIDStudent=:gibbonPersonID2) LEFT JOIN gibbonScaleGrade ON (gibbonMarkbookTarget.gibbonScaleGradeID=gibbonScaleGrade.gibbonScaleGradeID)
        WHERE gibbonCourseClassPerson.gibbonPersonID=:gibbonPersonID $and ORDER BY course, class";
        $resultList = $connection2->prepare($sqlList);
        $resultList->execute($dataList);
    if ($resultList->rowCount() > 0) {
        while ($rowList = $resultList->fetch()) {
            
                $dataEntry['gibbonPersonIDStudent'] = $_SESSION[$guid]['gibbonPersonID'];
                $dataEntry['gibbonCourseClassID'] = $rowList['gibbonCourseClassID'];
                $dataEntry['today'] = date('Y-m-d');
                $sqlEntry = "SELECT *, gibbonMarkbookColumn.comment AS commentOn, gibbonMarkbookColumn.uploadedResponse AS uploadedResponseOn, gibbonMarkbookEntry.comment AS comment, (SELECT description FROM gibbonMarkbookWeight WHERE gibbonMarkbookWeight.gibbonCourseClassID=gibbonMarkbookColumn.gibbonCourseClassID AND gibbonMarkbookWeight.type=gibbonMarkbookColumn.type LIMIT 1) as typeName
                FROM gibbonMarkbookEntry 
                JOIN gibbonMarkbookColumn ON (gibbonMarkbookEntry.gibbonMarkbookColumnID=gibbonMarkbookColumn.gibbonMarkbookColumnID) 
                WHERE gibbonMarkbookEntry.gibbonPersonIDStudent=:gibbonPersonIDStudent 
                AND gibbonMarkbookColumn.gibbonCourseClassID=:gibbonCourseClassID 
                AND gibbonMarkbookColumn.viewableStudents='Y'
                AND complete='Y' AND completeDate<=:today $and2  
                ORDER BY completeDate";
                $resultEntry = $connection2->prepare($sqlEntry);
                $resultEntry->execute($dataEntry);
            if ($resultEntry->rowCount() > 0) {
                echo '<h4>'.$rowList['course'].'.'.$rowList['class']." <span style='font-size:85%; font-style: italic'>(".$rowList['name'].')</span></h4>';

                
                    $dataTeachers = array('gibbonCourseClassID' => $rowList['gibbonCourseClassID']);
                    $sqlTeachers = "SELECT title, surname, preferredName FROM gibbonPerson JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonPersonID=gibbonPerson.gibbonPersonID) WHERE role='Teacher' AND gibbonCourseClassID=:gibbonCourseClassID ORDER BY surname, preferredName";
                    $resultTeachers = $connection2->prepare($sqlTeachers);
                    $resultTeachers->execute($dataTeachers);

                $teachers = '<p><b>'.__('Taught by:').'</b> ';
                while ($rowTeachers = $resultTeachers->fetch()) {
                    $teachers = $teachers.Format::name($rowTeachers['title'], $rowTeachers['preferredName'], $rowTeachers['surname'], 'Staff', false, false).', ';
                }
                $teachers = substr($teachers, 0, -2);
                $teachers = $teachers.'</p>';
                echo $teachers;

                if ($rowList['target'] != '') {
                    echo "<div style='font-weight: bold' class='linkTop'>";
                    echo __('Target').': '.$rowList['target'];
                    echo '</div>';
                }

                echo "<table cellspacing='0' style='width: 100%'>";
                echo "<tr class='head'>";
                echo "<th style='width: 120px'>";
                    echo __('Assessment');
                echo '</th>';
                if ($enableModifiedAssessment == 'Y') {
                    echo "<th style='width: 75px'>";
                        echo __('Modified');
                    echo '</th>';
                }
                echo "<th style='width: 75px; text-align: center'>";
                    echo (!empty($attainmentAltName))? $attainmentAltName : __('Attainment');
                echo '</th>';
                if ($enableEffort == 'Y') {
                    echo "<th style='width: 75px; text-align: center'>";
                        echo (!empty($effortAltName))? $effortAltName : __('Effort');
                    echo '</th>';
                }
                echo '<th>';
                    echo __('Comment');
                echo '</th>';
                echo "<th style='width: 75px'>";
                    echo __('Submission');
                echo '</th>';
                echo '</tr>';

                $count = 0;
                while ($rowEntry = $resultEntry->fetch()) {
                    if ($count % 2 == 0) {
                        $rowNum = 'even';
                    } else {
                        $rowNum = 'odd';
                    }
                    ++$count;
                    ++$entryCount;

                    echo "<a name='".$rowEntry['gibbonMarkbookEntryID']."'></a>";
                    echo "<tr class=$rowNum>";
                    echo '<td>';
                    echo "<span title='".htmlPrep($rowEntry['description'])."'><b><u>".$rowEntry['name'].'</u></b></span><br/>';
                    echo "<span style='font-size: 90%; font-style: italic; font-weight: normal'>";
                    $unit = getUnit($connection2, $rowEntry['gibbonUnitID'], $rowEntry['gibbonCourseClassID']);
                    if (isset($unit[0])) {
                        echo $unit[0].'<br/>';
                        if ($unit[1] != '') {
                            echo '<i>'.$unit[1].' '.__('Unit').'</i><br/>';
                        }
                    }
                    if ($rowEntry['completeDate'] != '') {
                        echo __('Marked on').' '.dateConvertBack($guid, $rowEntry['completeDate']).'<br/>';
                    } else {
                        echo __('Unmarked').'<br/>';
                    }
                    echo (!empty($rowEntry['typeName']))? ucfirst($rowEntry['typeName']) : ucfirst($rowEntry['type']);
                    if ($rowEntry['attachment'] != '' and file_exists($_SESSION[$guid]['absolutePath'].'/'.$rowEntry['attachment'])) {
                        echo " | <a 'title='".__('Download more information')."' href='".$_SESSION[$guid]['absoluteURL'].'/'.$rowEntry['attachment']."'>".__('More info').'</a>';
                    }
                    echo '</span><br/>';
                    echo '</td>';
                    if ($enableModifiedAssessment == 'Y') {
                        if (!is_null($rowEntry['modifiedAssessment'])) {
                            echo "<td>";
                            echo ynExpander($guid, $rowEntry['modifiedAssessment']);
                            echo '</td>';
                        }
                        else {
                            echo "<td class='dull' style='color: #bbb; text-align: center'>";
                            echo __('N/A');
                            echo '</td>';
                        }
                    }
                    if ($rowEntry['attainment'] == 'N' or ($rowEntry['gibbonScaleIDAttainment'] == '' and $rowEntry['gibbonRubricIDAttainment'] == '')) {
                        echo "<td class='dull' style='color: #bbb; text-align: center'>";
                        echo __('N/A');
                        echo '</td>';
                    } else {
                        echo "<td style='text-align: center'>";
                        $attainmentExtra = '';
                        
                            $dataAttainment = array('gibbonScaleID' => $rowEntry['gibbonScaleIDAttainment']);
                            $sqlAttainment = 'SELECT * FROM gibbonScale WHERE gibbonScaleID=:gibbonScaleID';
                            $resultAttainment = $connection2->prepare($sqlAttainment);
                            $resultAttainment->execute($dataAttainment);
                        if ($resultAttainment->rowCount() == 1) {
                            $rowAttainment = $resultAttainment->fetch();
                            $attainmentExtra = '<br/>'.__($rowAttainment['usage']);
                        }
                        $styleAttainment = "style='font-weight: bold'";
                        if ( ($rowEntry['attainmentConcern'] == 'Y' || $rowEntry['attainmentConcern'] == 'P') and $showStudentAttainmentWarning == 'Y') {
                            $styleAttainment = getAlertStyle($alert, $rowEntry['attainmentConcern'] );
                        }
                        if ($rowEntry['gibbonScaleIDAttainment'] == '00027') {
                            $rowEntry['attainmentValue'] = $rowEntry['attainmentDescriptor'];
                            $rowEntry['attainmentDescriptor'] = '';
                        }
                        echo "<div $styleAttainment>".$rowEntry['attainmentValue'];
                        if ($rowEntry['gibbonRubricIDAttainment'] != '' AND $enableRubrics =='Y') {
                            echo "<a class='thickbox' href='".$_SESSION[$guid]['absoluteURL'].'/fullscreen.php?q=/modules/Markbook/markbook_view_rubric.php&gibbonRubricID='.$rowEntry['gibbonRubricIDAttainment'].'&gibbonCourseClassID='.$rowEntry['gibbonCourseClassID'].'&gibbonMarkbookColumnID='.$rowEntry['gibbonMarkbookColumnID'].'&gibbonPersonID='.$_SESSION[$guid]['gibbonPersonID']."&mark=FALSE&type=attainment&width=1100&height=550'><img style='margin-bottom: -3px; margin-left: 3px' title='".__('View Rubric')."' src='./themes/".$_SESSION[$guid]['gibbonThemeName']."/img/rubric.png'/></a>";
                        }
                        echo '</div>';
                        if ($rowEntry['attainmentValue'] != '') {
                            echo "<div class='detailItem' style='font-size: 75%; font-style: italic; margin-top: 2px'><b>".htmlPrep(__($rowEntry['attainmentDescriptor'])).'</b>'.__($attainmentExtra).'</div>';
                        }
                        echo '</td>';
                    }
					if ($enableEffort == 'Y') {
	                    if ($rowEntry['effort'] == 'N' or ($rowEntry['gibbonScaleIDEffort'] == '' and $rowEntry['gibbonRubricIDEffort'] == '')) {
	                        echo "<td class='dull' style='color: #bbb; text-align: center'>";
	                        echo __('N/A');
	                        echo '</td>';
	                    } else {
	                        echo "<td style='text-align: center'>";
	                        $effortExtra = '';
	                        
	                            $dataEffort = array('gibbonScaleID' => $rowEntry['gibbonScaleIDEffort']);
	                            $sqlEffort = 'SELECT * FROM gibbonScale WHERE gibbonScaleID=:gibbonScaleID';
	                            $resultEffort = $connection2->prepare($sqlEffort);
	                            $resultEffort->execute($dataEffort);
	                        if ($resultEffort->rowCount() == 1) {
	                            $rowEffort = $resultEffort->fetch();
	                            $effortExtra = '<br/>'.__($rowEffort['usage']);
	                        }
	                        $styleEffort = "style='font-weight: bold'";
	                        if ($rowEntry['effortConcern'] == 'Y' and $showStudentEffortWarning == 'Y') {
	                            $styleEffort = getAlertStyle($alert, $rowEntry['effortConcern'] );
	                        }
	                        echo "<div $styleEffort>".$rowEntry['effortValue'];
	                        if ($rowEntry['gibbonRubricIDEffort'] != '' AND $enableRubrics =='Y') {
	                            echo "<a class='thickbox' href='".$_SESSION[$guid]['absoluteURL'].'/fullscreen.php?q=/modules/Markbook/markbook_view_rubric.php&gibbonRubricID='.$rowEntry['gibbonRubricIDEffort'].'&gibbonCourseClassID='.$rowEntry['gibbonCourseClassID'].'&gibbonMarkbookColumnID='.$rowEntry['gibbonMarkbookColumnID'].'&gibbonPersonID='.$_SESSION[$guid]['gibbonPersonID']."&mark=FALSE&type=effort&width=1100&height=550'><img style='margin-bottom: -3px; margin-left: 3px' title='".__('View Rubric')."' src='./themes/".$_SESSION[$guid]['gibbonThemeName']."/img/rubric.png'/></a>";
	                        }
	                        echo '</div>';
	                        if ($rowEntry['effortValue'] != '') {
	                            echo "<div class='detailItem' style='font-size: 75%; font-style: italic; margin-top: 2px'>";
	                            echo '<b>'.htmlPrep(__($rowEntry['effortDescriptor'])).'</b>';
	                            if ($effortExtra != '') {
	                                echo __($effortExtra);
	                            }
	                            echo '</div>';
	                        }
	                        echo '</td>';
	                    }
					}
                    if ($rowEntry['commentOn'] == 'N' and $rowEntry['uploadedResponseOn'] == 'N') {
                        echo "<td class='dull' style='color: #bbb; text-align: left'>";
                        echo __('N/A');
                        echo '</td>';
                    } else {
                        echo '<td>';
                        if ($rowEntry['comment'] != '') {
                            if (mb_strlen($rowEntry['comment']) > 200) {
                                echo "<script type='text/javascript'>";
                                echo '$(document).ready(function(){';
                                echo "\$(\".comment-$entryCount\").hide();";
                                echo "\$(\".show_hide-$entryCount\").fadeIn(1000);";
                                echo "\$(\".show_hide-$entryCount\").click(function(){";
                                echo "\$(\".comment-$entryCount\").fadeToggle(1000);";
                                echo '});';
                                echo '});';
                                echo '</script>';
                                echo '<span>'.mb_substr($rowEntry['comment'], 0, 200).'...<br/>';
                                echo "<a title='".__('View Description')."' class='show_hide-$entryCount' onclick='return false;' href='#'>".__('Read more').'</a></span><br/>';
                            } else {
                                echo nl2br($rowEntry['comment']);
                            }
                            echo '<br/>';
                        }
                        if ($rowEntry['response'] != '') {
                            echo "<a title='".__('Uploaded Response')."' href='".$_SESSION[$guid]['absoluteURL'].'/'.$rowEntry['response']."'>".__('Uploaded Response').'</a><br/>';
                        }
                        echo '</td>';
                    }
                    if ($rowEntry['gibbonPlannerEntryID'] == 0) {
                        echo "<td class='dull' style='color: #bbb; text-align: left'>";
                        echo __('N/A');
                        echo '</td>';
                    } else {
                        
                            $dataSub = array('gibbonPlannerEntryID' => $rowEntry['gibbonPlannerEntryID']);
                            $sqlSub = "SELECT * FROM gibbonPlannerEntry WHERE gibbonPlannerEntryID=:gibbonPlannerEntryID AND homeworkSubmission='Y'";
                            $resultSub = $connection2->prepare($sqlSub);
                            $resultSub->execute($dataSub);
                        if ($resultSub->rowCount() != 1) {
                            echo "<td class='dull' style='color: #bbb; text-align: left'>";
                            echo __('N/A');
                            echo '</td>';
                        } else {
                            echo '<td>';
                            $rowSub = $resultSub->fetch();

                            
                                $dataWork = array('gibbonPlannerEntryID' => $rowEntry['gibbonPlannerEntryID'], 'gibbonPersonID' => $_SESSION[$guid]['gibbonPersonID']);
                                $sqlWork = 'SELECT * FROM gibbonPlannerEntryHomework WHERE gibbonPlannerEntryID=:gibbonPlannerEntryID AND gibbonPersonID=:gibbonPersonID ORDER BY count DESC';
                                $resultWork = $connection2->prepare($sqlWork);
                                $resultWork->execute($dataWork);
                            if ($resultWork->rowCount() > 0) {
                                $rowWork = $resultWork->fetch();

                                if ($rowWork['status'] == 'Exemption') {
                                    $linkText = __('Exemption');
                                } elseif ($rowWork['version'] == 'Final') {
                                    $linkText = __('Final');
                                } else {
                                    $linkText = __('Draft').' '.$rowWork['count'];
                                }

                                $style = '';
                                $status = 'On Time';
                                if ($rowWork['status'] == 'Exemption') {
                                    $status = __('Exemption');
                                } elseif ($rowWork['status'] == 'Late') {
                                    $style = "style='color: #ff0000; font-weight: bold; border: 2px solid #ff0000; padding: 2px 4px'";
                                    $status = __('Late');
                                }

                                if ($rowWork['type'] == 'File') {
                                    echo "<span title='".$rowWork['version'].". $status. ".sprintf(__('Submitted at %1$s on %2$s'), substr($rowWork['timestamp'], 11, 5), dateConvertBack($guid, substr($rowWork['timestamp'], 0, 10)))."' $style><a href='".$_SESSION[$guid]['absoluteURL'].'/'.$rowWork['location']."'>$linkText</a></span>";
                                } elseif ($rowWork['type'] == 'Link') {
                                    echo "<span title='".$rowWork['version'].". $status. ".sprintf(__('Submitted at %1$s on %2$s'), substr($rowWork['timestamp'], 11, 5), dateConvertBack($guid, substr($rowWork['timestamp'], 0, 10)))."' $style><a target='_blank' href='".$rowWork['location']."'>$linkText</a></span>";
                                } else {
                                    echo "<span title='$status. ".sprintf(__('Recorded at %1$s on %2$s'), substr($rowWork['timestamp'], 11, 5), dateConvertBack($guid, substr($rowWork['timestamp'], 0, 10)))."' $style>$linkText</span>";
                                }
                            } else {
                                if (date('Y-m-d H:i:s') < $rowSub['homeworkDueDateTime']) {
                                    echo "<span title='Pending'>".__('Pending').'</span>';
                                } else {
                                    if (!empty($rowList['dateStart']) && $rowList['dateStart'] > $rowSub['date']) {
                                        echo "<span title='".__('Student joined school after assessment was given.')."' style='color: #000; font-weight: normal; border: 2px none #ff0000; padding: 2px 4px'>".__('NA').'</span>';
                                    } else {
                                        if ($rowSub['homeworkSubmissionRequired'] == 'Required') {
                                            echo "<div style='color: #ff0000; font-weight: bold; border: 2px solid #ff0000; padding: 2px 4px; margin: 2px 0px'>".__('Incomplete').'</div>';
                                        } else {
                                            echo __('Not submitted online');
                                        }
                                    }
                                }
                            }
                            echo '</td>';
                        }
                    }
                    echo '</tr>';
                    if (mb_strlen($rowEntry['comment']) > 200) {
                        echo "<tr class='comment-$entryCount' id='comment-$entryCount'>";
                        echo '<td colspan=6>';
                        echo nl2br($rowEntry['comment']);
                        echo '</td>';
                        echo '</tr>';
                    }
                }

                $enableColumnWeighting = getSettingByScope($connection2, 'Markbook', 'enableColumnWeighting');
                $enableDisplayCumulativeMarks = getSettingByScope($connection2, 'Markbook', 'enableDisplayCumulativeMarks');

                if ($enableColumnWeighting == 'Y' && $enableDisplayCumulativeMarks == 'Y') {
                    renderStudentCumulativeMarks($gibbon, $pdo, $_SESSION[$guid]['gibbonPersonID'], $rowList['gibbonCourseClassID']);
                }

                echo '</table>';
            }
        }
    }

    if ($entryCount < 1) {
        echo "<div class='error'>";
        echo __('There are no records to display.');
        echo '</div>';
    }
