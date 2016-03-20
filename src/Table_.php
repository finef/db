<?php
// @TODO Iterator, Countable
namespace \Fine\Db;

use \Fine\Std\ParamTrait;
use \Fine\Paging\PagingInterface;

class Table implements TableInteraface, Iterator, Countable
{

    use ParamTrait;

    protected $_db;
    protected $_table;
    protected $_alias;
    protected $_key;
    protected $_field;
    protected $_select    = array();
    protected $_param     = array();
    protected $_result;
    protected $_dependent = array();
    protected $_paging;
    protected $_tuples;

    /* setup */

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

    public function removeField($asField)
    {
        foreach (is_array($asField) ? $asField : explode(' ', $asField) as $i) {
            $aRemove[$i] = true;
        }
        foreach ($this->_field as $k => $v) {
            if (isset ($aRemove[$v])) {
                unset($this->_field[$k]);
            }
        }

        return $this;
    }

    /**
    * Czy model zawiera podane pole
    *
    * @param string $sField
    * @return boolean
    */
    public function hasField($sField)
    {
      return in_array($sField, $this->_field);
    }

    /* values */

    /**
    * Ustawia lub pobiera wartosci modelu, nie zmienia wartosci klucza glownego
    *
    * @param array|null $aKeyValue Wartości jako tablica asocjacyjna
    * @param array|string $asRestrictionField Pola w ktorych maja byc ustawione wartosci
    * @return array|$this Wartości jako tablica asocjacyjna
    */
    public function val($aKeyValue = null, $asRestrictionField = null)
    {
      if (func_num_args() == 0) {
          foreach ($this->_field as $i) {
              $a[$i] = $this->{$i};
          }
          if ($this->_key != null) {
              $a[$this->_key] = $this->id();
          }

          return $a;
      }
      else if ($asRestrictionField === null) {
          foreach ($this->_field as $i) {
              if (isset($aKeyValue[$i]) && $i != $this->_key) {
                  $this->{$i} = $aKeyValue[$i];
              }
          }
      }
      else {
          if (! is_array($asRestrictionField)) {
              $asRestrictionField = explode(' ', $asRestrictionField);
          }
          foreach ($this->_field as $i) {
              if (isset($aKeyValue[$i]) && $i != $this->_key && in_array($asRestrictionField, $i)) {
                  $this->{$i} = $aKeyValue[$i];
              }
          }
      }
      return $this;
    }

    /**
    * Ustala pola modelu i ustawia wartości modelu, nie zmienia wartosci klucza glownego
    *
    * @param array $aKeyValue Pola jako klucze $aKeyValue, wartosci modelu jako wartosci $aKeyValue
    * @return $this
    */
    public function fieldAndVal($aKeyValue)
    {
      $this->field(array_keys($aKeyValue));
      $this->val($aKeyValue);
      return $this;
    }

    /**
    * Czysci wartosci modelu
    */
    public function removeVal()
    {
      $this->_ = null;
      foreach (self::$_metadata[$this->_class]['field'] as $field) {
          $this->{$field} = null;
      }
      $this->_dependent = array();
    }

    /**
    * Zwraca dane ostatniego zapytanie
    * @return mixed
    */
    public function data()
    {
      return $this->_tuples;
    }

    /**
    * Ustala lub pobiera wartość dla klucza glownego
    *
    * @param string|null $sValue Id
    * @return string|$this Id
    */
    public function id($sValue = null)
    {
      if (func_num_args() == 0) {
          return $this->{$this->_key};
      }
      else {
          $this->{$this->_key} = $sValue;
          return $this;
      }
    }

    /* params for queries */

    public function paramId($isId = null)
    {
        if (func_num_args() == 0) {
            return $this->_param[$this->_key];
        }

        $this->_param[$this->_key] = $isId;
        return $this;

    }

    public function paramPaging(f_paging $paging = null)
    {
      if ($paging !== null) {
          $this->paging($paging);
      }

      $this->paging()
              ->all($this->fetchCount())
              ->paging();

      $this->param(self::PARAM_PAGING, $this->paging());

      return $this;
    }

    public function isParam($sKey)
    {
      return isset($this->_param[$sKey]);
    }

    public function removeParam($asKey = null)
    {
      if (func_num_args() == 0) {
          $this->_param = array();
      }
      else {
          if (! is_array($asKey)) {
              $asKey = array($asKey);
          }
          foreach ($asKey as $i) {
              unset ($this->_param[$i]);
          }
      }
      return $this;
    }

    /* select */

