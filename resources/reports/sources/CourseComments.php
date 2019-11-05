<?php

use Gibbon\Module\Reports\DataSource;

class CourseComments extends DataSource
{
    public function getSchema()
    {
        return [
            'classNotes'      => 'The first units focused on photosynthesis and cellular respiration, as well as the human body system. Students explored the biochemistry behind photosynthesis and cellular respiration. Additionally, they investigated the human digestive system, circulatory system, motor system, respiratory system, and the excretory system.',
            'studentComment' => 'Student demonstrates strong work habits along with an aptitude for human biology. She has a very good grasp of all matters relating the cellular respiration and she is able to apply her understanding of the human systems in a variety of contexts. She is encouraged to seek clarification on formatting and presentation before she submits her lab reports.',
            'writtenBy' => 'Ms. Test Teacher',
        ];
    }

    public function getData($ids = [])
    {
        $data = array('reportID' => $ids['reportID'], 'studentID' => $ids['gibbonPersonID'], 'subjectID' => $ids['gibbonCourseID'], 'gibbonCourseClassID' => $ids['gibbonCourseClassID']);
        $sql = "SELECT arrReportSubject.subjectComment as studentComment, arrClassNotes.classNotes, teacher.title, teacher.surname, teacher.preferredName
                FROM gibbonPerson
                LEFT JOIN arrReportSubject ON (arrReportSubject.studentID=gibbonPerson.gibbonPersonID AND arrReportSubject.subjectID=:subjectID AND arrReportSubject.reportID=:reportID)
                LEFT JOIN arrClassNotes ON (arrClassNotes.gibbonCourseClassID=:gibbonCourseClassID AND arrClassNotes.reportID=:reportID)
                LEFT JOIN gibbonPerson as teacher ON (teacher.gibbonPersonID=arrReportSubject.teacherID)
                WHERE gibbonPerson.gibbonPersonID=:studentID";

        $result = $this->pdo->executeQuery($data, $sql);

        $values = array();

        if ($result->rowCount() > 0) {
            $values = $result->fetch();

            if (!empty($values['preferredName'])) {

                $values['writtenBy'] = formatName($values['title'], substr($values['preferredName'], 0, 1).'.', $values['surname'], 'Staff', false, false);

                if ($values['preferredName'] == 'Christine' && $values['surname'] == 'Sonmez') {
                    $values['writtenBy'] .= ', Ms. S. Smith-Dale';
                }
            }
        }

        return $values;
    }
}
