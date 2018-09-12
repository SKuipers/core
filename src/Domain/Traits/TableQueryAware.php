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

namespace Gibbon\Domain\Traits;

/**
 * 
 */
trait TableQueryAware
{
    public function get($primaryKeyValue, $tableName = null, $primaryKey = null)
    {
        return $this->selectRow($primaryKeyValue, $tableName, $primaryKey)->fetch();
    }
    
    public function select($primaryKeyValue, $tableName = null, $primaryKey = null)
    {
        if (empty($tableName)) $tableName = $this->getTableName();
        if (empty($primaryKey)) $primaryKey = $this->getPrimaryKey();

        if (empty($primaryKeyValue)) {
            throw new \InvalidArgumentException("Gateway select method for {$tableName} must provide a primary key value.");
        }

        $query = $this
            ->newSelect()
            ->cols(['*'])
            ->from($tableName)
            ->where($primaryKey.' = :primaryKey')
            ->bindValue('primaryKey', $primaryKeyValue);

        return $this->runSelect($query);
    }
    
    public function insert($data, $tableName = null, $primaryKey = null)
    {
        if (empty($tableName)) $tableName = $this->getTableName();
        if (empty($primaryKey)) $primaryKey = $this->getPrimaryKey();

        unset($data[$primaryKey]);

        $query = $this
            ->newInsert()
            ->into($tableName)
            ->cols($data);

        return $this->runInsert($query);
    }

    public function update($data, $tableName = null, $primaryKey = null)
    {
        if (empty($tableName)) $tableName = $this->getTableName();
        if (empty($primaryKey)) $primaryKey = $this->getPrimaryKey();

        if (empty($data[$primaryKey])) {
            throw new \InvalidArgumentException("Gateway update method for {$tableName} must provide a primary key value.");
        }
        
        $primaryKeyValue = $data[$primaryKey];
        unset($data[$primaryKey]);

        $query = $this
            ->newUpdate()
            ->table($tableName)
            ->cols($data)
            ->where($primaryKey.' = :primaryKey')
            ->bindValue('primaryKey', $primaryKeyValue);

        return $this->runUpdate($query);
    }

    public function delete($primaryKeyValue, $tableName = null, $primaryKey = null)
    {
        if (empty($tableName)) $tableName = $this->getTableName();
        if (empty($primaryKey)) $primaryKey = $this->getPrimaryKey();

        if (empty($primaryKeyValue)) {
            throw new \InvalidArgumentException("Gateway delete method for {$tableName} must provide a primary key value.");
        }

        $query = $this
            ->newDelete()
            ->from($tableName)
            ->where($primaryKey.' = :primaryKey')
            ->bindValue('primaryKey', $primaryKeyValue);

        return $this->runDelete($query);
    }
}