    /**
    * Selekcjonuje jeden rekord z tabeli, zapisuje go do pola _, zapisuje pobrane dane do pol publicznych obiektu
    *
    * @param array|integer|string $aisParam Parametry
    * @return $this
    */
    public function select($aisParam = null)
    {
      if (func_num_args() != 0 && !is_array($aisParam) && !$this->_param && $this->_key) {
          $aisParam = array($this->_key => $this->_db->escape($aisParam));
      }

      if (($data = $this->_db->row($this->_sql($aisParam, true, true, true)))) {
          $this->val($data);
          $this->_ = $data;
          if ($this->_key !== null && isset($data[$this->_key])) {
              $this->{$this->_key} = $data[$this->_key];
          }
      }
      else {
          $this->removeVal();
      }
      return $this;
    }

    /**
    * Selekcjonuje wiele rekordow z tabeli, zaisuje je do _, nie zapisuje pobranych danych do pol publicznych obiektu
    *
    * @param array|integer|string|null $aisParam Parametry
    * @return $this
    */
    public function selectAll($aParam = null)
    {
      $this->_ = $this->_db->rows($this->_sql($aParam, true, true, true));
      return $this;
    }

    /**
    * Pobiera jedno wymiarową tablice numeryczną gdzie wartościami tablicy jest pierwsze pole z wyselekcjonowanych rekordow
    *
    * @param array|integer|string|null $aisParam Parametry
    * @return $this
    */
    public function selectCol($aParam = null)
    {
      $this->_ = $this->_db->col($this->_sql($aParam, true, true, true));
      return $this;
    }

    /**
    * Pobiera jedno wymiarową tablice asocjacyjną gdzie kluczem jest pierwsze pole a wartością drugie z wyselekcjonowanych rekordow
    *
    * @param array|integer|string|null $aisParam Parametry
    * @return $this
    */
    public function selectCols($aParam = null)
    {
      $this->_ = $this->_db->cols($this->_sql($aParam, true, true, true));
      return $this;
    }

    /**
    * Pobiera wartosc pierwszego pola z pierwszego wyselekcjonowanego rekordu, zapisuje ja do _
    *
    * @param array|integer|string|null $aisParam Parametry
    * @return $this
    */
    public function selectVal($aisParam = null)
    {
      if (func_num_args() != 0 && !is_array($aisParam) && !$this->_param && $this->_key) {
          $aisParam = array($this->_key => $this->_db->escape($aisParam));
      }
      $this->_ = $this->_db->val($this->_sql($aisParam, true, true, true));
      return $this;
    }

    /**
    * Wykonuje zapytanie SELECT COUNT, wartosc zapytania zapisuje do pola _
    *
    * @param array|integer|string|null $aisParam Parametry
    * @param string $sExpr Domyślnie *
    * @return $this
    */
    public function selectCount($aParam = null, $sExpr = '*')
    {
      $this->_ = $this->_db->val("SELECT COUNT($sExpr)".$this->_sql($aParam, false, true, true));
      return $this;
    }

    /**
    * Pobiera dwu wymiarową tablice gdzie kluczem jest pierwsze pole a wartością tablica asocjacyjna
    *
    * @param array|integer|string|null $aisParam Parametry
    * @return $this
    */
    public function selectKeyed($aParam = null)
    {
      $this->_ = $this->_db->keyed($this->_sql($aParam, true, true, true));
      return $this;
    }

    /**
    * Selekcjonuje rekordy, ktore pobierane sa metoda next()
    *
    * @param array|integer|string|null $aisParam Parametry
    * @return $this
    */
    public function selectLoop($aParam = null)
    {
      $this->_result = $this->_db->query($this->_sql($aParam, true, true, true));
      return $this;
    }

    /**
    * Pobiera kolejny rekod zapytania wykonanego przez metode selectLoop()
    *
    * @return $this
    */
    public function selectNext()
    {
      if (($data = $this->_db->fetchUsingResult($this->_result))) {
          $this->val($data);
          $this->_ = $data;
          if ($this->_key !== null && isset($data[$this->_key])) {
              $this->{$this->_key} = $data[$this->_key];
          }
      }
      else {
          $this->removeVal();
      }
      return $this;
    }

    /**
    * Pobiera do modelu ostatnio dodany rekorod do bazy
    *
    * @return $tthis
    */
    public function selectInserted()
    {
      $row = $this->_db->row("SELECT * FROM `$this->_table` WHERE `$this->_key` = LAST_INSERT_ID()");
      $this->val($row);
      $this->{$this->_key} = $row[$this->_key];
      return $this;
    }

