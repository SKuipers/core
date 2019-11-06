<?php

use Gibbon\Module\Reports\DataSource;

class ClassGrades extends DataSource
{
    protected $gibbonStudentEnrolmentID;
    protected $data;

    public function getSchema()
    {
        return [
            1 => ['grade' => 70, 'effort' => ['value' => 'S', 'descriptor' => 'Satisfactory']],
            2 => ['grade' => 80, 'effort' => ['value' => 'G', 'descriptor' => 'Good']],
            3 => ['grade' => 90, 'effort' => ['value' => 'VG', 'descriptor' => 'Very Good']],
            4 => ['grade' => 100, 'effort' => ['value' => 'E', 'descriptor' => 'Excellent']],
        ];
    }

    public function getData($ids = [])
    {
        if ($this->isCacheValid($ids) == false) {
            $this->data = $this->getFactory()->get('TermGrades')->getData($ids);
            $this->gibbonStudentEnrolmentID = $ids['gibbonStudentEnrolmentID'];
        }

        return (isset($this->data['classGrades'][$ids['gibbonCourseID']]))? $this->data['classGrades'][$ids['gibbonCourseID']] : array();
    }

    protected function isCacheValid($ids)
    {
        return isset($this->data) && $this->gibbonStudentEnrolmentID == $ids['gibbonStudentEnrolmentID'];
    }
}
