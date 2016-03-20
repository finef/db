<?php

namespace \Fine\Db;

use \Fine\Std\ParamTrait;
use \Fine\Paging\PagingInterface;

class Table
{
    
    use ParamTrait;

    protected $_db;
    protected $_table;
    protected $_alias;
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

    public function setAlias($alias)
    {
        $this->_alias = $alias;
        return $this;
    }

    public function getAlias()
    {
        return $this->_alias;
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

    /** @TODO field */


}