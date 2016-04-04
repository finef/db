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
    
    /**
     * Builds fragmenet or full raw sql query
     * 
     * $stmt options:
     * - ':select' => ['post_id', 'title' => 'post_title']  // `post_id`, `post_title` as 'title'
     * - ':select' => 'post_id, post_title as title' // post_id, post_title as title
     * - ':select' => ['|count' => '|COUNT(*)']  // COUNT(*) as count
     * - ':prefix' => 'SQL_NO_CACHE DISTINCT'
     * - ':from'   => 'post'
     * - ':from'   => ['p' => 'post']
     * - ':join'   => ['JOIN author ON (author_id = post_id_author)', 'LEFT JOIN img ON (author_id_img = img_id)']
     * - ':group'  => 'post_id'
     * - ':having' => 'post_id > 0'
     * - ':having' => ['post_id >' =>  '0']
     * - ':order'  => 'post_published DESC'
     * - ':paging' => \Fine\Paging\PagingInterface
     * - ':limit'  => 10,
     * - ':offset' => 0,
     * - 'post_level' => [1, 2, 3] // `post_level` IN ('1', '2',  '3')
     * - 'post_level BETWEEN' => [4, 5] // `post_level` BETWEEN '4' AND '5'
     * - 'post_level <>' => 4 // `post_level` <> '4'
     * - '|post_level <>' => 4 // post_level <> '4'
     * - "|post_level != '{a}')" => ['{a}' => 4] // post_level != '4'
     * - ':operator' => 'AND' // values: AND, OR; default: AND; logical operator that joins all conditions
     * - [':operator' => 'OR', 'post_level' => '1'
     *    [':operator' => 'OR', 'post_level' => '2', 'post_level' => '3']]
     *   // post_level = '1' OR (post_level = '2' OR  post_level = '3')
     * 
     * @param array $stmt
     * @return string Sql
     */
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
                $cols = [];
                foreach ($stmt[':select'] as $k => $v) {
                    $col = $v[0] === '|' ? substr($v, 1) : "`$v`";
                    if (is_int($k)) {
                        $col .= " " . ($k[0] === '|' ? substr($k, 1) : "'{$this->escape($k)}'");
                    }
                    $cols[] = $col;
                }
                $select = implode(', ', $cols);
            }
            $select = 'SELECT ' 
                    . (array_key_exists(':prefix', $stmt) ? ' ' . $stmt[':prefix'] : '')
                    . $select;
        }
        
        // from, alias
        $from = null;
        if (array_key_exists(':from', $stmt)) {
            $from = ' FROM ';
            if (is_array()) {
                $table = reset($stmt[':from']);
                $alias = key($stmt[':from']);
                $from .= "`$table` as '{$this->escape($alias)}'";
            }
            else if ($stmt[':from'][0] === '*') {
                $from .= substr($stmt[':from'], 1);
            }
            else {
                $from .= "`{$stmt[':from']}`";
            }
        }
        
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
              $stmt[':from'], $stmt[':join'],
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
    
    public function sqlCondition($condition)
    {
        
        if (is_string($condition)) {
            return $condition;
        }
        
        $logical = ' AND ';
        $sql     = [];

        foreach ($condition as $key => $value) {
            
            // <int> => [], <int> => '<raw sql>'
            if (is_int($key)) {
                $sql[] = is_array($value) ? '(' . $this->sqlCondition($value) . ')' : $value;
                continue;
            }
            
            // '#(a = {a} OR b = {b})' => [{a} => '1', {b} => '2']
            if ($key[0] === '|' && is_array($value)) {
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
            
            $key = $key[0] === '|' ? substr($key, 1) : "`$key`";
            
            
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
