<?php
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1998-2004 Manuel Lemos, Tomas V.V.Cox,                 |
// | Stig. S. Bakken, Lukas Smith                                         |
// | All rights reserved.                                                 |
// +----------------------------------------------------------------------+
// | MDB2 is a merge of PEAR DB and Metabases that provides a unified DB  |
// | API as well as database abstraction for PHP applications.            |
// | This LICENSE is in the BSD license style.                            |
// |                                                                      |
// | Redistribution and use in source and binary forms, with or without   |
// | modification, are permitted provided that the following conditions   |
// | are met:                                                             |
// |                                                                      |
// | Redistributions of source code must retain the above copyright       |
// | notice, this list of conditions and the following disclaimer.        |
// |                                                                      |
// | Redistributions in binary form must reproduce the above copyright    |
// | notice, this list of conditions and the following disclaimer in the  |
// | documentation and/or other materials provided with the distribution. |
// |                                                                      |
// | Neither the name of Manuel Lemos, Tomas V.V.Cox, Stig. S. Bakken,    |
// | Lukas Smith nor the names of his contributors may be used to endorse |
// | or promote products derived from this software without specific prior|
// | written permission.                                                  |
// |                                                                      |
// | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS  |
// | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT    |
// | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS    |
// | FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE      |
// | REGENTS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,          |
// | INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, |
// | BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS|
// |  OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED  |
// | AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT          |
// | LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY|
// | WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE          |
// | POSSIBILITY OF SUCH DAMAGE.                                          |
// +----------------------------------------------------------------------+
// | Author: Lukas Smith <smith@backendmedia.com>                         |
// +----------------------------------------------------------------------+
//
// $Id$
//

require_once 'MDB2/Driver/Reverse/Common.php';

/**
 * MDB2 MySQL driver for the schema reverse engineering module
 *
 * @package MDB2
 * @category Database
 * @author  Lukas Smith <smith@backendmedia.com>
 */
class MDB2_Driver_Reverse_mysql extends MDB2_Driver_Reverse_Common
{
    // }}}
    // {{{ constructor

    /**
     * Constructor
     */
    function MDB2_Driver_Reverse_mysql($db_index)
    {
        $this->MDB2_Driver_Reverse_Common($db_index);
    }

    // }}}
    // {{{ getTableFieldDefinition()

