<?php

namespace \Fine\Db;

use \Fine\Std\ParamTrait;
use \Fine\Paging\PagingInterface;

class Model
{
    
    use ParamTrait;

    protected $_db;
    protected $_table;
    protected $_key;
    protected $_field;
    protected $_param;
    protected $_result;
    
    public function setDb(Db $db)
    {
      $this->_db = $db;
      return $this;
    }

    public function getDb()
    {
      return $this->_db;
    }

    public function setTable($table)
    {
        $this->_table = $table;
        return $this;
    }

    public function getTable()
    {
        return $this->_table;
    }

    public function setKey($key)
    {
        $this->_key = $key;
        return $this;
    }

    public function getKey()
    {
        return $this->_key;
    }

    public function setFields(array $fields)
    {
        $this->_field = $fields;
        return $this;
    }
    
    public function getFields()
    {
        return $this->_field;
    }

    public function addField($field, $alias = null)
    {
        if ($alias === null) {
            $this->_field[] = $field;
        } else {
            $this->_field[$alias] = $field;
        }
        return $this;
    }

    public function addFields(array $fields)
    {
        foreach ($fields as $alias => $field) {
            if (is_int($alias)) {
                $this->_field[] = $field;
            }
            else {
                $this->_field[$alias] = $field;
            }
        }
        return $this;
    }

    public function removeField($field)
    {
        foreach ($this->_field as $alias => $name) {
            if (is_int($alias)) {
                if ($name === $field) {
                    unset($this->_field[$alias]);
                }
            }
            else {
                if ($alias === $field) {
                    unset($this->_field[$alias]);
                }
            }
        }

        return $this;
    }

    public function hasField($field)
    {
        foreach ($this->_field as $alias => $name) {
            if (is_int($alias)) {
                if ($name === $field) {
                    return true;
                }
            }
            else {
                if ($alias === $field) {
                    return true;
                }
            }
        }

        return false;
    }
    
    public function setId($id) 
    {
        $this->_id = $id;
        return $this;
    }

    public function getId() 
    {
        return $this->_id;
    }

    public function setVal(array $val)
    {
        foreach ($this->_field as $field) {
            if (isset($val[$field]) && $field !== $this->_key) {
                $this->{$field} = $val[$field];
            }
        }
        
        return $this;
    }
    
    public function getVal()
    {
        $val = [];
        foreach ($this->_field as $i) {
            $val[$i] = $this->{$i};
        }
        if ($this->_key !== null) {
            $val[$this->_key] = $this->getId();
        }

        return $val;
    }
    
    public function setFieldAndVal($aKeyValue)
    {
        $this->setFields(array_keys($aKeyValue));
        $this->setVal($aKeyValue);                    
        return $this;                    
    }
    
    public function setResult($result)
    {
        $this->_result = $result;
        foreach ($this->_field as $field) { /** @todo handle is_int? */
            $this->{$field} = null;
        }
        if ($this->_key !== null) {
            $this->{$this->_key} = null;
        }
        
        return $this;
    }
    
    public function setActiveRecordResult($result)
    {
        $this->setResult($result);
        $this->setVal($result);
        if ($this->_key !== null && isset($result[$this->_key])) {
            $this->{$this->_key} = $result[$this->_key];
        }
        return $this;
    }
    
    public function getResult()
    {
        return $this->_result;
    }
    
    public function select($params = null)
    {
        return $this->setActiveRecordResult($this->_db->fetch($this->_sql($params, true, true)));
    }

    public function selectAll($params = null)
    {
        return $this->setResult($this->_db->fetchAll($this->_sql($params, true, true)));
    }
    
    public function selectCol($params = null)
    {
        return $this->setResult($this->_db->fetchCol($this->_sql($params, true, true)));
    }
    
    public function selectVal($params = null)
    {
        return $this->setResult($this->_db->fetchVal($this->_sql($params, true, true)));
    }
    
    public function selectKeyPair($params = null)
    {
        return $this->setResult($this->_db->fetchKeyPair($this->_sql($params, true, true)));
    }
    
    public function selectKeyed($params = null)
    {
        return $this->setResult($this->_db->fetchKeyed($this->_sql($params, true, true)));
    }
    
    public function selectCount($params = null, $expr = '*')
    {
        return $this->setResult($this->_db->fetchKeyed("SELECT COUNT($expr)" . $this->_sql($params, false, true)));
    }
    
    public function selectEach(callable $callback = null)
    {
        if ($callback === null) { // foreach
            /** @todo Iterator */
        }
        else {
            $stmt = $this->_db->query($this->_sql(null, true, true));
            while($result = $stmt->fetch()) {
                if (call_user_func($callback, $this->_db->{$this->_table}->setActiveRecordResult($result)) === false) {
                    break;
                }
            }
        }
    }

    public function selectInserted()
    {
        return $this->setActiveRecordResult($this->_db->{$this->_table}->fetchVal([$this->_key => $this->_db->lastInsertId()]));
    }

    public function fetch($params = null)
    {
        return $this->select($params)->getResult();
    }

    public function fetchAll($params = null)
    {
        return $this->selectAll($params)->getResult();
    }

    public function fetchCol($params = null)
    {
        return $this->selectCol($params)->getResult();
    }

    public function fetchVal($params = null)
    {
        return $this->selectVal($params)->getResult();
    }

    public function fetchKeyPair($params = null)
    {
        return $this->selectKeyPair($params)->getResult();
    }

    public function fetchKeyed($params = null)
    {
        return $this->selectKeyed($params)->getResult();
    }

    public function fetchCount($params = null, $expr = '*')
    {
        return $this->selectCount($params, $expr)->getResult();
    }

    public function fetchInserted()
    {
        return $this->selectInserted()->getResult();
    }

    public function insert(array $data = null)
    {
        $this->_db->insert($this->_table, $data ?: $this->getVal());
        return $this;
    }

    public function insertAll(array $data)
    {
        $this->_db->insertAll($this->_table, $data);
        return $this;
    }
    
    public function update($data = null, $param = null)
    {
        $param = array_merge($this->_param, $param);
        if ($this->{$this->_key} !== null) {
            $param[$this->_key] = $this->{$this->_key};
        }
        $this->_db->update($this->_table, $data, $param);
        return $this;
    }
            
    public function delete($param = null)
    {
        $this->_db->delete($this->_table, array_merge($this->_param, $param));
        return $this;
    }
    
    public function save($data = null, $id = null)
    {
        if ($data !== null) {
            $this->setVal($data);
        }
        if ($id !== null) {
            $this->setId($id);
        }

        if ($this->_key === null || $this->{$this->_key} === null) {
            $this->insert();
        }
        else {
            $this->update();
        }

        return $this;
    }    
    
    public function joinRaw()
    {
    }
    
    public function join()
    {
    }

    public function leftJoin()
    {
    }
    
    public function addRelation()
    {
    }


    protected function _sql($param, $select, $from)
    {
        $sql = [];
        
        if ($select) {
            $sql[':select'] = $this->_field;
        }
        
        if ($from) {
            $sql[':from'] = $this->_table;
        }
        
        $sql = array_merge($this->_param, (array)$param, $sql);
        
        return $this->_db->sql($sql);
    }
            
}
