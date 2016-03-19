<?php

namespace \Fine\Db;

class Client
{

    protected $_pdo;

    public function setPdo(\PDO $pdo)
    {
        $this->_pdo = $pdo;
        return $this;
    }

    public function getPdo()
    {
        return $this->_pdo;
    }

    public function escape($s)
    {
        return $this->getPdo();
    }

}