    /**
     * get the stucture of a field into an array
     *
     * @param string    $table         name of table that should be used in method
     * @param string    $field_name     name of field that should be used in method
     * @return mixed data array on success, a MDB2 error on failure
     * @access public
     */
    function getTableFieldDefinition($table, $field_name)
    {
        $db =& $GLOBALS['_MDB2_databases'][$this->db_index];
        if ($field_name == $db->dummy_primary_key) {
            return $db->raiseError(MDB2_ERROR, null, null,
                'getTableFieldDefinition: '.$db->dummy_primary_key.' is an hidden column');
        }
        $result = $db->query("SHOW COLUMNS FROM $table");
        if (MDB2::isError($result)) {
            return $result;
        }
        $columns = $result->getColumnNames();
        if (MDB2::isError($columns)) {
            return $columns;
        }
        if (!isset($columns[$column = 'field'])
            || !isset($columns[$column = 'type']))
        {
            return $db->raiseError(MDB2_ERROR, null, null,
                'getTableFieldDefinition: show columns does not return the column '.$column);
        }
        $field_column = $columns['field'];
        $type_column = $columns['type'];
        while (is_array($row = $result->fetchRow())) {
            if ($field_name == $row[$field_column]) {
                $db_type = strtolower($row[$type_column]);
                $db_type = strtok($db_type, '(), ');
                if ($db_type == 'national') {
                    $db_type = strtok('(), ');
                }
                $length = strtok('(), ');
                $decimal = strtok('(), ');
                $type = array();
                switch($db_type) {
                    case 'tinyint':
                    case 'smallint':
                    case 'mediumint':
                    case 'int':
                    case 'integer':
                    case 'bigint':
                        $type[0] = 'integer';
                        if ($length == '1') {
                            $type[1] = 'boolean';
                            if (preg_match('/^[is|has]/', $field_name)) {
                                $type = array_reverse($type);
                            }
                        }
                        break;
                    case 'tinytext':
                    case 'mediumtext':
                    case 'longtext':
                    case 'text':
                    case 'char':
                    case 'varchar':
                        $type[0] = 'text';
                        if ($decimal == 'binary') {
                            $type[1] = 'blob';
                        } elseif ($length == '1') {
                            $type[1] = 'boolean';
                            if (preg_match('/[is|has]/', $field_name)) {
                                $type = array_reverse($type);
                            }
                        } elseif (strstr($db_type, 'text'))
                            $type[1] = 'clob';
                        break;
                    case 'enum':
                        preg_match_all('/\'.+\'/U',$row[$type_column], $matches);
                        $length = 0;
                        if (is_array($matches)) {
                            foreach($matches[0] as $value) {
                                $length = max($length, strlen($value)-2);
                            }
                        }
                        unset($decimal);
                    case 'set':
                        $type[0] = 'text';
                        $type[1] = 'integer';
                        break;
                    case 'date':
                        $type[0] = 'date';
                        break;
                    case 'datetime':
                    case 'timestamp':
                        $type[0] = 'timestamp';
                        break;
                    case 'time':
                        $type[0] = 'time';
                        break;
                    case 'float':
                    case 'double':
                    case 'real':
                        $type[0] = 'float';
                        break;
                    case 'decimal':
                    case 'numeric':
                        $type[0] = 'decimal';
                        break;
                    case 'tinyblob':
                    case 'mediumblob':
                    case 'longblob':
                    case 'blob':
                        $type[0] = 'blob';
                        $type[1] = 'text';
                        break;
                    case 'year':
                        $type[0] = 'integer';
                        $type[1] = 'date';
                        break;
                    default:
                        return $db->raiseError(MDB2_ERROR, null, null,
                            'getTableFieldDefinition: unknown database attribute type');
                }
                unset($notnull);
                if (isset($columns['null'])
                    && $row[$columns['null']] != 'YES')
                {
                    $notnull = true;
                }
                unset($default);
                if (isset($columns['default'])
                    && isset($row[$columns['default']]))
                {
                    $default = $row[$columns['default']];
                }
                $definition = array();
                for ($field_choices = array(), $datatype = 0; $datatype < count($type); $datatype++) {
                    $field_choices[$datatype] = array('type' => $type[$datatype]);
                    if (isset($notnull)) {
                        $field_choices[$datatype]['notnull'] = true;
                    }
                    if (isset($default)) {
                        $field_choices[$datatype]['default'] = $default;
                    }
                    if ($type[$datatype] != 'boolean'
                        && $type[$datatype] != 'time'
                        && $type[$datatype] != 'date'
                        && $type[$datatype] != 'timestamp')
                    {
                        if (strlen($length)) {
                            $field_choices[$datatype]['length'] = $length;
                        }
                    }
                }
                $definition[0] = $field_choices;
                if (isset($columns['extra'])
                    && isset($row[$columns['extra']])
                    && $row[$columns['extra']] == 'auto_increment')
                {
                    $implicit_sequence = array();
                    $implicit_sequence['on'] = array();
                    $implicit_sequence['on']['table'] = $table;
                    $implicit_sequence['on']['field'] = $field_name;
                    $definition[1]['name'] = $table.'_'.$field_name;
                    $definition[1]['definition'] = $implicit_sequence;
                }
                if (isset($columns['key'])
                    && isset($row[$columns['key']])
                    && $row[$columns['key']] == 'PRI')
                {
                    // check that its not just a unique field
                    $query = "SHOW INDEX FROM $table";
                    $indexes = $db->queryAll($query, null, MDB2_FETCHMODE_ASSOC);
                    if (MDB2::isError($indexes)) {
                        return $result;
                    }
                    $is_primary = false;
                    foreach($indexes as $index) {
                        if ($index['key_name'] == 'PRIMARY' && $index['column_name'] == $field_name) {
                            $is_primary = true;
                            break;
                        }
                    }
                    if ($is_primary) {
                        $implicit_index = array();
                        $implicit_index['unique'] = true;
                        $implicit_index['fields'][$field_name] = '';
                        $definition[2]['name'] = $field_name;
                        $definition[2]['definition'] = $implicit_index;
                    }
                }
                return $definition;
            }
        }
        $db->free($result);

        if (MDB2::isError($row)) {
            return $row;
        }
        return $db->raiseError(MDB2_ERROR, null, null,
            'getTableFieldDefinition: it was not specified an existing table column');
    }

    // }}}
    // {{{ getTableIndexDefinition()