    /* fetch - select + return data */

    /**
    * Selekcjonuje jeden rekord z tabeli
    *
    * @param array|integer|string $aisParam Parametry
    * @return array
    */
    public function fetch($aisParam = null)
    {
      $this->select($aisParam);
      return $this->_;
    }

    /**
    * Selekcjonuje wiele rekordow z tabeli
    *
    * @param array|integer|string|null $aisParam Parametry
    * @return array
    */
    public function fetchAll($aParam = null)
    {
      $this->selectAll($aParam);
      return $this->_;
    }

    /**
    * Zwraca jedno wymiarową tablice numeryczną gdzie wartościami tablicy jest pierwsze pole z wyselekcjonowanych rekordow
    *
    * @param array|integer|string|null $aisParam Parametry
    * @return array|false Tablice | W przpadku nie wyselekcjonowania żadnych rekordow
    */
    public function fetchCol($aParam = null)
    {
      $this->selectCol($aParam);
      return $this->_;
    }

    /**
    * Pobiera jedno wymiarową tablice asocjacyjną gdzie kluczem jest pierwsze pole a wartością drugie z wyselekcjonowanych rekordow
    *
    * @param array|integer|string|null $aisParam Parametry
    * @return array
    */
    public function fetchCols($aParam = null)
    {
      $this->selectCols($aParam);
      return $this->_;
    }

    /**
    * Pobiera wartosc pierwszego pola z pierwszego wyselekcjonowanego rekordu, zapisuje ja do _
    *
    * @param array|integer|string|null $aisParam Parametry
    * @return string
    */
    public function fetchVal($aisParam = null)
    {
      $this->selectVal($aisParam);
      return $this->_;
    }

    /**
    * Wykonuje zapytanie SELECT COUNT, wartosc zapytania zapisuje do pola _
    *
    * @param array|integer|string|null $aisParam Parametry
    * @param string $sExpr Domyślnie *
    * @return int
    */
    public function fetchCount($aParam = null, $sExpr = '*')
    {
      $this->selectCount($aParam, $sExpr);
      return $this->_;
    }

    /**
    * Pobiera dwu wymiarową tablice gdzie kluczem jest pierwsze pole a wartością tablica asocjacyjna
    *
    * @param array|integer|string|null $aisParam Parametry
    * @return array
    */
    public function fetchKeyed($aParam = null)
    {
      $this->selectKeyed($aParam);
      return $this->_;
    }

    /**
    * Pobiera kolejny rekod zapytania wykonanego przez metode selectLoop()
    *
    * @return array
    */
    public function fetchNext()
    {
      if (($data = $this->_db->fetchUsingResult($this->_result))) {
          $this->val($data);
          $this->_ = $data;
          if ($this->_key !== null && isset($data[$this->_key])) {
              $this->{$this->_key} = $data[$this->_key];
          }
      }
      else {
          $this->removeVal();
      }
      return $this;
    }

    /**
    * Pobiera do modelu ostatnio dodany rekorod do bazy
    *
    * @return $tthis
    */
    public function fetchInserted()
    {
      $row = $this->_db->row("SELECT * FROM `$this->_table` WHERE `$this->_key` = LAST_INSERT_ID()");
      $this->val($row);
      $this->{$this->_key} = $row[$this->_key];
      return $this;
    }

    /* queries - insert, update, delete */

    /**
    * Dodaje rekord do tabeli
    *
    * Nieprawidłowe klucze w tablicy są pomijane, musi sie zgadzac kolejność i ilość
    *
    * @param array $aData Tablica jedno wymiarowa asocjacyjna
    * @return f_m
    */
    public function insert($aData = null)
    {

      if (!$aData) {
          $aData = $this->val();
      }

      $aSet = array();
      foreach ($aData as $k => $v) {
          if (is_int($k)) {
              $aSet[] = $v;
          }
          else {
              $aSet[$k] = "`$k` = '{$this->_db->escape($v)}'";
          }
      }
      if ($this->_hardlink) {
          foreach ($this->_hardlink as $k => $v) {
              if (is_int($k)) {
                  continue;
              }
              $aSet[$k] = "`$k` = '{$this->_db->escape($v)}'";
          }
      }

      $this->_db->query("INSERT INTO `{$this->_table}` SET " . implode(', ', $aSet));

      return $this;
    }

