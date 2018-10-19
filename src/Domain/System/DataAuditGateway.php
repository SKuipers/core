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

namespace Gibbon\Domain\System;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\Traits\TableQueryAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * DataAudit Gateway
 *
 * @version v17
 * @since   v17
 */
class DataAuditGateway extends QueryableGateway
{
    use TableAware;
    use TableQueryAware;

    private static $tableName = 'gibbonDataAudit';
    private static $primaryKey = 'gibbonDataAuditID';
    private static $primaryName = 'event';

    private static $searchableColumns = [''];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryDataAudits(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonDataAuditID', 'event'
            ]);

        return $this->runQuery($query, $criteria);
    }

    public function getDataAuditByID($gibbonDataAuditID)
    {
        $data = array('gibbonDataAuditID' => $gibbonDataAuditID);
        $sql = "SELECT gibbonDataAudit.*, gibbonModule.name AS moduleName 
                FROM gibbonDataAudit 
                JOIN gibbonAction ON (gibbonAction.gibbonActionID=gibbonDataAudit.gibbonActionID)
                JOIN gibbonModule ON gibbonModule.gibbonModuleID=gibbonAction.gibbonModuleID
                WHERE gibbonDataAuditID=:gibbonDataAuditID";

        return $this->db()->selectOne($sql, $data);
    }

    public function restoreRecord($tableName, $data)
    {
        $query = $this
            ->newInsert()
            ->into($tableName)
            ->cols($data);

        return $this->runInsert($query);
    }

}
