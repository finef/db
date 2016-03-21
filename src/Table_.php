<?php

class Table implements TableInteraface, Iterator, Countable
{



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




}