    /**
    * Dodaje rekordy do tabeli
    *
    * Nieprawidlowe klucze w tablicy są pomijane, musi sie zgadzac kolejnosc i ilosc
    *
    * @param array $aData Tablica dwuwymiarowa gdzie drugi wymiar to tablica asocjacyjna
    * @return f_m
    */
    public function insertAll($aData = null)
    {
      if (func_num_args() == 0) {
          $aData = $this->_;
      }

      $aModelField = array();
      foreach ($this->_field as $i) {
          $aModelField[$i] = true;
      }

      $aField  = array();
      $aValues = array();
      foreach (current($aData) as $k => $v) {
          if (isset($aModelField[$k])) {
              $aField[$k] = true;
          }
      }
      if ($this->_hardlink) {
          $aLinkage = $this->_hardlink;
          foreach ($aLinkage as $k => $v) {
              if (is_int($k)) {
                  continue;
              }
              $aField[$k] = true;
              $aLinkage[$k] = $this->_db->escape($v);
          }
      }

      foreach ($aData as $data) {
          $aRow = array();
          foreach ($aField as $field => $true) {
              $aRow[$field] = "'{$this->_db->escape($data[$field])}'";
          }
          if ($this->_hardlink) {
              foreach ($aLinkage as $k => $v) {
                  $aRow[$k] = "'$v'";
              }
          }
          $aValues[] = "(".implode(', ', $aRow).")";
      }

      $this->_db->query("INSERT INTO `{$this->_table}` (`".implode('`, `', array_keys($aField))."`) VALUES ".implode(', ', $aValues));

      return $this;
    }

    /**
    * Modyfikuje rekord lub rekordy
    *
    * @param array $aData Tablica jedno wymiarowa asocjacyjna
    * @param array|integer|string|null $aisParam Parametry
    * @return $this
    */
    public function update($aData = null, $aisParam = null)
    {
      if (!$aisParam && !$this->_param && $this->_key !== null) {
          $aisParam = array($this->_key => $this->{$this->_key});
      }
      else if (isset($aisParam) && !is_array($aisParam) && $this->_key !== null) {
          $aisParam = array($this->_key => $aisParam);
      }

      if (!$aisParam  && !$this->_param) {
          throw new LogicException("Oczekiwany warunek modyfikowania rekordow.");
      }

      if (!$aData) {
          $aData = $this->val();
      }

      $aSet = array();
      foreach ($aData as $k => $v) {
          if (is_int($k)) {
              $aSet[] = $v;
          }
          else {
              $aSet[$k] = "`$k` = '{$this->_db->escape($v)}'";
          }
      }
      if ($this->_hardlink) {
          foreach ($this->_hardlink as $k => $v) {
              if (is_int($k)) {
                  continue;
              }

              $aSet[$k] = "`$k` = '{$this->_db->escape($v)}'";
          }
      }

      $this->_db->query("UPDATE `{$this->_table}` SET " . implode(", ", $aSet) . $this->_sql($aisParam, false, false, true));

      return $this;
    }


    /**
    * Usuwa rekord lub rekordy
    *
    * Gdy brak jakiegokolwiek warunku zapytanie DELETE nie zostanie wykonane
    * Aby usunąć wszystkie rekordy wykonaj delete(array('1')) wtedy wygeneruje sie zapytanie DELETE FROM table WHERE 1
    *
    * @param array|integer|string|null $aisParam Parametry
    * @return int 0 - sucess, 1 - Bład zapytania DELETE, <2,n> - Błąd w metodzie przeciązającej tą metode;
    */
    public function delete($aisParam = null)
    {
      if (func_num_args() != 0 && !is_array($aisParam) && $this->_key) {
          $aisParam = array($this->_key => $aisParam);
      }
      else if (func_num_args() == 0 && !$this->_param && $this->_key !== null) {
          $aisParam = array($this->_key => $this->{$this->_key});
      }

      if (!$this->_param && !$aisParam) {
          throw new LogicException("Oczekiwany warunek kasacji rekordow");
      }

      $this->_db->query("DELETE FROM `{$this->_table}`" . $this->_sql($aisParam, false, false, true));
      return $this;
    }

    /**
    * Zapisuje rekord do bazy, wkonuje zapytanie INSERT jesli wartosc klucza podstawowego jest rowna null, lub UPDATE w przeciwnym wypadku
    *
    * @param array $aData Tablica jedno wymiarowa asocjacyjna
    * @param integer|null $iId Id rekordu
    * @return $this
    */
    public function save($aData = null, $iId = null)
    {
      if ($aData !== null) {
          $this->val($aData);
      }
      if ($iId !== null) {
          $this->{$this->_key} = $iId;
      }

      if ($this->_key === null || $this->{$this->_key} === null) {
          $this->insert();
      }
      else {
          $this->update();
      }

      return $this;
    }

