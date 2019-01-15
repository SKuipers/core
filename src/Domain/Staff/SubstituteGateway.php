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

namespace Gibbon\Domain\Staff;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\Traits\TableQueryAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Substitute Gateway
 *
 * @version v18
 * @since   v18
 */
class SubstituteGateway extends QueryableGateway
{
    use TableAware;
    use TableQueryAware;

    private static $tableName = 'gibbonSubstitute';
    private static $primaryKey = 'gibbonSubstituteID';

    private static $searchableColumns = ['preferredName', 'surname', 'username'];
    
    /**
     * Queries the list of users for the Manage Substitutes page.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAllSubstitutes(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonSubstitute.gibbonSubstituteID', 'gibbonSubstitute.type', 'gibbonSubstitute.details', 'gibbonSubstitute.priority', 'gibbonSubstitute.active',
                'gibbonPerson.gibbonPersonID', 'gibbonPerson.title', 'gibbonPerson.surname', 'gibbonPerson.preferredName', 'gibbonPerson.status', 'gibbonPerson.image_240',
                
            ])
            ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=gibbonSubstitute.gibbonPersonID');

        $criteria->addFilterRules([
            'active' => function ($query, $active) {
                return $query
                    ->where('gibbonSubstitute.active = :active')
                    ->bindValue('active', $active);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonPerson.status = :status')
                    ->bindValue('status', ucfirst($status));
            },
        ]);

        return $this->runQuery($query, $criteria);
    }
}
