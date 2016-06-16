<?php

namespace \Fine\Db;

interface LinkerInterface 
{
    
    public function link(Model $model, $to, $from, $joinType, $fields);
    
}
