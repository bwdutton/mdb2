<?php
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1998-2004 Manuel Lemos, Paul Cooper                    |
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
// | Author: Paul Cooper <pgc@ucecom.com>                                 |
// +----------------------------------------------------------------------+
//
// $Id:

class MDB2_Bugs_TestCase extends PHPUnit_TestCase {
    //contains the dsn of the database we are testing
    var $dsn;
    //contains the options that should be used during testing
    var $options;
    //contains the name of the database we are testing
    var $database;
    //contains the MDB2 object of the db once we have connected
    var $db;
    // contains field names from the test table
    var $fields;
    // contains the types of the fields from the test table
    var $types;

    function MDB2_Bugs_TestCase($name) {
        $this->PHPUnit_TestCase($name);
    }

    function setUp() {
        $this->dsn = $GLOBALS['dsn'];
        $this->options = $GLOBALS['options'];
        $this->database = $GLOBALS['database'];
        $this->db =& MDB2::connect($this->dsn, $this->options);
        if (MDB2::isError($this->db)) {
            $this->assertTrue(false, 'Could not connect to database in setUp');
            exit;
        }
        $this->db->setDatabase($this->database);
        $this->fields = array(
                        'user_name',
                        'user_password',
                        'subscribed',
                        'user_id',
                        'quota',
                        'weight',
                        'access_date',
                        'access_time',
                        'approved'
                    );
        $this->types = array(
                        'text',
                        'text',
                        'boolean',
                        'integer',
                        'decimal',
                        'float',
                        'date',
                        'time',
                        'timestamp'
                    );
        $this->clearTables();
    }

    function tearDown() {
        $this->clearTables();
        unset($this->dsn);
        if (!MDB2::isError($this->db)) {
            $this->db->disconnect();
        }
        unset($this->db);
    }

    function clearTables() {
        if (MDB2::isError($this->db->query('DELETE FROM users'))) {
            $this->assertTrue(false, 'Error deleting from table users');
        }
        if (MDB2::isError($this->db->query('DELETE FROM files'))) {
            $this->assertTrue(false, 'Error deleting from table users');
        }
    }

    function insertTestValues($prepared_query, &$data) {
        for ($i = 0; $i < count($this->fields); $i++) {
            $this->db->setParam($prepared_query, ($i + 1), $data[$this->fields[$i]]);
        }
    }

    /**
     *
     */
    function testFetchModeBug() {
        $data = array();

        $prepared_query = $this->db->prepare('INSERT INTO users (user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', $this->types);

        $data['user_name'] = 'user_=';
        $data['user_password'] = 'somepassword';
        $data['subscribed'] = true;
        $data['user_id'] = 0;
        $data['quota'] = sprintf("%.2f", strval(2/100));
        $data['weight'] = sqrt(0);
        $data['access_date'] = MDB2_Date::mdbToday();
        $data['access_time'] = MDB2_Date::mdbTime();
        $data['approved'] = MDB2_Date::mdbNow();

        $this->insertTestValues($prepared_query, $data);

        $result = $this->db->execute($prepared_query);

        if (MDB2::isError($result)) {
            $this->assertTrue(false, 'Error executing prepared query'.$result->getMessage());
        }

        $this->db->freePrepared($prepared_query);

        $result =& $this->db->query('SELECT * FROM users ORDER BY user_name');

        if (MDB2::isError($result)) {
            $this->assertTrue(false, 'Error selecting from users'.$result->getMessage());
        }

        $this->db->setFetchMode(MDB2_FETCHMODE_ASSOC);

        $firstRow = $result->fetchRow();
        $this->assertEquals($firstRow['user_name'], $data['user_name'], "The data returned ($firstRow[user_name]) does not match that expected (".$data['user_name'].")");





        $result = $this->db->query('SELECT user_name, user_id, quota FROM users ORDER BY user_name');
        if (MDB2::isError($result)) {
            $this->assertTrue(false, 'Error selecting from users'.$result->getMessage());
        }
        $this->db->setFetchMode(MDB2_FETCHMODE_ORDERED);

        $value = $result->fetch();
        $this->assertEquals($data['user_name'], $value, "The data returned ($value) does not match that expected (".$data['user_name'].")");
        $result->free();
    }

    /**
     * http://bugs.php.net/bug.php?id=22328
     */
    function testBug22328() {
        $result =& $this->db->query('SELECT * FROM users');
        $this->db->pushErrorHandling(PEAR_ERROR_RETURN);
        $result2 = $this->db->query('SELECT * FROM foo');

        $data = $result->fetchRow();
        $this->db->popErrorHandling();
        $this->assertFalse(MDB2::isError($data), "Error messages for a query affect result reading of other queries");
    }

