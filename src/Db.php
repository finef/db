<?php

namespace \Fine\Db;

use \Fine\Container\Container;

class Db extends Container
{

    protected $_pdo;

    public function setPdo(\PDO $pdo)
    {
        $this->_pdo = $pdo;
        return $this;
    }

    /**
     * 
     * @return PDO
     */
    public function getPdo()
    {
        return $this->_pdo;
    }

    public function escape($string)
    {
        return $this->getPdo()->quote($string);
    }

    public function lastInsertId()
    {
        return $this->getPdo()->lastInsertId();
    }
    
    /**
     * @return PDOStatement
     */
    public function query($statement)
    {
        return $this->getPdo()->query($this->sql($statement));
    }
    
    /**
     * @return PDOStatement
     */
    public function prepare($statement)
    {
        return $this->getPdo()->prepare($this->sql($statement));
    }
    
    public function fetch($statement)
    {
        $this->query($this->sql($statement))->fetch(PDO::FETCH_ASSOC);
    }
            
    public function fetchAll($statement)
    {
        return $this->query($this->sql($statement))->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function fetchCol($statement)
    {
        return $this->query($this->sql($statement))->fetchColumn();
    }
    
    public function fetchVal($statement)
    {
        $val = $this->query($this->sql($statement))->fetchColumn();
        return is_array($val) ? $val[0] : null;
    }
    
    public function fetchKeyPair($statement)
    {
        return $this->query($this->sql($statement))->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
    
    public function fetchKeyed($statement)
    {
        return $this->query($this->sql($statement))->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
    }
    
    public function sql($statement)
    {
        
        if (is_string($statement)) {
            return $statement;
        }
        
        // select, prefix
        $select = null;
        if (isset($statement['select'])) {
            $select = [];
            foreach ($statement['select'] as $k => $v) {
                $select[] =  "$v" . (is_int($k) ? '' : ' as ' . $k);
            }
            $select = 'SELECT ' 
                    . (array_key_exists('prefix', $statement) ? ' ' . $statement['prefix'] : '')
                    . implode(', ', $select);
        }
        
        // from, alias
        $from = array_key_exists('from', $statement) 
            ? ' FROM ' 
              . '`' . $statement['from'] . '`' 
              . (array_key_exists('alias', $statement) ? ' as ' . $statement['alias'] : '')
            : null;
        
        // join
        $join = array_key_exists('join', $statement) ? ' ' . implode(' ', $statement['join']) : null;
        
        // group
        $group = array_key_exists('group', $statement) ? ' GROUP BY ' . $statement['group'] : null;
        
        // having
        $having = array_key_exists('having', $statement) ? ' HAVING ' . $this->sqlCondition($statement['having']) : null;
        
        // group
        $order = array_key_exists('order', $statement) ? ' ORDER BY ' . $statement['order'] : null;
        
        // limit, offset
        $limit = null;
        $offset = null;
        if (isset($statement['paging'])) {
           $limit =  ' LIMIT ' . $statement['paging']->getLimit();
           $offset = ' OFFSET ' . $statement['paging']->getOffset();
        }
        else {
            if (array_key_exists('limit', $statement)) {
                $limit = ' LIMIT ' . $statement['limit'];
            }
            if (array_key_exists('offset', $statement)) {
                $offset = ' OFFSET ' . $statement['offset'];
            }
        }
        
        unset($statement['select'], $statement['prefix'], 
              $statement['from'], $statement['alias'], $statement['join'],
              $statement['group'], $statement['having'], $statement['order'],
              $statement['paging'], $statement['limit'], $statement['offset']);
        
        // everything else is where
        $where = $this->sqlCondition($statement);
        if ($where) {
            $where = ' WHERE ' . $where;
        }
        else {
            $where = null;
        }
        
        return $select . $from . $join. $where . $group . $having . $order . $limit . $offset;
    }
    
    public function sqlCondition(array $condition)
    {
        
        $logical = ' AND ';

        foreach ($condition as $key => $value) {

            if (is_int($key)) {
                if (is_array($value)) {
                    $condition[] = '(' . $this->sqlCondition($value) . ')';
                }
                else {
                    $condition[] = $value;
                }
                continue;
            }

            switch ($key) {

                case 'operator':
                    $logical = ' ' . trim($value) . ' ';
                    break;

                default:

                    $comparison = '=';
                    if (strpos($key, ' ') !== false) {
                        list ($key, $comparison) = explode(' ', $key, 2);
                    }
                    if  (is_array($value) && $comparison === '=') {
                        $comparison = 'IN';
                    }

                    switch ($comparison) {

                        case 'BETWEEN':
                        case 'NOT BETWEEN':
                            $condition[] = "$key $comparison"
                                     . " '{$this->escape($value[0])}'"
                                     . " AND '{$this->escape($value[1])}'";
                            break;

                        case 'IN':
                        case 'NOT IN':
                            foreach ($value as $k => $v) {
                                $value[$k] = $this->escape($v);
                            }
                            $condition[] = "$key $comparison ('" . implode("','", $value) . "')";
                            break;

                        default:
                            $condition[] = "$key $comparison '{$this->escape($value)}'";
                            break;

                    }

            }

          
        }
        
        return implode($logical, $condition);

    }
    
    /**
     * 
     * @param string $table
     * @param array $row
     * @return self
     */
    public function insert($table, array $row)
    {
        $set = [];
        
        foreach ($row as $k => $v) {
            if (is_int($k)) {
                $set[] = $v;
            }
            else {
                $set[$k] = "`$k` = '{$this->escape($v)}'";
            }
        }

        return $this->query("INSERT INTO `{$table}` SET " . implode(', ', $set));
    }
    
    public function insertAll($table, array $rows)
    {
        $fields = array_keys($rows[0]);
        $values = [];

        foreach ($rows as $row) {
            $value = [];
            foreach ($fields as $field) {
                $value[$field] = "'{$this->escape($row[$field])}'";
            }
            $values[] = "(" . implode(', ', $value) . ")";
        }

        return $this->query("INSERT INTO `{$table}` (`" . implode('`, `', $fields) . "`) VALUES " . implode(', ', $values));
    }
    
    public function update($table, array $data, array $statement)
    {
        $set = array();
        
        foreach ($data as $k => $v) {
            if (is_int($k)) {
                $set[] = $v;
            }
            else {
                $set[$k] = "`$k` = '{$this->escape($v)}'";
            }
        }

        return $this->query("UPDATE `{$table}` SET " . implode(", ", $set) . ' WHERE ' . $this->sqlCondition($statement));
    }
    
    public function delete($table, array $statement)
    {
        return $this->_db->query("DELETE FROM `{$table}` WHERE " . $this->sqlCondition($statement));
    }
    
}
