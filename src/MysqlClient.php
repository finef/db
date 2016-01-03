<?php

namespace \Fine\Db;

use \Fine\Container\Container;

class MysqlClient extends Container
{

    protected $_connect;
    protected $_query;
    protected $_result;

    public function connect($sHostname, $sUsername, $sPassword)
    {
        $this->_connect = @mysqli_connect($sHostname, $sUsername, $sPassword);

        if ($this->_connect) {
            return $this;
        }

        /* @TODO exception */
    }

    public function selectDb($sDatabaseName)
    {
        if (mysqli_select_db($this->_connect, $sDatabaseName)) {
            return $this;
        }
        /* @TODO exception */
    }

    public function setConnection($connection)
    {
        $this->_connect = $connection;
        return $this;
    }

    public function getConnection()
    {
        return $this->_connect;
    }

    public function escape($string)
    {
        return mysqli_real_escape_string($this->_connect, $string);
    }

    public function result()
    {
        return $this->_result;
    }

    /**
     *  - zasob zapytania lub falsza dla SELECT, SHOW, EXPLAIN i DESCRIBE;
     *  - true lub false dla UPDATE, DELETE...
     *
     * @param string $query Zapytanie SQL
     * @return resource
     */
    public function query($query)
    {
        $this->_query = $query;
        if (($this->_result = mysqli_query($this->_connect, $query))) {
            return $this->_result;
        }
        throw $this->_exceptionQuery();
    }

    /**
     * Zwraca wyselekcjonowany rekord jako tablice asocjacyjną
     *
     * @param string $query Zapytanie SQL
     * @return array|false
     */
    public function row($query)
    {
        $this->_query = $query;
        if (($this->_result = mysqli_query($this->_connect, $query))) {
            return mysqli_fetch_assoc($this->_result);
        }
        throw $this->_exceptionQuery();
    }

    /**
     * Zwraca wyselekcjonowane rekordy jako dwu wymiarową tablice, gdzie tablice wymiaru 2 jest asocjacyjna
     *
     * @param string $query Zapytanie SQL
     * @return array|false
     */
    public function rows($query)
    {
        $this->_query = $query;
        if (($this->_result = mysqli_query($this->_connect, $query))) {
            $a = array();
            while ($i = mysqli_fetch_assoc($this->_result)) {
                $a[] = $i;
            }
            return $a;
        }
        throw $this->_exceptionQuery();
    }


    /**
     * Zwraca jedno wymiarową tablice numeryczną
     * gdzie wartością pola tablicy jest pierwsze pole z wyselekcjonowanych rekordow
     *
     * @param string $query Zapytanie SQL
     * @return array|false
     */
    public function col($query)
    {
        $this->_query = $query;
        if (($this->_result = mysqli_query($this->_connect, $query))) {
            $a = array();
            while ($i = mysqli_fetch_row($this->_result)) {
                $a[] = $i[0];
            }
            return $a;
        }
        throw $this->_exceptionQuery();
    }

    /**
     * Zwraca jedno wymiarową tablice asocjacyjną gdzie kluczem jest pierwsze pole a wartością drugie z wyselekcjonowanych rekordow
     *
     * @param string $query Zapytanie SQL
     * @return array|false
     */
    public function cols($query)
    {
        $this->_query = $query;
        if (($this->_result = mysqli_query($this->_connect, $query))) {
            $a = array();
            while ($i = mysqli_fetch_row($this->_result)) {
                $a[$i[0]] = $i[1];
            }
            return $a;
        }
        throw $this->_exceptionQuery();
    }

    /**
     * Zwraca wartosc pierwszego pola z pierwszego wyselekcjonowanego rekordu
     *
     * @param string $query Zapytanie SQL
     * @return string
     */
    public function val($query)
    {
        $this->_query = $query;
        if (($this->_result = mysqli_query($this->_connect, $query))) {
            if (($a = mysqli_fetch_row($this->_result))) {
                return $a[0];
            }
            return null;
        }
        throw $this->_exceptionQuery();
    }

    /**
     * Zwraca wyselekcjonowany rekord jako tablice zwykłą (numeryczną)
     *
     * @param string $query Zapytanie SQL
     * @return array
     */
    public function rowNum($query)
    {
        $this->_query = $query;
        if (($this->_result = mysqli_query($this->_connect, $query))) {
            return mysqli_fetch_row($this->_result);
        }
        throw $this->_exceptionQuery();
    }

