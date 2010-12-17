<?php
/**
 * CreditCardFreezer_PDO
 *
 * Special class which extends the CreditCardFreezer with new
 * methods designed for PHP's PDO data connections.
 *
 * Very similar to an object relational mapper (ORM) and the methods
 * mimic those of the Doctrine project, though it's much more
 * lightweight as the features really only include the basic
 * CRUD functionality to and from the database.
 *
 * Copyright (c) 2010, Andrew Kandels <me@andrewkandels.com>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category    Encryption
 * @package     CreditCardFreezer
 * @author      Andrew Kandels <me@andrewkandels.com>
 * @copyright   2010 Andrew Kandels <me@andrewkandels.com>
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link        http://andrewkandels.com/CreditCardFreezer
 * @access      public
 */


require_once dirname(__FILE__) . '/../CreditCardFreezer.php';

class CreditCardFreezer_PDO extends CreditCardFreezer
{
    private $_dbh               = null;
    private $_pdoDriver         = null;
    private $_tableName         = 'credit_card';
    private $_primaryKey        = 'credit_card_id';
    private $_columns           = null;
    private $_primaryId         = null;

    /**
     * Creates a CreditCardFreezer object and links it to a PDO
     * data source.
     *
     * @param   array       Optional attributes passed as an array.
     * @param   array       Associative array of column mappings,
     *                      (attribute constant => string column name)
     * @return  CreditCardFreezer
     */
    public function __construct(PDO $dbh, array $columns = array())
    {
        parent::__construct();

        $this->_dbh = $dbh;
        $this->_pdoDriver = $dbh->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!empty($columns)) {
            $this->_columns = $columns;
        } else {
            $columns = $this->_getTextLabels();
            foreach ($columns as $attr=> $label) {
                if (in_array($attr, array(
                    self::EXPIRE_YEAR,
                    self::EXPIRE_MONTH,
                    self::NUMBER,
                    self::CCV
                ))) {
                    unset($columns[$attr]);
                }
            }
            $this->_columns = $columns;
        }
    }

    /**
     * Sets the table name to use on the data source.
     * Defaults to "credit_card".
     * @param   string      Table name
     * @return  CreditCardFreezer_PDO
     */
    public function setTableName($name)
    {
        $this->_tableName = $name;
        return $this;
    }

    /**
     * Sets the primary key column name to use on the data source.
     * Defaults to "credit_card_id".
     * @param   string      Primary key
     * @return  CreditCardFreezer_PDO
     */
    public function setPrimaryKey($name)
    {
        $this->_primaryKey = $name;
        return $this;
    }

    /**
     * Sets the column mappings. This should be an associative array
     * of class constants (e.g.: CreditCardFreezer::FIRST_NAME) pointing
     * to string column labels for use in a database.
     *
     * @param   array       Column mappings
     * @return  CreditCardFreezer_PDO
     */
    public function setColumns(array $columns)
    {
        $this->_columns = $columns;
        return $this;
    }

    /**
     * Creates a table over a PDO connection to store one or more
     * attributes and assign them to an autoincrementing ID. This method
     * currently supports the following databases:
     *     - sqlite
     *     - mysql
     * (it may work with others as well)
     *
     * @param   array       Attributes to create columns for (defaults to all
     *                      with values)
     * @return  CreditCardFreezer
     */
    public function createTable(array $attrs = array())
    {
        // If not set, use columns with values
        if (empty($attrs)) {
            foreach ($this->_attr as $attr => $value) {
                if (in_array($attr, array(
                    self::EXPIRE_YEAR,
                    self::EXPIRE_MONTH,
                    self::NUMBER
                ))) {
                    if (!in_array(self::SECURE_STORE, $attrs)) {
                        $attrs[] = self::SECURE_STORE;
                    }
                } else {
                    $attrs[] = $attr;
                }
            }

            // Otherwise, just default to the store value
            if (empty($attrs)) {
                $attrs = array(self::SECURE_STORE);
            }
        }

        // Use text labels for column names (instead of numbers from constants)
        $textLabels = $this->_getTextLabels();
        $mappings   = $this->_columns;
        if (empty($mappings)) {
            $mappings = $textLabels;
        } else {
            foreach ($textLabels as $attr => $label) {
                if (!isset($mappings[$attr])) {
                    $mappings[$attr] = $label;
                }
            }
        }

        $columns = array();

        foreach ($attrs as $attr) {
            switch ($attr) {
                case self::SECURE_STORE:
                    $columns[] = sprintf('%s VARCHAR(%d) NOT NULL',
                        $mappings[self::SECURE_STORE],
                        self::ENCRYPT_CHUNK_STORAGE
                    );
                    break;

                case self::NUMBER:
                    $columns[] = sprintf('%s VARCHAR(%d) NOT NULL',
                        $mappings[self::NUMBER],
                        self::ENCRYPT_CHUNK_STORAGE
                    );
                    break;

                case self::EXPIRE_MONTH:
                    $columns[] = sprintf('%s VARCHAR(%d) NOT NULL',
                        $mappings[self::EXPIRE_MONTH],
                        self::ENCRYPT_CHUNK_STORAGE
                    );
                    break;

                case self::EXPIRE_YEAR:
                    $columns[] = sprintf('%s VARCHAR(%d) NOT NULL',
                        $mappings[self::EXPIRE_YEAR],
                        self::ENCRYPT_CHUNK_STORAGE
                    );
                    break;

                case self::TYPE:
                    $columns[] = sprintf('%s VARCHAR(15) NOT NULL',
                        $mappings[self::TYPE]
                    );
                    break;

                case self::CCV:
                    // This should not be stored!
                    break;

                case self::FIRST_NAME:
                    $columns[] = sprintf('%s VARCHAR(30) NOT NULL',
                        $mappings[self::FIRST_NAME]
                    );
                    break;

                case self::LAST_NAME:
                    $columns[] = sprintf('%s VARCHAR(30) NOT NULL',
                        $mappings[self::LAST_NAME]
                    );
                    break;

                case self::ADDRESS:
                    $columns[] = sprintf('%s VARCHAR(120) NOT NULL',
                        $mappings[self::ADDRESS]
                    );
                    break;

                case self::CITY:
                    $columns[] = sprintf('%s VARCHAR(30) NOT NULL',
                        $mappings[self::CITY]
                    );
                    break;

                case self::STATE:
                    $columns[] = sprintf('%s VARCHAR(20) NOT NULL',
                        $mappings[self::STATE]
                    );
                    break;

                case self::POSTAL_CODE:
                    $columns[] = sprintf('%s VARCHAR(15) NOT NULL',
                        $mappings[self::POSTAL_CODE]
                    );
                    break;

                case self::COUNTRY:
                    $columns[] = sprintf('%s VARCHAR(30) NOT NULL',
                        $mappings[self::COUNTRY]
                    );
                    break;

                case self::PHONE:
                    $columns[] = sprintf('%s VARCHAR(20) NOT NULL',
                        $mappings[self::PHONE]
                    );
                    break;
            }
        }

        switch ($this->_pdoDriver) {
            case 'sqlite':
                $sql = "CREATE TABLE %s ("
                     . "%s INTEGER PRIMARY KEY AUTOINCREMENT,%s "
                     . "created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)";
                break;

            default:
                $sql = "CREATE TABLE %s ("
                     . "%s INT(11) UNSIGNED AUTO_INCREMENT,%s "
                     . "created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, "
                     . "PRIMARY KEY (%s))";
                break;
        }

        $sql = sprintf($sql,
            $this->_tableName,
            $this->_primaryKey,
            "\n" . implode(",\n", $columns) . ',',
            $this->_primaryKey
        );

        $this->_dbh->query($sql);
        return $this;
    }

    /**
     * Inserts the stored attributes of this object into a PDO connection
     * using a prepared statement and returns the new autoincrement column
     * id (if any).
     *
     * @return  mixed   Autoincrement ID # (if any)
     */
    public function insert()
    {
        $into = $values = array();
        foreach ($this->_columns as $attr => $column) {
            $into[] = $column;
            $values[] = ":$column";
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->_tableName,
            implode(', ', $into),
            implode(', ', $values)
        );

        $stmt = $this->_dbh->prepare($sql);

        foreach ($this->_columns as $attr => $column) {
            $stmt->bindParam(":$column", $this->getForStorage($attr));
        }

        return ($this->_primaryId = $stmt->execute());
    }

    /**
     * Deletes the current object from the database. Either the
     * find() or insert() methods must first be called in order
     * to assign the current object to a row.
     *
     * @return  CreditCardFreezer_PDO
     */
    public function delete()
    {
        if (!$this->_primaryId) {
            throw new CreditCardFreezer_Exception(
                'No row mapping has been set. Call insert() or find() first.'
            );
        }

        $stmt = $this->_dbh->prepare(sprintf(
            'DELETE FROM %s WHERE %s = :id',
            $this->_tableName,
            $this->_primaryKey
        ));

        $stmt->bindParam(':id', $this->_primaryId);
        $stmt->execute();

        return $this;
    }

    /**
     * Updates any changes made to the current object to the data
     * source. Either the find() or insert() methods must first
     * be called in order to assign the current object a row.
     *
     * @param   integer Autoincrement ID #
     * @return  CreditCardFreezer_PDO
     */
    public function save()
    {
        if (!$this->_primaryId) {
            throw new CreditCardFreezer_Exception(
                'No row mapping has been set. Call insert() or find() first.'
            );
        }

        $lines = array();
        foreach ($this->_columns as $attr => $column) {
            $lines[] = "$column = :$column";
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :id',
            $this->_tableName,
            implode(', ', $lines),
            $this->_primaryKey
        );

        $stmt = $this->_dbh->prepare($sql);
        $stmt->bindParam(':id', $this->_primaryId);

        foreach ($this->_columns as $attr => $column) {
            $stmt->bindParam(":$column", $this->getForStorage($attr));
        }

        $stmt->execute();

        return $this;
    }

    /**
     * Queries a PDO connection and restores the current object to
     * the values stored in the data source row specified by id.
     *
     * @param   integer     ID to lookup
     * @return  boolean     Was a row found?
     */
    public function find($id)
    {
        $sql = sprintf(
            'SELECT %s FROM %s WHERE %s = :id',
            implode(', ', array_values($this->_columns)),
            $this->_tableName,
            $this->_primaryKey
        );

        $stmt = $this->_dbh->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->_primaryId = $id;
            $lookup = array_flip($this->_columns);
            foreach ($result as $key => $value) {
                $this->set($lookup[$key], $value, true);
            }
        }

        return (boolean)$result;
    }
}
