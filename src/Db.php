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
    public function query($stmt)
    {
        return $this->getPdo()->query($this->sql($stmt));
    }
    
    /**
     * @return PDOStatement
     */
    public function prepare($stmt)
    {
        return $this->getPdo()->prepare($this->sql($stmt));
    }
    
    public function fetch($stmt)
    {
        $this->query($this->sql($stmt))->fetch(PDO::FETCH_ASSOC);
    }
            
    public function fetchAll($stmt)
    {
        return $this->query($this->sql($stmt))->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function fetchCol($stmt)
    {
        return $this->query($this->sql($stmt))->fetchColumn();
    }
    
    public function fetchVal($stmt)
    {
        $val = $this->query($this->sql($stmt))->fetchColumn();
        return is_array($val) ? $val[0] : null;
    }
    
    public function fetchKeyPair($stmt)
    {
        return $this->query($this->sql($stmt))->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
    
    public function fetchKeyed($stmt)
    {
        return $this->query($this->sql($stmt))->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
    }
    
    public function sql($stmt)
    {
        if (is_string($stmt)) {
            return $stmt;
        }
        
        // select, prefix
        $select = null;
        if (isset($stmt[':select'])) {
            if (!is_array($stmt[':select'])) {
                $select = $stmt[':select'];
            }
            else {
                $col = [];
                foreach ($stmt[':select'] as $k => $v) {
                    $col[] =  (strpos($v, ' ') !== false ? $v : "`$v`")
                        . (is_int($k) 
                            ? '' 
                            : ' as ' . (strpos($k, ' ') !== false ? $k : "'$k'")
                        );
                }
                $select = implode(', ', $col);
            }
            $select = 'SELECT ' 
                    . (array_key_exists(':prefix', $stmt) ? ' ' . $stmt[':prefix'] : '')
                    . $select;
        }
        
        // from, alias
        $from = array_key_exists(':from', $stmt) 
            ? ' FROM ' 
              . '`' . $stmt[':from'] . '`' 
              . (array_key_exists(':alias', $stmt) ? ' as ' . $stmt[':alias'] : '')
            : null;
        
        // join
        $join = array_key_exists(':join', $stmt) ? ' ' . implode(' ', $stmt[':join']) : null;
        
        // group
        $group = array_key_exists(':group', $stmt) ? ' GROUP BY ' . $stmt[':group'] : null;
        
        // having
        $having = array_key_exists(':having', $stmt) ? ' HAVING ' . $this->sqlCondition($stmt[':having']) : null;
        
        // group
        $order = array_key_exists(':order', $stmt) ? ' ORDER BY ' . $stmt[':order'] : null;
        
        // limit, offset
        $limit = null;
        $offset = null;
        if (array_key_exists(':paging', $stmt)) {
           $limit =  ' LIMIT ' . $stmt[':paging']->getLimit();
           $offset = ' OFFSET ' . $stmt[':paging']->getOffset();
        }
        else {
            if (array_key_exists(':limit', $stmt)) {
                $limit = ' LIMIT ' . $stmt[':limit'];
            }
            if (array_key_exists(':offset', $stmt)) {
                $offset = ' OFFSET ' . $stmt[':offset'];
            }
        }
        
        unset($stmt[':select'], $stmt[':prefix'], 
              $stmt[':from'], $stmt['alias'], $stmt[':join'],
              $stmt[':group'], $stmt[':having'], $stmt[':order'],
              $stmt[':paging'], $stmt[':limit'], $stmt[':offset']);
        
        // everything else is where
        $where = $this->sqlCondition($stmt);
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
        $sql     = [];

        foreach ($condition as $key => $value) {
            
            // <int> => [], <int> => '<raw sql>'
            if (is_int($key)) {
                $sql[] = is_array($value) ? '(' . $this->sqlCondition($value) . ')' : $value;
                continue;
            }
            
            // '#(a = {a} OR b = {b})' => [{a} => '1', {b} => '2']
            if ($key[0] === '%') {
                $key = substr($key, 1);
                foreach ($value as $find => $replace) {
                    $key = str_replace($find, $this->escape($replace), $key);
                }
                $sql[] = $key;
            }
            
            // logical operator - AND, OR
            if ($key === ':operator') {
                $logical = ' ' . trim($value) . ' ';
                continue;
            }

            $comparison = '=';
            
            if (strpos($key, ' ') !== false) {
                list ($key, $comparison) = explode(' ', $key, 2);
            }
            
            $key = $key[0] === '*' ? substr($key, 1) : "`$key`";
            
            
            if  (is_array($value) && $comparison === '=') {
                $comparison = 'IN';
            }

            switch ($comparison) {

                case 'BETWEEN':
                case 'NOT BETWEEN':
                    $sql[] = "$key $comparison"
                             . " '{$this->escape($value[0])}'"
                             . " AND '{$this->escape($value[1])}'";
                    break;

                case 'IN':
                case 'NOT IN':
                    foreach ($value as $k => $v) {
                        $value[$k] = $this->escape($v);
                    }
                    $sql[] = "$key $comparison ('" . implode("','", $value) . "')";
                    break;

                default:
                    $sql[] = "$key $comparison '{$this->escape($value)}'";
                    break;

            }

        }
        
        return implode($logical, $sql);

    }
    
    public function count($table, array $statement, $expr)
    {
        return $this->_db->query("SELECT COUNT($expr) FROM `$table` WHERE " . $this->sqlCondition($statement));
    }
    
    
    /**
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
    
    public function update($table, array $data, array $stmt)
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

        return $this->query("UPDATE `{$table}` SET " . implode(", ", $set) . ' WHERE ' . $this->sqlCondition($stmt));
    }
    
    public function delete($table, array $stmt)
    {
        return $this->_db->query("DELETE FROM `{$table}` WHERE " . $this->sqlCondition($stmt));
    }
    
}
