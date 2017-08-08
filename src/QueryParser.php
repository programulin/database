<?php
namespace Programulin\Database;

use Programulin\Database\Exception\ParserError;

class QueryParser
{
    private $input_params = [];
    private $output_params = [];
    private $output_query;
    
    public function parse($query, array $params)
    {
        $this->input_params = $params;
        $this->output_params = [];
        
        $this->output_query = preg_replace_callback('~(:[a-z]{1,})~s', [$this, 'placeholderHandler'], $query);
        
        if (!empty($this->input_params))
            throw new ParserError('Значений больше, чем плейсхолдеров.');
    }
    
    public function params()
    {
        return $this->output_params;
    }
    
    public function query()
    {
        return $this->output_query;
    }

    private function placeholderHandler(array $ph)
    {
        $param = array_shift($this->input_params);
        $ph = $ph[0];

        if (is_null($param))
            throw new ParserError("Для плейсхолдера '{$ph}' не найдено значение.");

        # :v :s :b :i, :d
        if (in_array($ph, [':v', ':s', ':b', ':i', ':d'], true))
            return $this->pType($ph, $param);

        # :name
        elseif ($ph === ':name')
            return $this->pName($param);

        # :names
        elseif ($ph === ':names')
            return $this->pNames($param);

        # :set
        elseif ($ph === ':set')
            return $this->pSet($param);

        # :where
        elseif ($ph === ':where')
            return $this->pWhere($param);

        # :in
        elseif ($ph === ':in')
            return $this->pIn($param);

        # :limit
        elseif ($ph === ':limit')
            return $this->pLimit($param);

        # wrong name
        else
            throw new ParserError("Некорректный плейсхолдер '{$ph}'.");
    }
    
    private function pType($ph, $param)
    {
        if(!$this->validateValue($param))
            throw new ParserError('Некорректное значение плейсхолдера :v.');

        if ($ph === ':s')
            $param = (string) $param;
        elseif ($ph === ':b')
            $param = (bool) $param;
        elseif ($ph === ':i')
            $param = (int) $param;
        elseif ($ph === ':d')
            $param = (float) $param;

        $this->output_params[] = $param;
        return '?';
    }

    private function pName($param)
    {
        if (!$this->validateName($param))
            throw new ParserError('Некорректное значение плейсхолдера :name.');

        return $this->protectName($param);
    }
    
    private function pNames($param)
    {
        if(!is_array($param) or empty($param))
            throw new ParserError('Значение плейсхолдера :names должно быть непустым массивом.');

        foreach ($param as $name)
        {
            if (!$this->validateName($name))
                throw new ParserError('Некорректное значение плейсхолдера :names.');

            $names[] = $this->protectName($name);
        }

        return implode(',', $names);
    }

    private function pSet($param)
    {
        if (!is_array($param) or empty($param))
            throw new ParserError('Значение плейсхолдера :set должно быть непустым массивом.');

        foreach ($param as $name => $value)
        {
            if (!$this->validateName($name))
                throw new ParserError('Некорректный ключ одного из параметров :set.');

            if (!$this->validateValue($value))
                throw new ParserError('Некорректное значение одного из параметров :set.');

            $this->output_params[] = $value;
            $sets[] = $this->protectName($name) . ' = ?';
        }

        return ' SET ' . implode(',', $sets);
    }

    private function pWhere($arr)
    {
        if(empty($arr))
            return;
        
        if(!is_array($arr))
            throw new ParserError('В :where нужно передавать массив.');

        $where = [];
        
        foreach($arr as $k => $v)
        {
            if(!is_array($v) or count($v) != 3)
                throw new ParserError("В :where нужно передавать массив, содержащий подмассивы с 3 значениями.");

            if(!$this->validateName($v[0]))
                throw new ParserError('Некорректное название столбца в :where.');

            $elem = $k + 1;
            $name = $this->protectName($v[0]);
            $action = mb_strtoupper($v[1]);
            $value = $v[2];
            
            if(in_array($action, ['>', '>=', '<', '<=', '=', '!=', 'LIKE'], true))
            {
                if(!$this->validateValue($value))
                    throw new ParserError("Некорректное значение у массива $elem плейсхолдера :where.");
                
                $this->output_params[] = $value;
                
                $where[] = $name . " $action ?"; 
            }
            
            elseif($action === 'BETWEEN')
            {
                if(!isset($value[0], $value[1]) or !$this->validateValue($value[0]) or !$this->validateValue($value[1]))
                   throw new ParserError('Некорректные значения BETWEEN плейсхолдера :where.');
                
                $where[] = $name . ' BETWEEN ? AND ?';
                $this->output_params[] = $value[0];
                $this->output_params[] = $value[1];
            }
            
            elseif($action === 'IN')
                $where[] = $name . ' ' . $this->pIn($value);
            
            else
                throw new ParserError("Некорректное условие в массиве $elem плейсхолдера :where.");
        }

        return 'WHERE ' . implode(' AND ', $where);
    }
    
    private function pIn($param)
    {
        if (empty($param))
            return 'IN (false)';

        if (!is_array($param) or empty($param))
            throw new ParserError('Значение плейсхолдера :in должно быть непустым массивом.');

        foreach ($param as $value)
        {
            if (!$this->validateValue($value))
                throw new ParserError('Некорректное значение одного из параметров :in.');

            $this->output_params[] = $value;
            $in[] = '?';
        }
        return 'IN (' . implode(',', $in) . ')';
    }
    
    private function pLimit($param)
    {
        if(!is_array($param))
            $param = [$param];

        if (count($param) > 2)
            throw new ParserError('Некорректное значение плейсхолдера :limit.');

        /*
         * Поиск любого значения, которое приводится к true.
         * Если таких значений нет - не отображаем LIMIT.
         */
        if (!in_array(true, $param, false))
            return '';

        foreach ($param as $value)
        {
            if (!$this->validateValue($value))
                throw new ParserError('Один из параметров :limit некорректный.');

            $limits[] = (int) $value;
        }

        return 'LIMIT ' . implode(',', $limits);   
    }

    private function validateValue($value)
    {
        return in_array(gettype($value), ['boolean', 'integer', 'double', 'string', 'NULL'], true);
    }

    private function validateName($value)
    {
        if (!in_array(gettype($value), ['string', 'double', 'integer'], true))
            return false;

        if (!preg_match('~^[a-z0-9\-_]+(\.{0,1}[a-z0-9\-_]+){0,2}$~i', $value))
            return false;

        return true;
    }

    private function protectName($name)
    {
        $parts = explode('.', $name);

        foreach ($parts as $k => $part)
            $parts[$k] = "`$part`";

        return implode('.', $parts);
    }
}