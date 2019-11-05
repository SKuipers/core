<?php

use Gibbon\Module\Reports\DataSource;

class ClassGrades extends DataSource
{
    protected $gibbonStudentEnrolmentID;
    protected $data;

    public function getSchema()
    {
        return [ 
            ['grade' => 70, 'effort' => 'S'],
            ['grade' => 80, 'effort' => 'G'],
            ['grade' => 90, 'effort' => 'VG'],
            ['grade' => 100, 'effort' => 'E'],
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