    /* relations */

    public function relations()
    {
      self::$_metadata[$this->_class]['rel'] = array();
    }

    /**
    * Ustala/pobiera relacje
    *
    * # Ustalanie
    *
    * Standardowe relacje nie musza byc definiowane.
    * Relacje ustalane sa w wlasciwym modelu w metodzie `relations` np.
    *  class m_post
    *  ...
    *  public function relations()
    *  {
    *      $this->relation('user', 'post_id_user', 'user_id');
    *      $this->relation('user_active', 'post_id_user', 'user_id', "user_status = 'active'");
    *  }
    *
    * # Pobieranie
    *
    *  print_r($this->relation('user'));
    *
    *  array(
    *      [rel_field]     => post_id_user
    *      [rel_rel_table] => post
    *      [rel_rel_field] => user_id
    *      [rel_condition] => null
    *  )
    *
    * @param type $sName Nazwa relacji
    * @param type $sThisField Pole aktualnego modelu
    * @param type $sRelatedField Pole obce
    * @param type $sCondition Warunek
    * @return \f_m
    */
    public function relation($sName, $sThisField = null, $sRelatedField = null, $asCondition = null)
    {
      $relations =& self::$_metadata[$this->_class]['rel'];

      /**
       * setter
       */

      if (func_num_args() > 1) {
          $relations[$sName] = array(
              'rel_field'     => $sThisField,
              'rel_rel_table' => current(explode('_', $sRelatedField, 2)),
              'rel_rel_field' => $sRelatedField,
              'rel_condition' => (is_string($asCondition) ? array($asCondition) : $asCondition),
          );
          return $this;
      }

      /**
       * getter
       */

      // lazy load relations
      if ($relations === null) {
          $this->relations();
      }

      if (!isset($relations[$sName])) {

          list($relatedTable, $relatedSuffix) = explode('_', $sName, 2);

          $relation = array(
              'rel_field'     => '',
              'rel_rel_table' => $relatedTable,
              'rel_rel_field' => '',
          );

          // relation n:1 (ref)
          if (in_array("{$this->_table}_id_{$sName}", self::$_metadata[$this->_class]['field'])) {

              $relation['rel_field']     = "{$this->_table}_id_{$sName}";
              $relation['rel_rel_field'] = "{$relatedTable}_id";

          }
          else {

              $relation['rel_field'] = "{$this->_table}_id";
              $relatedClass          = self::$_metadata[$this->_class]['prefix'] . $relatedTable;
              $relatedField          = "{$relatedTable}_id_{$this->_table}"
                                     . ($relatedSuffix !== null ? "_$relatedSuffix" : '');

              // lazy init related model metadata - we need fields
              if (! isset(self::$_metadata[$relatedClass])) {
                  new $relatedClass();
              }

              $relation['rel_rel_field'] = in_array($relatedField, self::$_metadata[$relatedClass]['field'])
                                         ? $relatedField         // relation 1:n (dep)
                                         : "{$relatedTable}_id"; // relation 1:1
          }

          $relations[$sName] = $relation;

      }

      return $relations[$sName];
    }

    public function joinRaw($sSqlJoinFragment, $asFields = null)
    {
      $this->_param['join'][] = $sSqlJoinFragment;

      if (func_num_args() > 1) {
          $this->addField($asFields);
      }

      return $this;
    }

    /**
    * Wykonuje JOIN dołączenie do tabeli według referencji
    *
    * @param string $asRefName Nazwa referencji
    * @param array|string $asField Pola jako tablica lub string gdzie pola są oddzielone znakiem spacji
    * @param string $asModel Nazwa modelu
    * @return $this
    */
    public function join($sRelation, $asField = null, $sModel = null, $sJoinAlias = null, $sModelAlias = null)
    {
      list($sSqlJoinFragment, $aFields) = $this->_sqlJoin('JOIN', $sRelation, $asField, $sModel, $sJoinAlias, $sModelAlias);

      $this->joinRaw($sSqlJoinFragment, $aFields);

      return $this;
    }

