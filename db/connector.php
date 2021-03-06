<?php

    namespace BaseCMS\db;
    
    use BaseCMS\db\RowObject as RowObject;
    use BaseCMS\db\DatabaseException as DatabaseException;

    class Connector {
    
        private $conn;
    
        function __construct($db_config) {
        
            $engine = $db_config['engine'] ? $db_config['engine'] : 'mysql';
            $host = $db_config['host'] ? $db_config['host'] : 'localhost';
            $uname = $db_config['username'] ? $db_config['username'] : '';
            $pword = $db_config['password'] ? $db_config['password'] : '';
            $db = $db_config['database'] ? $db_config['database'] : '';
        
            $dsn = $engine . ':dbname=' . $db . ';host=' . $host;            
            $this->conn = new \PDO($dsn, $uname, $pword);
            
        }
    
        function execute($query, $params = null, $get_id = false) {
            
            if (defined('MYSQL_DEBUG')) echo $query, var_dump($params);
        
            $statement = $this->conn->prepare($query);
            $statement->execute($params);
            
            $errors = $statement->errorInfo();
            if ($errors[0] != '00000') {
                throw new DatabaseException("Query execution returned an error: $errors[0] $errors[1] $errors[2]");
            }
            
            $result = array();
            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $result[] = $row;
            };
        
            if (!$get_id)
                return $result;
            else
                return $this->conn->lastInsertId();
        
        }
        
        function get($table, $where_params = null, $order_params = null, $limit = null) {
            if (!$this->valid_column_name($table))
                throw new DatabaseException('Invalid table name in call to Connector::get');
            
            if ($limit && is_numeric($limit))
                $limit = ' LIMIT ' . intval($limit);
            else if (!is_numeric($limit))
                //$limit = '';
                $limit;
            else
                $limit = 0;
                
            $order = '';
            foreach ($order_params as $v) {
                
                if (!$this->valid_column_name($v)) 
                    throw new DatabaseException('Invalid order parameter in Connector::get');
                if ($order) 
                    $order = $order . ', ';
                    
                $order = $order . $v . ' ';
                
            }
            if ($order) $order = ' ORDER BY ' . $order . ' ';
            
            list($where, $params) = $this->columns_from_array($where_params, 'WHERE');
            
            if (strrpos('`', $table) !== false) {
                throw new DatabaseException('Bad table name in call to Connector::get');
            }
            
            $query = "SELECT * FROM `" . $table . "` " . $where . $order . $limit;
            $result = $this->execute($query, $params);
            $output = array();
            foreach ($result as $row) {
                $output[] = new RowObject($row, $table);
            }
            
            return $output;
        }
        
        function get_one($table, $where_params = null, $order_params = null) {
            $result = $this->get($table, $where_params, $order_params, 1);
            return array_shift($result);
        }
        
        function save($row_obj, $key = array(), $table = null) {
        
            if (!$table) {
                $table = $row_obj->_get_table();
                if (!$table)
                    throw new DatabaseException('RowObject does not have a table property and no table as been passed to Connector::save');
            }
            if (!$this->valid_column_name($table))
                throw new DatabaseException('Invalid table name in call to Connector::save');
        
            // We assume that if an id field is provided, we should update the 
            // table based on the id. Otherwise we will 
            if (empty($key) && $row_obj->id)
                $key = array('id' => $row_obj->id);
            else if ($row_obj->id)
                throw new DatabaseException('Cannot update table with Connector::save - RowObject has an existing id, and keys have been provided (this looks like a mistake)');
        
            $where = '';
            $wparams = array();
            if (count($key)) {
                list($where, $wparams) = $this->columns_from_array($key, 'WHERE');
            }
            
            $row = $row_obj->_get_row();
            $set = '';
            $params = array();
            if (count($row)) {
                list($set, $params) = $this->columns_from_array($row, 'SET');
            } else
                throw new DatabaseException('RowObject with no values in row property in Connector::save?');
            
            if (!$where) {
                $get_id = true;
                $verb = 'INSERT INTO';
                $set .= ', creation_date = NOW() ';
            } else {
                $get_id = false;
                $verb = 'UPDATE';
            }
                
            $query = $verb . " `" . $table . "` " . $set . " " . $where;
            $params = array_merge($params, $wparams);            
            $result = $this->execute($query, $params, $get_id);
            return $result;
        }
        
        function delete($row_obj, $table = null) {
            if (!$table) {
                $table = $row_obj->_get_table();
                if (!$table)
                    throw new DatabaseException('RowObject does not have a table property and no table as been passed to Connector::delete');
            }
            if (!$this->valid_column_name($table))
                throw new DatabaseException('Invalid table name in call to Connector::delete');
            $query = "DELETE FROM `" . $table . "` WHERE id = :id";
            return $this->execute($query, array('id' => $row_obj->id));
        }
        
        private function valid_column_name($k) {
            $i = substr($k, 0, 1);
            $k = str_replace('_', '', $k);
            return (ctype_alnum($k) && ctype_alpha($i));
        }
        
        private function columns_from_array($arr, $verb = null) {
        
            $o = ''; 
            $p = array();
            
            foreach ($arr as $k => $v) {
                if (!$this->valid_column_name($k)) 
                    throw new DatabaseException('Invalid column name key in Connector::columns_from_array: ' . $k);
                if ($o && $verb == 'WHERE') 
                    $o = $o . ' AND ';
                else if ($o)
                    $o = $o . ', ';
                    
                if (is_array($v)) {
                    $or = '';
                    $count = 0;
                    foreach($v as $alternative) {
                        if ($or && $conjunction = 'OR')
                            $or .= ' OR ';
                        $or = $or . $k . ' = :' . $k . $count;
                        $p[':'.$k.$count] = $alternative;
                    }
                    $o .= $or;
                } else {
                    $o = $o . $k . ' = :' . $k;
                    $p[':'.$k] = $v;
                }
            }
            
            if ($verb && $o)
                $o = $verb . ' ' . $o;
                
            return array($o, $p);
        }
        
        function new_row($row_array, $table = null) {
            if ($row_array['id'])
                throw new DatabaseException('Connector:new_row cowardly refusing to create a new row object with an id (must be loaded with Connector::get)');
            return new RowObject($row_array, $table);
        }
        
    }
