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
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Module\Staff\Tables\AbsenceFormats;

/**
 * AbsenceDates
 *
 * @version v18
 * @since   v18
 */
class AbsenceDates
{
    protected $staffDateAbsenceGateway;

    public function __construct(StaffAbsenceDateGateway $staffDateAbsenceGateway)
    {
        $this->staffDateAbsenceGateway = $staffDateAbsenceGateway;
    }

    public function compose($absence)
    {
        $absenceDates = $this->staffDateAbsenceGateway->selectDatesByAbsence($absence['gibbonStaffAbsenceID'])->toDataSet();
        $table = DataTable::create('staffAbsenceDates')->withData($absenceDates);

        $table->addColumn('date', __($absence['type']).' '.__($absence['reason']))
            ->format(Format::using('dateReadable', 'date'));

        $table->addColumn('timeStart', __n('{count} Day', '{count} Days', $absence['value'], ['count' => $absence['value']]))
            ->format([AbsenceFormats::class, 'timeDetails']);

        return $table;
    }
}