    /**
    * Wykonuje LEFT JOIN dołączenie do tabeli według referencji
    *
    * @param string $asRefName Nazwa referencji
    * @param array|string $asField Pola jako tablica lub string gdzie pola są oddzielone znakiem spacji
    * @param string $asModel Nazwa modelu
    * @return $this
    */
    public function joinLeft($sRelation, $asField = null, $sModel = null, $sJoinAlias = null, $sModelAlias = null)
    {
      list($sSqlJoinFragment, $aFields) = $this->_sqlJoin('LEFT JOIN', $sRelation, $asField, $sModel, $sJoinAlias, $sModelAlias);

      $this->joinRaw($sSqlJoinFragment, $aFields);

      return $this;
    }

    /**
    *
    * @param type $sDependentModelName
    * @return f_m
    */
    public function dependent($sDependentModelName)
    {

      $relation = $this->relation($sDependentModelName);


      $class = self::$_metadata[$this->_class]['prefix'] . $relation['rel_rel_table'];
      $model = new $class;
      $model->hardlink($relation['rel_rel_field'], $this->{$relation['rel_field']});
      if (isset($relation['rel_condition'])) {
          foreach ($relation['rel_condition'] as $k => $v) {
              $model->hardlink($k, $v);
          }
      }

      $model->{$relation['rel_rel_field']} = $this->{$relation['rel_field']};

      $this->_dependent[$sDependentModelName] = $model;

      return $model;

    }

    /* additional */

    public function lastInsertId()
    {
      $this->selectInserted();
      return $this->id();
    }

    public function query($aParam, $bSelect, $bFrom, $bLinkage)
    {
      return $this->_sql($aParam, $bSelect, $bFrom, $bLinkage);
    }

    /**
    * Ustala/pobiera obiekt lub konfiguracje stronnicowania
    *
    * @param f_paging|array $aoPaging
    * @return f_m|f_paging
    */
    public function paging($aoPaging = null)
    {
      if (func_num_args() == 0) { // getter
          if ($this->_paging === null) {
              $this->_paging = new f_paging();
          }
          return $this->_paging;
      }
      else { // setter
          if (is_object($aoPaging)) {
              if (!$aoPaging instanceof f_paging) {
                  throw new f_m_exception_invalidArgument("Oczekiwano argumentu o typie f_paging");
              }
              $this->_paging = $aoPaging;
          }
          else {
              if ($this->_paging === null) {
                  $this->_paging = new f_paging();
              }
              foreach ((array)$aoPaging as $k => $v) {
                  $this->_paging->{$k}($v);
              }
          }
          return $this;
      }
    }

    /* friend api */

    /**
    * @todo napisac opis
    * @param <type> $isKey
    * @param <type> $sValue
    */
    public function hardlink($sKey, $sValue)
    {
      $this->_hardlink[$sKey] = $sValue;
    }

    /* private api */