    /**
     * Zwraca wyselekcjonowane rekordy jako dwu wymiarową tablice zwykłą (numeryczną)
     *
     * @param string $query Zapytanie SQL
     * @return array|flase Tablica lub falsz
     */
    public function rowsNum($query)
    {
        $this->_query = $query;
        if (($this->_result = mysqli_query($this->_connect, $query))) {
            $a = array();
            while ($i = mysqli_fetch_row($this->_result)) {
                $a[] = $i;
            }
            return $a;
        }
        throw $this->_exceptionQuery();
    }

    /**
     * Zwraca wyselekcjonowane rekordy jako dwu wymiarową tablice
     * gdzie kluczem jest pierwsze pole a wartością jest tablica asocjacyjna
     *
     * @param string $query Zapytanie SQL
     * @return array|flase Tablica lub falsz
     */
    public function keyed($query)
    {
        $this->_query = $query;
        if (($this->_result = mysqli_query($this->_connect, $query))) {
            $a = array();
            while ($i = mysqli_fetch_row($this->_result)) {
                $a[$i[0]] = $i;
            }
            return $a;
        }
        throw $this->_exceptionQuery();
    }

    /**
     * Zwraca wartosc klucza głownego ostatnio dodanego rekordu
     *
     * @return int Wartosc klucza ostatnio dodanego rekordu
     */
    public function lastInsertId()
    {
        return $this->val("SELECT LAST_INSERT_ID()");
    }

    public function lastQuery()
    {
        return $this->_query;
    }

    /**
     * Zwraca tablicę asocjacyjną zawierającą pobrany wiersz, lub FALSE jeżeli nie ma więcej wierszy w wyniku.
     *
     * @return array|false
     */
    public function fetch()
    {
        return mysqli_fetch_assoc($this->_result);
    }

    public function fetchByResult($rQueryResult)
    {
        return mysqli_fetch_assoc($rQueryResult);
    }

    /**
     * Zwraca tablicę zwykłą (numeryczną) zawierającą pobrany wiersz, lub FALSE jeżeli nie ma więcej wierszy w wyniku.
     *
     * @return array|false
     */
    public function fetchNum()
    {
        return mysqli_fetch_row($this->_result);
    }

    public function fetchNumByResult($rQueryResult)
    {
        return mysqli_fetch_row($rQueryResult);
    }

    /**
     * Zwraca liczbę wierszy w wyniku
     *
     * @return int|false
     */
    public function countSelected()
    {
        return mysqli_num_rows($this->_result);
    }

    /**
     * Zwraca liczbę wierszy w wyniku
     *
     * @return int|false
     */
    public function countSelectedByResult($rQueryResult)
    {
        return mysqli_num_rows($rQueryResult);
    }

    /**
     * Zwraca liczbe zmodyfikowanych wierszy
     *
     * @return int
     */
    public function countAffected()
    {
        return mysqli_affected_rows($this->_connect);
    }

    /**
     * Zamyka połączenie z serwerem MySQL
     *
     * @return boolean
     */
    public function close()
    {
        if ($this->_connect) {
            mysqli_close($this->_connect);
            $this->_connect = null;
            return true;
        }
        return false;
    }

    public function errorMsg()
    {
        return @mysqli_error($this->_connect);
    }

    public function errorNo()
    {
        return @mysqli_errno($this->_connect);
    }

    /**
     * Buduje bezpieczne zapytanie SQL metoda "zaslepek"
     *
     * @param string $query Zapytanie z "zaslepkami"
     * @param array $aArgs zmienne do przeparsowania
     * @return string Zapytanie SQL
     *
     */
    public function prepare($query, array $vars)
    {
        foreach ($var as $name => $value) {
            $query = str_replace($name, $this->escape($value), $query);
        }

        return $query;
    }

    protected function _exceptionQuery()
    {
        $exception            = new f_db_exception_query($this->errorMsg(), $this->errorNo());
        $exception->Query     = $this->_query;
        $exception->_metadata = array('Query' => 'mysqli');

        return $exception;
    }

}
