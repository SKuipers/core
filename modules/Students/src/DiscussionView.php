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

namespace Gibbon\Module\Students;

use Gibbon\View\View;
use Gibbon\Services\Format;
use Gibbon\Forms\OutputableInterface;
use Gibbon\Contracts\Services\Session;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\System\DiscussionGateway;

/**
 * DiscussionView
 *
 * Reusable class for displaying the info for coverage dates.
 *
 * @version v22
 * @since   v22
 */
class DiscussionView implements OutputableInterface
{
    protected $session;
    protected $db;
    protected $view;
    protected $discussionGateway;
    protected $discussion;

    public function __construct(Session $session, Connection $db, View $view, DiscussionGateway $discussionGateway)
    {
        $this->session = $session;
        $this->db = $db;
        $this->view = $view;
        $this->discussionGateway = $discussionGateway;
    }

    public function create($gibbonPersonID)
    {
        $this->discussion = $this->discussionGateway->selectDiscussionByTargetPerson($gibbonPersonID, false)->fetchGrouped();

        // Filter to most recent comment on each discussion
        $this->discussion = array_map(function ($item) {
            return end($item);
        }, $this->discussion);

        // Update discussion info
        $this->discussion = array_map(function ($item) {
            $context = $this->getDiscussionContext($item['gibbonPersonIDTarget'], $item['foreignTable'], $item['foreignTableID']);
            $item['context'] = __($item['moduleName']).' · '.$context;
            $item['type'] = __($item['moduleName']).' '.$item['type'];
            $item['attachmentLabel'] = __('View');
            return $item;
        }, $this->discussion,);



        return $this;
    }

    public function getOutput()
    {
        return $this->view->fetchFromTemplate('ui/discussion.twig.html', [
            'title' => __('Recent Feedback'),
            'discussion' => $this->discussion,
            'itemClass' => 'mb-8'
        ]);
    }

    protected function getDiscussionContext($gibbonPersonID, $foreignTable, $foreignTableID)
    {
        $context = '';
        $data = ['foreignTableID' => $foreignTableID];

        switch ($foreignTable) {
            case 'gibbonMarkbookEntry': 
                $sql = "SELECT gibbonMarkbookColumn.name, gibbonMarkbookColumn.gibbonMarkbookColumnID
                    FROM gibbonMarkbookEntry 
                    JOIN gibbonMarkbookColumn ON (gibbonMarkbookColumn.gibbonMarkbookColumnID=gibbonMarkbookEntry.gibbonMarkbookColumnID)
                    WHERE gibbonMarkbookEntryID=:foreignTableID";
                $values = $this->db->selectOne($sql, $data);
                $context = Format::link('./index.php?q=/modules/Students/student_view_details.php&gibbonPersonID='.$gibbonPersonID.'&search=&search=&allStudents=&subpage=Markbook', $values['name'], ['class' => 'inline underline']);
                break;

            case 'freeLearningUnitStudent': 
                $sql = "SELECT freeLearningUnit.name, freeLearningUnit.freeLearningUnitID
                    FROM freeLearningUnitStudent 
                    JOIN freeLearningUnit ON (freeLearningUnit.freeLearningUnitID=freeLearningUnitStudent.freeLearningUnitID)
                    WHERE freeLearningUnitStudentID=:foreignTableID";
                $values = $this->db->selectOne($sql, $data);
                $context = Format::link('./index.php?q=/modules/Free Learning/units_browse_details.php&sidebar=true&freeLearningUnitID='.$values['freeLearningUnitID'].'&gibbonDepartmentID=&difficulty=&showInactive=N&name=&view=map', $values['name'], ['class' => 'inline underline']);
                break;

            case 'flexibleLearningUnitSubmission': 
                $sql = "SELECT flexibleLearningUnit.name, flexibleLearningUnit.flexibleLearningUnitID
                    FROM flexibleLearningUnitSubmission 
                    JOIN flexibleLearningUnit ON (flexibleLearningUnit.flexibleLearningUnitID=flexibleLearningUnitSubmission.flexibleLearningUnitID)
                    WHERE flexibleLearningUnitSubmissionID=:foreignTableID";
                $values = $this->db->selectOne($sql, $data);
                $context = Format::link('./index.php?q=/modules/Flexible Learning/units_browse_details.php&sidebar=true&flexibleLearningUnitID='.$values['flexibleLearningUnitID'], $values['name'], ['class' => 'inline underline']);
                break;

            case 'masteryTranscriptJourney': 
                $sql = "SELECT masteryTranscriptJourney.type, masteryTranscriptJourney.masteryTranscriptCreditID, masteryTranscriptCredit.name as creditName, masteryTranscriptJourney.masteryTranscriptOpportunityID, masteryTranscriptOpportunity.name as opportunityName
                    FROM masteryTranscriptJourney 
                    LEFT JOIN masteryTranscriptCredit ON (masteryTranscriptCredit.masteryTranscriptCreditID=masteryTranscriptJourney.masteryTranscriptCreditID AND masteryTranscriptJourney.type='Credit')
                    LEFT JOIN masteryTranscriptOpportunity ON (masteryTranscriptOpportunity.masteryTranscriptOpportunityID=masteryTranscriptJourney.masteryTranscriptOpportunityID AND masteryTranscriptJourney.type='Opportunity')
                    WHERE masteryTranscriptJourneyID=:foreignTableID";
                $values = $this->db->selectOne($sql, $data);
                $context = !empty($values['creditName']) ? $values['creditName'] : $values['opportunityName'];
                break;
                
            default : '';
        }

        return $context;
    }
}
