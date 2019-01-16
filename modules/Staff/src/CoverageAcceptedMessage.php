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

namespace Gibbon\Module\Staff;

use Gibbon\Services\Format;

/**
 * CoverageAcceptedMessage
 *
 * @version v18
 * @since   v18
 */
class CoverageAcceptedMessage implements Message
{
    protected $coverage;

    public function __construct($coverage)
    {
        $this->coverage = $coverage;
    }

    public function via()
    {
        return ['mail'];
        // return ['mail', 'sms'];
    }

    public function toSubject()
    {
        return __('Coverage Accepted');
    }

    public function toSMS()
    {
        return __('A coverage request for {date} was {actioned} by {person}.', [
            'date'     => Format::dateRangeReadable($this->coverage['dateStart'], $this->coverage['dateEnd']),
            'actioned' => strtolower($this->coverage['status']),
            'person'   => Format::name($this->coverage['titleCoverage'], $this->coverage['preferredNameCoverage'], $this->coverage['surnameCoverage'], 'Staff', false, true),
        ]);
    }

    public function toMail()
    {
        return $this->toSMS();
    }

    public function toLink()
    {
        return [
            'url'  => 'index.php?q=/modules/Staff/coverage_view_details.php&gibbonStaffCoverageID='.$this->coverage['gibbonStaffCoverageID'],
            'text' => __('View Details'),
        ];
    }
}
