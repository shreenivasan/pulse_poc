<?php

    function db() {
        //Credentials saved in modules/settings.php
	   $connection = new mysqli(SERVER, USER, PASSWORD, DATABASE);
	   $connection->query("SET NAMES 'utf8'");
	   $connection->query("SET CHARACTER SET utf8");
	   $connection->query("SET SESSION collation_connection = 'utf8_unicode_ci'");

	   return $connection;
    }

    function end_db($db){
    	mysqli_close($db);
    } 
    
    class Dbconfig {

    private $_connection;

    public function __construct() {
        $this->_connect();
    }

    protected function _connect() {
        if ($this->_connection) {
            return;
        }
        $this->_connection = mysqli_init();
        $_isConnected = @mysqli_real_connect($this->_connection, SERVER, USER, PASSWORD, DATABASE, 3306, null, MYSQLI_CLIENT_FOUND_ROWS
        );

        if ($_isConnected === false || mysqli_connect_errno()) {
            $this->closeConnection();
        }
    }

    public function getConnection() {
        return $this->_connection;
    }

    public function beginTransaction() {
        $this->_connect();
        $this->_connection->autocommit(false);
        return $this;
    }

    public function commitTransaction() {
        $this->_connect();
        $this->_connection->commit();
        $this->_connection->autocommit(true);
        return $this;
    }

    public function rollbackTransaction() {
        $this->_connect();
        $this->_connection->rollback();
        $this->_connection->autocommit(true);
        return $this;
    }

    public function lastInsertId() {
        return $this->_connection->insert_id;
    }

    // used just to insert - no value will be returned
    public function execute($sql, $bind = array()) {

        if (!is_array($bind)) {
            $bind = array($bind);
        }

        $this->_connect();
        $stmt = $this->_connection->prepare($sql);
        if ($stmt) {
            $types = "";
            // set up parameter type s = string, i = integer, d = decimal
            // please pass the parameters carefully for proper execution
            foreach ($bind as $param) {
                if (is_int($param))
                    $types .= "i";
                else if (is_double($param))
                    $types .= "d";
                else if (is_string($param))
                    $types .= "s";
            }

            // set up binding types and parameters with query
            $bind_names[] = $types;
            for ($i = 0; $i < count($bind); $i++) {
                $bind_name = 'bind' . $i;
                $$bind_name = $bind[$i];
                $bind_names[] = &$$bind_name;
            }

            /* try
              { */
            // bind names
            if (!empty($bind)) {
                $return = call_user_func_array(array($stmt, 'bind_param'), $bind_names);
            }

            //print_R($bind);
            $parse_query = $this->_preparedQuery($sql, $bind);
            //echo "<br />".$this->_preparedQuery($sql,$bind)."<br />";
            $start = round(microtime(true), 4);
            $retval = $stmt->execute();
            return $retval;
            
        }
    }

    public function isConnected() {
        return ((bool) ($this->_connection instanceof mysqli));
    }

    public function closeConnection() {
        if ($this->isConnected()) {
            $this->_connection->close();
        }
        $this->_connection = null;
    }

    public function __destruct() {
        $this->closeConnection();
    }

    public function query($sql, $bind = array()) {
        if (!is_array($bind)) {
            $bind = array($bind);
        }
        $this->_connect();
        $stmt = $this->_connection->prepare($sql);
        if ($stmt) {
            $types = "";
            foreach ($bind as $param) {
                if (is_int($param))
                    $types .= "i";
                else if (is_double($param))
                    $types .= "d";
                else if (is_string($param))
                    $types .= "s";
            }

            $bind_names[] = $types;
            for ($i = 0; $i < count($bind); $i++) {
                $bind_name = 'bind' . $i;
                $$bind_name = $bind[$i];
                $bind_names[] = &$$bind_name;
            }
            
            if (!empty($bind)) {
                $return = call_user_func_array(array($stmt, 'bind_param'), $bind_names);
            }
            $parse_query = $this->_preparedQuery($sql, $bind);
            $start = round(microtime(true), 4);
            $stmt->execute();
            $end = round(microtime(true), 4);
            $total = round($end - $start, 4);
            
            $result = $stmt->get_result();
            $returns = array();
            while ($row = $result->fetch_assoc()) {
                $returns[] = $row;
            }
            return $returns;
        }
    }

    private function _preparedQuery($sql, $params) {
        for ($i = 0; $i < count($params); $i++) {
            $sql = preg_replace('/\?/', $params[$i], $sql, 1);
        }
        return $sql;
    }

    public function escape($value) {
        $this->_connect();
        return $this->_connection->real_escape_string($value);
    }
    
    public function ping() {
        if (isset($this->_connection) && $this->_connection->ping()) {
            return true;
        } else {
            return false;
        }
    }

    public function query_additional_params($sql, $bind = array(), $extra = array()) {
        if (!is_array($bind)) {
            $bind = array($bind);
        }

        $this->_connect();
        $stmt = $this->_connection->prepare($sql);
        if ($stmt) {
            $types = "";
            foreach ($bind as $param) {
                if (is_int($param))
                    $types .= "i";
                else if (is_double($param))
                    $types .= "d";
                else if (is_string($param))
                    $types .= "s";
            }

            $bind_names[] = $types;
            for ($i = 0; $i < count($bind); $i++) {
                $bind_name = 'bind' . $i;
                $$bind_name = $bind[$i];
                $bind_names[] = &$$bind_name;
            }
            if (!empty($bind)) {
                $return = call_user_func_array(array($stmt, 'bind_param'), $bind_names);
            }
            
            $parse_query = $this->_preparedQuery($sql, $bind);
            $start = round(microtime(true), 4);
            $stmt->execute();
            $end = round(microtime(true), 4);
            $total = round($end - $start, 4);

            $result = $stmt->get_result();
            $returns = array();
            while ($row = $result->fetch_assoc()) {
                if (isset($extra['grouping_with_field']) && !empty($row[$extra['grouping_with_field']])) {
                    $returns[$row[$extra['grouping_with_field']]] [] = $row;
                } else {
                    $returns[] = $row;
                }
            }
            //exit;
            return $returns;
        }
    }

}