    /**
     * http://pear.php.net/bugs/bug.php?id=670
     */
    function testBug670() {
        $data['user_name'] = null;
        $data['user_password'] = 'somepassword';
        $data['subscribed'] = true;
        $data['user_id'] = 1;
        $data['quota'] = sprintf("%.2f",strval(3/100));
        $data['weight'] = sqrt(1);
        $data['access_date'] = MDB2_Date::mdbToday();
        $data['access_time'] = MDB2_Date::mdbTime();
        $data['approved'] = MDB2_Date::mdbNow();

        $prepared_query = $this->db->prepare('INSERT INTO users (user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', $this->types);
        $this->insertTestValues($prepared_query, $data);
        $result = $this->db->execute($prepared_query);

        $result = $this->db->query('SELECT user_name FROM users');
        $col = $result->fetchCol('user_name');
        if (MDB2::isError($col)) {
            $this->assertTrue(false, "Error when fetching column first first row as NULL: ".$col->getMessage());
        }

        $data['user_name'] = "user_1";
        $data['user_id'] = 2;

        $this->insertTestValues($prepared_query, $data);
        $result = $this->db->execute($prepared_query);

        $result = $this->db->query('SELECT user_name FROM users');
        $col = $result->fetchCol('user_name');
        if (MDB2::isError($col)) {
            $this->assertTrue(false, "Error when fetching column: ".$col->getMessage());
        }

        $data['user_name'] = null;

        $this->db->freePrepared($prepared_query);
    }

    /**
     * http://pear.php.net/bugs/bug.php?id=681
     */
    function testBug681() {
        $result = $this->db->query('SELECT * FROM users WHERE 1=0');

        $numrows = $result->numRows();
        $this->assertEquals(0, $numrows, "Numrows is not returning 0 for empty result sets");

        $data['user_name'] = "user_1";
        $data['user_password'] = 'somepassword';
        $data['subscribed'] = true;
        $data['user_id'] = 1;
        $data['quota'] = sprintf("%.2f",strval(3/100));
        $data['weight'] = sqrt(1);
        $data['access_date'] = MDB2_Date::mdbToday();
        $data['access_time'] = MDB2_Date::mdbTime();
        $data['approved'] = MDB2_Date::mdbNow();

        $prepared_query = $this->db->prepare('INSERT INTO users (user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', $this->types);
        $this->insertTestValues($prepared_query, $data);
        $result = $this->db->execute($prepared_query);

        $result = $this->db->query('SELECT * FROM users');
        $numrows = $result->numRows();
        $this->assertEquals(1, $numrows, "Numrows is not returning proper value");

        $this->db->freePrepared($prepared_query);
    }

    /**
     * http://pear.php.net/bugs/bug.php?id=718
     */
    function testBug718() {
        $data['user_name'] = "user_1";
        $data['user_password'] = 'somepassword';
        $data['subscribed'] = true;
        $data['user_id'] = 1;
        $data['quota'] = sprintf("%.2f",strval(3/100));
        $data['weight'] = sqrt(1);
        $data['access_date'] = MDB2_Date::mdbToday();
        $data['access_time'] = MDB2_Date::mdbTime();
        $data['approved'] = MDB2_Date::mdbNow();

        $prepared_query = $this->db->prepare('INSERT INTO users (user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', $this->types);
        $this->insertTestValues($prepared_query, $data);
        $result = $this->db->execute($prepared_query);

        $row = $this->db->queryRow('SELECT a.user_id, b.user_id FROM users a, users b where a.user_id = b.user_id', array('integer', 'integer'), MDB2_FETCHMODE_ORDERED);
        $this->assertEquals(2, count($row), "Columns with the same name get overwritten in ordered mode");

        $this->db->freePrepared($prepared_query);
    }

    /**
     * http://pear.php.net/bugs/bug.php?id=946
     */
    function testBug946() {
        $data = array();
        $total_rows = 5;

        $prepared_query = $this->db->prepare('INSERT INTO users (user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', $this->types);

        for ($row = 0; $row < $total_rows; $row++) {
            $data[$row]['user_name'] = "user_$row";
            $data[$row]['user_password'] = 'somepassword';
            $data[$row]['subscribed'] = (boolean)($row % 2);
            $data[$row]['user_id'] = $row;
            $data[$row]['quota'] = sprintf("%.2f",strval(1+($row+1)/100));
            $data[$row]['weight'] = sqrt($row);
            $data[$row]['access_date'] = MDB2_Date::mdbToday();
            $data[$row]['access_time'] = MDB2_Date::mdbTime();
            $data[$row]['approved'] = MDB2_Date::mdbNow();

            $this->insertTestValues($prepared_query, $data[$row]);

            $result = $this->db->execute($prepared_query);

            if (MDB2::isError($result)) {
                $this->assertTrue(false, 'Error executing prepared query'.$result->getMessage());
            }
        }
        $this->db->freePrepared($prepared_query);

        $this->db->setLimit(3, 1);
        $result = $this->db->query('SELECT * FROM users');
        $numrows = $result->numRows();
        while ($row = $result->fetchRow()) {
            if (MDB2::isError($row)) {
                $this->assertTrue(false, 'Error fetching a row'.$row->getMessage());
            }
        }
        $result->free();

        $result = $this->db->query('SELECT * FROM users');
        $numrows = $result->numRows();
        while ($row = $result->fetchRow()) {
            if (MDB2::isError($row)) {
                $this->assertTrue(false, 'Error fetching a row'.$row->getMessage());
            }
        }
        $result->free();
    }
}

?>