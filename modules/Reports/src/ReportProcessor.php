<?php

namespace Gibbon\Module\Reports;

use Gibbon\Module\Reports\Domain\ReportingGPAGateway;

class ReportProcessor
{
    protected $gpaGateway;

    public function __construct(ReportingGPAGateway $gpaGateway)
    {
        $this->gpaGateway = $gpaGateway;
    }

    public function calculateGPA(ReportData $data)
    {
        $reports = $data->getField('termGrades', 'reports');

        $terms = array_reduce(array_keys($reports), function ($group, $reportNum) use (&$data, &$reports) {
            $group[$reportNum] = $this->calculateTermGPA($data, $reportNum, $reports[$reportNum]);
            return $group;
        }, []);

        $data->setField('termGrades', 'terms', $terms);
    }

    public function calculateTermGPA(ReportData $data, $reportNum, $reportID)
    {
        $total = 0;
        $cumulative = 0;
        $atRisk = 0;

        foreach ($data->getField('termGrades', 'classGrades') as $class) {

            // Grab the course weight and grade
            $weight = isset($class[$reportNum]['weight'])? floatval($class[$reportNum]['weight']) : 0;
            

            if (!empty($class[$reportNum]['final percent'])) {
                $grade = intval($class[$reportNum]['final percent']['value']);
            } else {
                $grade = isset($class[$reportNum]['term percent'])? intval($class[$reportNum]['term percent']['value']) : '-';
            }

            // Skip any empty or incomplete marks
            if ($weight == 0 || $grade == '' || $grade == '-' || $grade == 'INC') continue;

            // Check core courses for At Risk (< 60%)
            $core = $class[$reportNum]['core'] ?? 'N';
            if ($core == 'Y' && $grade < 60.0) {
                $atRisk++;
            }

            // Sum the cumulative weight & grades
            $total += $weight;
            $cumulative += ($grade * $weight);
        }

        if (empty($total) && empty($cumulative)) return array();

        // Calculate the GPA
        $gpa = ( $cumulative / $total );
        $gpa = round( min(100.0, max(0.0, $gpa)), 1);

        if ($atRisk > 0) {
            $status = 'At Risk';
        } elseif ($gpa >= 94.5) {
            $status = 'Scholars';
        } else if ($gpa >= 89.5) {
            $status = 'Distinction';
        } else if ($gpa >= 79.5) {
            $status = 'Honours';
        } else if ($gpa >= 60.0) {
            $status = 'Good Standing';
        } else {
            $status = 'At Risk';
        }

        // Store this in the database, for easy lookup
        $this->saveGPA($reportID, $data, $gpa, $status);

        return array('gpa' => $gpa, 'status' => $status);
    }

    public function saveGPA($gibbonReportingCycleID, ReportData $data, $gpa, $status)
    {
        $data = array(
            'gibbonReportingCycleID' => $gibbonReportingCycleID,
            'gibbonPersonIDStudent'  => $data->getField('student', 'gibbonPersonID'),
            'gibbonYearGroupID'      => $data->getField('student', 'gibbonYearGroupID'),
            'gpa'                    => $gpa,
            'status'                 => $status,
        );

        return $this->gpaGateway->insertAndUpdate($data, ['gpa' => $gpa, 'status' => $status]);
    }
}
