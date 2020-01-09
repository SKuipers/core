<?php

use Gibbon\Module\Reports\DataSource;

class ClassGrades extends DataSource
{
    protected $gibbonStudentEnrolmentID;
    protected $data;

    public function getSchema()
    {
        return [
            1 => [
                'effort'    => [
                    'value' => 'VG',
                    'descriptor' => 'Very Good',
                ],
                'comment'    => [
                    'value' => ['paragraph', 6],
                ],
            ],
            2 => [
                'effort'    => [
                    'value' => 'VG',
                    'descriptor' => 'Very Good',
                ],
                'comment'    => [
                    'value' => ['paragraph', 6],
                ],
            ],
            3 => [
                'effort'    => [
                    'value' => 'VG',
                    'descriptor' => 'Very Good',
                ],
                'comment'    => [
                    'value' => ['paragraph', 6],
                ],
            ],
            4 => [
                'effort'    => [
                    'value' => 'VG',
                    'descriptor' => 'Very Good',
                ],
                'comment'    => [
                    'value' => ['paragraph', 6],
                ],
            ],
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
