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

namespace Gibbon\Domain;

use Aura\SqlQuery\QueryFactory;
use Gibbon\Domain\QueryCriteria;
use Aura\SqlQuery\QueryInterface;
use Aura\SqlQuery\Common\DeleteInterface;
use Aura\SqlQuery\Common\InsertInterface;
use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\Common\UpdateInterface;
use Gibbon\Contracts\Inflectors\SessionAwareInterface;

/**
 * Auditable Gateway
 *
 * @version v17
 * @since   v17
 */
abstract class AuditableGateway extends QueryableGateway implements SessionAwareInterface
{
    protected $session;

    public function setSession($session)
    {
        $this->session = $session;
    }

    public function queryAudits(QueryCriteria $criteria, $foreignTableID = null)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonDataAudit')
            ->cols([
                'gibbonDataAuditID', 'event', 'eventData', 'foreignTable', 'foreignTableID', 'gibbonActionURL', 'gibbonDataAudit.gibbonActionID', 'gibbonDataAudit.gibbonRoleID', 'gibbonDataAudit.gibbonPersonID', 'changeDate', 'changeCount', 'changeTimestamp', 'gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonModule.name as moduleName', 
                $this->getTableName().'.'.$this->getPrimaryName().' AS primaryName',
                $this->getTableName().'.'.$this->getPrimaryKey().' AS primaryKeyValue'])
            ->leftJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=gibbonDataAudit.gibbonPersonID')
            ->leftJoin($this->getTableName(), $this->getTableName().'.'.$this->getPrimaryKey().'=gibbonDataAudit.foreignTableID')
            ->leftJoin('gibbonAction', 'gibbonAction.gibbonActionID=gibbonDataAudit.gibbonActionID')
            ->leftJoin('gibbonModule', 'gibbonModule.gibbonModuleID=gibbonAction.gibbonModuleID')
            ->where('foreignTable=:foreignTable', ['foreignTable' => $this->getTableName()]);

        if (!empty($foreignTableID)) {
            $query->where('foreignTableID=:foreignTableID', ['foreignTableID' => $foreignTableID]);
        }

        return $this->runQuery($query, $criteria);
    }

    protected function runInsert(InsertInterface $query)
    {
        $insertID = parent::runInsert($query);

        if (!empty($insertID) && $this->db()->getQuerySuccess()) {
            $this->createAudit($insertID, 'Created', $query->getBindValues());
        }

        return $insertID;
    }

    protected function runUpdate(UpdateInterface $query)
    {
        $updateID = $this->getPrimaryKeyFromQuery($query);
        $rowData = $this->getRow($updateID);
        $rowDifference = $this->getArrayDifference($rowData, $query->getBindValues());
        
        $updated = parent::runUpdate($query);

        if (!empty($updateID) && !empty($rowDifference) && $this->db()->getQuerySuccess()) {
            $this->createAudit($updateID, 'Updated', $rowDifference);
        }

        return $updated;
    }

    protected function runDelete(DeleteInterface $query)
    {
        $deleteID = $this->getPrimaryKeyFromQuery($query);

        $rowData = $this->getRow($deleteID);
        $deleted = parent::runDelete($query);

        if (!empty($deleteID) && $this->db()->getQuerySuccess()) {
            $this->createAudit($deleteID, 'Deleted', $rowData);
        }

        return $deleted;
    }

    private function getPrimaryKeyFromQuery(QueryInterface $query)
    {
        $bindings = $query->getBindValues();

        return isset($bindings['primaryKey']) ? $bindings['primaryKey'] : false;
    }

    private function getArrayDifference($original, $new)
    {
        return array_filter($new, function ($key) use ($original, $new) {
            return $original[$key] != $new[$key] && $key != 'primaryKey';
        }, ARRAY_FILTER_USE_KEY);
    }

    private function createAudit($foreignTableID, $event, $eventData = array())
    {
        $data = [
            'event'          => $event,
            'eventData'      => json_encode($eventData),
            'foreignTable'   => $this->getTableName(),
            'foreignTableID' => $foreignTableID,
            'gibbonModule'   => $this->session->get('module'),
            'gibbonActionURL'   => $this->session->get('action'),
            'gibbonPersonID' => $this->session->get('gibbonPersonID'),
            'gibbonRoleID'   => $this->session->get('gibbonRoleIDCurrent'),
        ];
        $sql = "INSERT INTO gibbonDataAudit SET 
                event=:event, 
                eventData=:eventData,
                foreignTable=:foreignTable, 
                foreignTableID=:foreignTableID, 
                gibbonActionURL=:gibbonActionURL, 
                gibbonActionID=(
                    SELECT gibbonAction.gibbonActionID 
                    FROM gibbonAction
                    JOIN gibbonModule ON (gibbonModule.gibbonModuleID=gibbonAction.gibbonModuleID)
                    JOIN gibbonPermission ON (gibbonAction.gibbonActionID=gibbonPermission.gibbonActionID)
                    JOIN gibbonRole ON (gibbonPermission.gibbonRoleID=gibbonRole.gibbonRoleID)
                    WHERE gibbonAction.URLList LIKE CONCAT('%', :gibbonActionURL, '%')
                    AND gibbonPermission.gibbonRoleID=:gibbonRoleID 
                    AND gibbonModule.name=:gibbonModule
                    ORDER BY gibbonAction.precedence DESC LIMIT 1), 
                gibbonRoleID=:gibbonRoleID, 
                gibbonPersonID=:gibbonPersonID,
                changeDate=NOW(),
                changeTimestamp=NOW()
        ";

        $this->db()->affectingStatement($sql, $data);
    }
}
