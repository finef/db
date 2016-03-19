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
        return $this->getPdo()->query($statement);
    }
    
    /**
     * @return PDOStatement
     */
    public function prepare($statement)
    {
        return $this->getPdo()->prepare($statement);
    }
    
    public function fetch($statement)
    {
        $this->query($statement)->fetch(PDO::FETCH_ASSOC);
    }
            
    public function fetchAll($statement)
    {
        return $this->query($statement)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function fetchCol($statement)
    {
        return $this->query($statement)->fetchColumn();
    }
    
    public function fetchVal($statement)
    {
        $val = $this->query($statement)->fetchColumn();
        return is_array($val) ? $val[0] : null;
    }
    
    public function fetchKeyPair($statement)
    {
        return $this->query($statement)->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
    
    public function fetchKeyed($statement)
    {
        return $this->query($statement)->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
    }
    
}
