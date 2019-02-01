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
    public function getByID($primaryKeyValue)
    {
        return $this->select($primaryKeyValue)->fetch();
    }

    public function getBy($keyName, $keyValue)
    {
        if (empty($keyName) || empty($keyValue)) {
            throw new \InvalidArgumentException("Gateway getBy method for {$this->getTableName()} must provide a primary key value.");
        }

        $query = $this
            ->newSelect()
            ->cols(['*'])
            ->from($this->getTableName())
            ->where($keyName.' = :keyName')
            ->bindValue('keyName', $keyValue);

        return $this->runSelect($query);
    }

    public function select($primaryKeyValue)
    {
        if (empty($primaryKeyValue)) {
            throw new \InvalidArgumentException("Gateway select method for {$this->getTableName()} must provide a primary key value.");
        }

        $query = $this
            ->newSelect()
            ->cols(['*'])
            ->from($this->getTableName())
            ->where($this->getPrimaryKey().' = :primaryKey')
            ->bindValue('primaryKey', $primaryKeyValue);

        return $this->runSelect($query);
    }
    
    public function insert($data)
    {
        unset($data[$this->getPrimaryKey()]);

        $query = $this
            ->newInsert()
            ->into($this->getTableName())
            ->cols($data);

        return $this->runInsert($query);
    }

    public function update($primaryKeyValue, $data)
    {
        if (empty($primaryKeyValue)) {
            throw new \InvalidArgumentException("Gateway update method for {$this->getTableName()} must provide a primary key value.");
        }
        
        unset($data[$this->getPrimaryKey()]);

        $query = $this
            ->newUpdate()
            ->table($this->getTableName())
            ->cols($data)
            ->where($this->getPrimaryKey().' = :primaryKey')
            ->bindValue('primaryKey', $primaryKeyValue);

        return $this->runUpdate($query);
    }

    public function delete($primaryKeyValue)
    {
        if (empty($primaryKeyValue)) {
            throw new \InvalidArgumentException("Gateway delete method for {$this->getTableName()} must provide a primary key value.");
        }

        $query = $this
            ->newDelete()
            ->from($this->getTableName())
            ->where($this->getPrimaryKey().' = :primaryKey')
            ->bindValue('primaryKey', $primaryKeyValue);

        return $this->runDelete($query);
    }

    public function unique(array $data, array $uniqueKeys, $primaryKeyValue = null) : bool
    {
        $query = $this
            ->newSelect()
            ->cols([$this->getPrimaryKey()])
            ->from($this->getTableName());

        $query->where(function ($query) use ($uniqueKeys, $data) {
            foreach ($uniqueKeys as $i => $key) {
                if (empty($data[$key])) return false;

                $query->where("{$key} = :key{$i}")
                    ->bindValue("key{$i}", $data[$key]);
            }
        });

        if (!empty($primaryKeyValue)) {
            $query->where($this->getPrimaryKey().' <> :primaryKey')
                  ->bindValue('primaryKey', $primaryKeyValue);
        }
            
        return $this->runSelect($query)->rowCount() == 0;
    }

    public function exists($primaryKeyValue)
    {
        return $this->select($primaryKeyValue)->rowCount() >= 1;
    }
}
