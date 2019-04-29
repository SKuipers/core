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

namespace Gibbon\Module\Staff\Tables;

use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\Staff\StaffCoverageDateGateway;
use Gibbon\Module\Staff\Tables\AbsenceFormats;
use Gibbon\Contracts\Services\Session;
use Gibbon\Contracts\Database\Connection;

/**
 * CoverageDates
 *
 * @version v18
 * @since   v18
 */
class CoverageDates
{
    protected $session;
    protected $db;
    protected $staffCoverageDateGateway;

    public function __construct(Session $session, Connection $db, StaffCoverageDateGateway $staffCoverageDateGateway)
    {
        $this->session = $session;
        $this->db = $db;
        $this->staffCoverageDateGateway = $staffCoverageDateGateway;
    }

    public function create($gibbonStaffCoverageID)
    {
        $guid = $this->session->get('guid');
        $connection2 = $this->db->getConnection();

        $dates = $this->staffCoverageDateGateway->selectDatesByCoverage($gibbonStaffCoverageID)->toDataSet();
        $table = DataTable::create('staffCoverageDates')->withData($dates);

        $table->addColumn('date', __('Date'))
            ->format(Format::using('dateReadable', 'date'));

        $table->addColumn('timeStart', __('Time'))
            ->format([AbsenceFormats::class, 'timeDetails']);

        return $table;
    }
}