    /**
     * get the stucture of an index into an array
     *
     * @param string    $table      name of table that should be used in method
     * @param string    $index_name name of index that should be used in method
     * @return mixed data array on success, a MDB2 error on failure
     * @access public
     */
    function getTableIndexDefinition($table, $index_name)
    {
        $db =& $GLOBALS['_MDB2_databases'][$this->db_index];
        if ($index_name == 'PRIMARY') {
            return $db->raiseError(MDB2_ERROR, null, null,
                'getTableIndexDefinition: PRIMARY is an hidden index');
        }
        if (MDB2::isError($result = $db->query("SHOW INDEX FROM $table"))) {
            return $result;
        }
        $definition = array();
        while (is_array($row = $result->fetchRow(MDB2_FETCHMODE_ASSOC))) {
            $key_name = strtolower($row['key_name']);
            if (!strcmp($index_name, $key_name)) {
                if (!$row['non_unique']) {
                    $definition[$index_name]['unique'] = true;
                }
                $column_name = $row['column_name'];
                $definition['fields'][$column_name] = array();
                if (isset($row['collation'])) {
                    $definition['fields'][$column_name]['sorting'] = ($row['collation'] == 'A' ? 'ascending' : 'descending');
                }
            }
        }
        $result->free();
        if (!isset($definition['fields'])) {
            return $db->raiseError(MDB2_ERROR, null, null,
                'getTableIndexDefinition: it was not specified an existing table index');
        }
        return $definition;
    }

    // }}}
    // {{{ tableInfo()

    /**
    * returns meta data about the result set
    *
    * @param resource    $result    result identifier
    * @param mixed $mode depends on implementation
    * @return array an nested array, or a MDB2 error
    * @access public
    */
    function tableInfo($result, $mode = null) {
        $db =& $GLOBALS['_MDB2_databases'][$this->db_index];
        $count = 0;
        $id     = 0;
        $res  = array();

        /*
         * depending on $mode, metadata returns the following values:
         *
         * - mode is false (default):
         * $result[]:
         *   [0]['table']  table name
         *   [0]['name']   field name
         *   [0]['type']   field type
         *   [0]['len']    field length
         *   [0]['flags']  field flags
         *
         * - mode is MDB2_TABLEINFO_ORDER
         * $result[]:
         *   ['num_fields'] number of metadata records
         *   [0]['table']  table name
         *   [0]['name']   field name
         *   [0]['type']   field type
         *   [0]['len']    field length
         *   [0]['flags']  field flags
         *   ['order'][field name]  index of field named "field name"
         *   The last one is used, if you have a field name, but no index.
         *   Test:  if (isset($result['meta']['myfield'])) { ...
         *
         * - mode is MDB2_TABLEINFO_ORDERTABLE
         *    the same as above. but additionally
         *   ['ordertable'][table name][field name] index of field
         *      named 'field name'
         *
         *      this is, because if you have fields from different
         *      tables with the same field name * they override each
         *      other with MDB2_TABLEINFO_ORDER
         *
         *      you can combine MDB2_TABLEINFO_ORDER and
         *      MDB2_TABLEINFO_ORDERTABLE with MDB2_TABLEINFO_ORDER |
         *      MDB2_TABLEINFO_ORDERTABLE * or with MDB2_TABLEINFO_FULL
         */

        // if $result is a string, then we want information about a
        // table without a resultset
        if (is_string($result)) {
            if (MDB::isError($connect = $db->connect())) {
                return($connect);
            }
            $id = @mysql_list_fields($db->database_name, $result, $db->connection);
            if (empty($id)) {
                return $db->raiseError();
            }
        } else { // else we want information about a resultset
            $id = $result->getResource();
            if (empty($id)) {
                return $db->raiseError();
            }
        }

        $count = @mysql_num_fields($id);

        // made this IF due to performance (one if is faster than $count if's)
        if (empty($mode)) {
            for ($i = 0; $i<$count; $i++) {
                $name = @mysql_field_name($id, $i);
                if ($name != 'dummy_primary_key') {
                    $res[$j]['table'] = @mysql_field_table($id, $i);
                    $res[$j]['name'] = $name;
                    $res[$j]['type'] = @mysql_field_type($id, $i);
                    $res[$j]['len']  = @mysql_field_len($id, $i);
                    $res[$j]['flags'] = @mysql_field_flags($id, $i);
                    $j++;
                }
            }
        } else { // full
            $res['num_fields'] = $count;

            for ($i = 0; $i<$count; $i++) {
                $name = @mysql_field_name($id, $i);
                if ($name != 'dummy_primary_key') {
                    $res[$j]['table'] = @mysql_field_table($id, $i);
                    $res[$j]['name'] = $name;
                    $res[$j]['type'] = @mysql_field_type($id, $i);
                    $res[$j]['len']  = @mysql_field_len($id, $i);
                    $res[$j]['flags'] = @mysql_field_flags($id, $i);
                    if ($mode & MDB_TABLEINFO_ORDER) {
                        // note sure if this should be $i or $j
                        $res['order'][$res[$j]['name']] = $i;
                    }
                    if ($mode & MDB_TABLEINFO_ORDERTABLE) {
                        $res['ordertable'][$res[$j]['table']][$res[$j]['name']] = $j;
                    }
                    $j++;
                }
            }
        }

        // free the result only if we were called on a table
        if (is_string($result)) {
            @mysql_free_result($id);
        }
        return $res;
    }
}
?>