    /**
    * Buduje zapytanie SQL
    *
    * @param array|integer|null|string $aParam Parametry
    * @param boolean $bSelect Czy budowac fragment SELECT
    * @param boolean $bFrom Czy budowac fragment FROM
    * @param boolean $bLinkage Czy budowac fragment powiazania
    * @return string Zapytanie SQL lub fragment zapytania
    */
    protected function _sql($aParam, $bSelect, $bFrom, $bLinkage)
    {

      $select          = null;
      $from            = null;
      $where           = null;
      $groupby         = null;
      $having          = null;
      $orderby         = null;
      $limit           = null;
      $offset          = null;
      $field           = $this->_field;
      $aParam          = array_merge((array)$this->_param, (array)$aParam);
      $logicalOperator = ' AND ';

      foreach ($aParam as $paramKey => $paramValue) {

          if (is_int($paramKey)) {
              $where[] = $paramValue;
              continue;
          }

          switch ($paramKey) {

              case self::PARAM_FIELD:
                  $field = is_array($paramValue) ? $paramValue : explode(' ', $paramValue);
                  break;

              case self::PARAM_OPERATOR:
                  $logicalOperator = $paramValue;

                  if (strlen($logicalOperator) > 0) {
                      if (substr($logicalOperator, 0, 1) != ' ') {
                          $logicalOperator = ' '. $logicalOperator;
                      }
                      if (substr($logicalOperator, -1) != ' ') {
                          $logicalOperator .= ' ';
                      }
                  }

                  break;

              case self::PARAM_GROUP:
                  $groupby = ' GROUP BY ' . $paramValue;
                  break;

              case self::PARAM_HAVING:
                  $having = ' HAVING ' . $paramValue;
                  break;

              case self::PARAM_ORDER:
                  $orderby = ' ORDER BY ' . $paramValue;
                  break;

              case self::PARAM_OFFSET:
                  $offset = ' OFFSET ' . $paramValue;
                  break;

              case self::PARAM_LIMIT:
                  $limit = ' LIMIT ' . $paramValue;
                  break;

              case self::PARAM_PAGING:
                  $offset = ' OFFSET ' . $paramValue->offset();
                  $limit  = ' LIMIT ' . $paramValue->limit();
                  break;

              case 'join':
                  break;

              default:

                  // operator porownania
                  $comparisonOperator = '=';
                  if (strpos($paramKey, ' ') !== false) {
                      list ($paramKey, $comparisonOperator) = explode(' ', $paramKey, 2);
                  }
                  if  (is_array($paramValue) && $comparisonOperator === '=') {
                      $comparisonOperator = 'IN';
                  }

                  switch ($comparisonOperator) {

                      case 'BETWEEN':
                      case 'NOT BETWEEN':
                          $where[] = "$paramKey $comparisonOperator"
                                   . " '{$this->_db->escape($paramValue[0])}'"
                                   . " AND '{$this->_db->escape($paramValue[1])}'";
                          break;

                      case 'IN':
                      case 'NOT IN':
                          foreach ($paramValue as $k => $v) {
                              $paramValue[$k] = $this->_db->escape($v);
                          }
                          $where[] = "$paramKey $comparisonOperator ('" . implode("','", $paramValue) . "')";
                          break;

                      default:
                          $where[] = "$paramKey $comparisonOperator '{$this->_db->escape($paramValue)}'";
                          break;

                  }

          }

      }

      if ($where) {
          $where = " WHERE " . implode($logicalOperator, $where);
      }

      // linkage
      if ($bLinkage && $this->_hardlink) {

          $hardlink = array();
          foreach ($this->_hardlink as $k => $v) {
              if (is_int($k)) {
                  $hardlink[] = $v;
              }
              else {
                  $hardlink[] = "`$k` = '{$this->_db->escape($v)}'";
              }
          }
          $hardlink = implode(' AND ', $hardlink);

          $where = $where ? " WHERE ($hardlink) AND (" . substr($where, 7) . ")" : " WHERE $hardlink";

      }

      // select
      if ($bSelect) {

          // this model
          foreach ($field as $k => $v) {
              $select[] =  "$v" . (is_int($k) ? '' : ' as ' . $k);
          }

          $select = 'SELECT ' . implode(', ', $select);
      }

      // from
      if ($bFrom) {

          // this model
          $from[] = "`{$this->_table}`" . ($this->_alias === null ? "" : " as $this->_alias");

          // join
          if (isset($aParam['join'])) {
              $from = array_merge($from, $aParam['join']);
          }

          $from = ' FROM ' . implode(' ', $from);

      }

      return $select . $from . $where . $groupby . $having . $orderby . $limit . $offset;

    }

    protected function _sqlJoin($sType, $sRelation, $asField, $sModel, $sJoinAlias, $sModelAlias)
    {
    $fields  = null;
    $sqlJoin = "";

    // relation
    if ($sModel === null) {
        $relation = $this->relation($sRelation);
    }
    else {
        $class  = self::$_metadata[$this->_class]['prefix'] . $sModel;
        $oModel = new $class();
        $relation = $oModel->relation($sRelation);
    }

    // field
    if ($asField !== false) {

        if ($asField === null) { // nie podano pol, to dodajemy wszystkie
            $joinClass = self::$_metadata[$this->_class]['prefix'] . $relation['rel_rel_table'];
            if (!isset(self::$_metadata[$joinClass])) {
                new $joinClass();
            }
            $asField = self::$_metadata[$joinClass]['field'];
        }
        else if (is_string($asField)) { // podano jako string - pola oddzielone spacja
            $asField = explode(' ', $asField);
        }

        $fields = array();

        foreach ($asField as $k => $v) {
            $fields[] = ($sJoinAlias === null ? '' : "`$sJoinAlias`" . '.')
                      . (is_int($k)
                            ? ($sJoinAlias === null ? "$v" : "`{$v}` as {$sJoinAlias}_{$v}")
                            : "$v as $k"
                        );
        }

    }

    // sql join fragment
    $sqlJoin = $sType . ' `' . $relation['rel_rel_table'] . '`'
             . ($sJoinAlias === null ? '' : " as `$sJoinAlias`")
             . ' ON ('
             . ($sModelAlias === null ? '' : "`$sModelAlias`.")
             . "{$relation['rel_field']}"
             . ' = '
             . ($sJoinAlias === null ? '' : "`$sJoinAlias`.")
             . "{$relation['rel_rel_field']}";

    if ($relation['rel_condition']) {
        foreach ($relation['rel_condition'] as $k => $v) {
            $sqlJoin .= is_int($k)
                      ? " AND $v"
                      : " AND $k = '" . $this->_db->escape($v). "'";
        }
    }

    $sqlJoin .= ')';

    // return sql join fragment and fields
    return array($sqlJoin, $fields);

    }


    /**
    * Get the value of Wynik zapytania (rokordy, rekord, krotki danych, wartosc pola lub falsz)
    *
    * @return mixed
    */
    public function get()
    {
    return $this->_;
    }

    /**
    * Set the value of Wynik zapytania (rokordy, rekord, krotki danych, wartosc pola lub falsz)
    *
    * @param mixed _
    *
    * @return self
    */
    public function set($_)
    {
    $this->_ = $_;

    return $this;
    }

    /**
    * Get the value of Table
    *
    * @return mixed
    */
    public function getTable()
    {
    return $this->_table;
    }

    /**
    * Set the value of Table
    *
    * @param mixed _table
    *
    * @return self
    */
    public function setTable($_table)
    {
    $this->_table = $_table;

    return $this;
    }

    /**
    * Get the value of Alias
    *
    * @return mixed
    */
    public function getAlias()
    {
    return $this->_alias;
    }

    /**
    * Set the value of Alias
    *
    * @param mixed _alias
    *
    * @return self
    */
    public function setAlias($_alias)
    {
    $this->_alias = $_alias;

    return $this;
    }

    /**
    * Get the value of Key
    *
    * @return mixed
    */
    public function getKey()
    {
    return $this->_key;
    }

    /**
    * Set the value of Key
    *
    * @param mixed _key
    *
    * @return self
    */
    public function setKey($_key)
    {
    $this->_key = $_key;

    return $this;
    }

    /**
    * Get the value of Field
    *
    * @return mixed
    */
    public function getField()
    {
    return $this->_field;
    }

    /**
    * Set the value of Field
    *
    * @param mixed _field
    *
    * @return self
    */
    public function setField($_field)
    {
    $this->_field = $_field;

    return $this;
    }

    /**
    * Get the value of Field Undo
    *
    * @return mixed
    */
    public function getFieldUndo()
    {
    return $this->_fieldUndo;
    }

    /**
    * Set the value of Field Undo
    *
    * @param mixed _fieldUndo
    *
    * @return self
    */
    public function setFieldUndo($_fieldUndo)
    {
    $this->_fieldUndo = $_fieldUndo;

    return $this;
    }

    /**
    * Get the value of Select
    *
    * @return mixed
    */
    public function getSelect()
    {
    return $this->_select;
    }

    /**
    * Set the value of Select
    *
    * @param mixed _select
    *
    * @return self
    */
    public function setSelect($_select)
    {
    $this->_select = $_select;

    return $this;
    }

    /**
    * Get the value of Param
    *
    * @return mixed
    */
    public function getParam()
    {
    return $this->_param;
    }

    /**
    * Set the value of Param
    *
    * @param mixed _param
    *
    * @return self
    */
    public function setParam($_param)
    {
    $this->_param = $_param;

    return $this;
    }

    /**
    * Get the value of Hardlink
    *
    * @return mixed
    */
    public function getHardlink()
    {
    return $this->_hardlink;
    }

    /**
    * Set the value of Hardlink
    *
    * @param mixed _hardlink
    *
    * @return self
    */
    public function setHardlink($_hardlink)
    {
    $this->_hardlink = $_hardlink;

    return $this;
    }

    /**
    * Get the value of Result
    *
    * @return mixed
    */
    public function getResult()
    {
    return $this->_result;
    }

    /**
    * Set the value of Result
    *
    * @param mixed _result
    *
    * @return self
    */
    public function setResult($_result)
    {
    $this->_result = $_result;

    return $this;
    }

    /**
    * Get the value of Dependent
    *
    * @return mixed
    */
    public function getDependent()
    {
    return $this->_dependent;
    }

    /**
    * Set the value of Dependent
    *
    * @param mixed _dependent
    *
    * @return self
    */
    public function setDependent($_dependent)
    {
    $this->_dependent = $_dependent;

    return $this;
    }

    /**
    * Get the value of Paging
    *
    * @return mixed
    */
    public function getPaging()
    {
    return $this->_paging;
    }

    /**
    * Set the value of Paging
    *
    * @param mixed _paging
    *
    * @return self
    */
    public function setPaging($_paging)
    {
    $this->_paging = $_paging;

    return $this;
    }

}
