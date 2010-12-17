<?php
/**
 * CreditCardFreezer
 *
 * Simple class which handles the encryption and decryption of
 * credit card information suitable for secure storage within
 * a database.
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


require_once dirname(__FILE__) . '/CreditCardFreezer/Exception.php';

class CreditCardFreezer
{
    /* Attributes */
    const NUMBER                    = 1;
    const EXPIRE_MONTH              = 2;
    const EXPIRE_YEAR               = 3;
    const CCV                       = 4;    // Numeric security code
    const FIRST_NAME                = 5;
    const LAST_NAME                 = 6;
    const ADDRESS                   = 7;
    const CITY                      = 8;
    const STATE                     = 9;
    const POSTAL_CODE               = 10;
    const COUNTRY                   = 11;
    const PHONE                     = 12;
    const TYPE                      = 13;   // Mastercard, Visa, etc.

    /* Special Attributes for Retrieval */
    const SECURE_STORE              = 14;   // Card number and expiration
                                            // as one value. Reduces columns
                                            // necessary in the database.

    /* Defaults to AES 128 bit encryption in CFB mode (uses an IV) */
    const CIPHER                    = MCRYPT_RIJNDAEL_256;
    const CIPHER_MODE               = MCRYPT_MODE_CFB;
    const KEY_LENGTH                = 32;

    /* Max size of value to encrypt */
    const ENCRYPT_CHUNK_BYTES       = 24;

    /* Size of outputted iv/encrypted text pair (estimate) */
    const ENCRYPT_CHUNK_STORAGE     = 90;

    /* Attribute Value Container */
    protected $_attr                = array();
    private   $_passkey             = null;

    /**
     * Creates a CreditCardFreezer object.
     * @param   array       Optional attributes passed as an array.
     * @return  CreditCardFreezer
     */
    public function __construct(array $attr = array())
    {
        if (!function_exists('mcrypt_encrypt')) {
            throw new CreditCardFreezer_Exception(
                'php-mcrypt library not installed. This library is required by '
                . 'the CreditCardFreezer class.'
            );
        }

        $this->_attr = $attr;
    }

    /**
     * Outputs all attributes as a string for purposes of debugging.
     * @return  string
     */
    public function __toString()
    {
        $textLabels = $this->_getTextLabels();
        $out        = array();

        foreach ($this->_attr as $attr => $value) {
            if (isset($textLabels[$attr])) {
                if (in_array($attr, array(
                    self::EXPIRE_MONTH,
                    self::EXPIRE_YEAR,
                    self::NUMBER
                ))) {
                    $out[] = sprintf('%15s %s (Encrypted: %s)',
                        $textLabels[$attr] . ':',
                        $value,
                        substr($this->get($attr, true), 0, 8) . '...'
                    );
                } else {
                    $out[] = sprintf('%15s %s', $textLabels[$attr] . ':', $value);
                }
            }
        }

        return implode("\n", $out) . "\n";
    }

    /**
     * Retrives the value for an attribute. If no attribute is
     * specified, it defaults to SECURE_STORE.
     *
     * @param   integer     Attribute (see class comments)
     * @param   boolean     If this is an encrypted attribute,
     *                      return the encrypted text and not the
     *                      actual value (e.g.: for database
     *                      storage)
     * @return  string
     */
    public function get($attr = null, $forStorage = false)
    {
        if ($attr === null) {
            $attr = self::SECURE_STORE;
        } else {
            $attr = $this->_lookupAttribute($attr);
        }

        /* This value can only be retrieved encrypted */
        if ($attr == self::SECURE_STORE) {
            $forStorage = true;
        }

        if ($forStorage) {
            return $this->getForStorage($attr);
        } else {
            return isset($this->_attr[$attr]) ? $this->_attr[$attr] : null;
        }
    }

    /**
     * Scans available attribute names and tries to return the correct
     * numerical constant value.
     *
     * @param   mixed       Search
     * @return  integer
     */
    protected function _lookupAttribute($attr)
    {
        if (preg_match('/^[0-9]+$/', $attr)) {
            $attr = (int)$attr;
        } else {
            $textLabels = $this->_getTextLabels();
            foreach ($textLabels as $index => $label) {
                $labels = array($label);
                $func = create_function('$c', 'return strtoupper($c[1]);');
                $camelCase = preg_replace_callback('/_([a-z])/', $func, $label);
                $labels[] = $camelCase;

                if (in_array($attr, $labels)) {
                    $attr = $index;
                    break;
                }
            }
        }

        return $attr;
    }

    /**
     * Sets the value for an attribute such as credit card number
     * or name. For a list of attributes, see the header of this
     * file.
     *
     * @param   integer     Attribute (see class comments)
     * @param   mixed       Value
     * @param   boolean     Is the value from data storage? Would
     *                      it need to be decrypted if it's a
     *                      secure attribute?
     * @return  CreditCardFreezer
     * @throws  CreditCardFreezer_Exception
     */
    public function set($attr, $value, $fromStorage = false)
    {
        $attr = $this->_lookupAttribute($attr);

        switch ($attr) {
            // Special combined value of the card number and expiration date
            case self::SECURE_STORE:
                // This value is always encrypted, so we ignore $fromStorage
                $plain = $this->_decrypt($value);
                if ($plain && preg_match('/^([0-9]{2})([0-9]{4})(.*)/', $plain, $matches)) {
                    list(
                        $ignore,
                        $this->_attr[self::EXPIRE_MONTH],
                        $this->_attr[self::EXPIRE_YEAR],
                        $this->_attr[self::NUMBER]
                    ) = $matches;
                } else {
                    throw new CreditCardFreezer_Exception(
                        'Secure store value does not decrypt to the expected '
                        . 'values. Perhaps the passkey has been changed? This can '
                        . 'happen if you do not set a passkey and move the source '
                        . 'file.'
                    );
                }
                break;

            case self::NUMBER:
            case self::EXPIRE_MONTH:
            case self::EXPIRE_YEAR:
            case self::CCV:
                if ($fromStorage) {
                    $this->_attr[$attr] = $this->_decrypt($value);
                } else {
                    $this->_attr[$attr] = preg_replace('/[^0-9]/', '', $value);
                }
                break;

            default:
                $this->_attr[$attr] = $value;
                break;
        }

        return $this;
    }

    /**
     * Encrypts a plain text value by creating a
     * unique iv for the transaction and truncating
     * it to the result of the mcrypt cipher.
     *
     * @param   string      Plain text value
     * @return  string      iv/encrypted text pair
     */
    private function _encrypt($value)
    {
        $len = strlen($value);
        if ($len > self::ENCRYPT_CHUNK_BYTES) {
            throw new CreditCardFreezer_Exception('Value to encrypt is too '
                . 'long. Should not exceed ' . self::ENCRYPT_CHUNK_BYTES . ' characters.'
            );
        } elseif ($len < self::ENCRYPT_CHUNK_BYTES) {
            $value = str_pad($value, self::ENCRYPT_CHUNK_BYTES);
        }

        // Most values are numerical (e.g.: card number, date, etc.)
        // We change the values to alphanumeric just to scramble any
        // patterns in the encrypted text.
        $value = base64_encode($value);

        $store = mcrypt_encrypt(
            self::CIPHER,
            $this->getPassKey(),
            $value,
            self::CIPHER_MODE,
            $iv = $this->_getIV()
        );

        return sprintf('%s|%s',
            base64_encode($iv),
            base64_encode($store)
        );
    }

    /**
     * Decrypts an encrypted value by breaking apart its
     * iv/encrypted text contents and passing them to
     * the appropriate mcrypt cipher.
     *
     * @param   string      iv/encrypted text pair
     * @return  string      Decrypted text value
     */
    private function _decrypt($value)
    {
        if (!strpos($value, '|')) {
            return false;
        }

        list($iv, $data)    = explode('|', $value);
        $iv                 = base64_decode($iv);
        $data               = base64_decode($data);

        $b64 = mcrypt_decrypt(
            self::CIPHER,
            $this->getPassKey(),
            $data,
            self::CIPHER_MODE,
            $iv
        );

        // We stored it base64 encoded to remove any numerical
        // patterns.
        $plain = base64_decode($b64);

        return rtrim($plain);
    }

    /**
     * Retrieves the value for an attribute.
     * @return  mixed
     */
    public function __get($attr)
    {
        return $this->get($attr);
    }

    /**
     * Sets the value for an attribute. See set().
     * @return  mixed
     */
    public function __set($attr, $value)
    {
        return $this->set($attr, $value);

        return null;
    }

    /**
     * Generate a random 8-byte IV.
     * @return  integer
     */
    private function _getIV()
    {
        $size = mcrypt_get_iv_size(self::CIPHER, self::CIPHER_MODE);
        return mcrypt_create_iv($size, MCRYPT_RAND);
    }

    /**
     * Sets the key to use in which to encrypt the secure data
     * attributes such as the credit card number and expiration
     * date. The key should be 32 bytes in length -- if it's too
     * short a hashing algorithm will be used to extend it. If
     * it's too long it will be truncated.
     *
     * A minimum length of 16 bytes is required.
     *
     * @param   string      Pass key
     * @return  CreditCardFreezer
     */
    public function setPassKey($key = '')
    {
        $len = strlen($key);
        // Best to set your own passkey :)
        if ($len <= 0) {
            $key = md5(__FILE__);
        }

        if ($len > self::KEY_LENGTH) {
            $key = substr($key, 0, self::KEY_LENGTH);
        } elseif ($len < self::KEY_LENGTH) {
            while (strlen($key) < self::KEY_LENGTH) {
                $key .= md5($key);
            }
            $key = substr($key, 0, self::KEY_LENGTH);
        }

        $this->_passkey = $key;

        return $this;
    }

    /**
     * Retrieves the 32-byte passkey which is used to encrypt
     * the secured attributes such as card number and the credit
     * card expiration date.
     *
     * @return      string
     */
    public function getPassKey()
    {
        if ($this->_passkey) {
            return $this->_passkey;
        } else {
            $this->setPassKey();
            return $this->_passkey;
        }
    }

    /**
     * Fills the attributes of the object from an array.
     *
     * @param   array       Values
     * @param   boolean     From storage? Are secure values encrypted?
     * @return  CreditCardFreezer
     */
    public function fromArray(array $values, $fromStorage = true)
    {
        foreach ($values as $attr => $value) {
            $this->set($attr, $value, $fromStorage);
        }

        return $this;
    }

    /**
     * Returns an array of attributes suitable for database
     * storage. You may pass an array of attributes that should
     * be encrypted, otherwise it will default to credit card
     * number, expiration month and year.
     *
     * CCV is never stored as it would be illegal.
     *
     * @param   array       Include only these attributes
     * @return  array       Value stores
     */
    public function getForStorage($filter = null)
    {
        $singular = false;
        $return = array();

        // Special attribute which stores the card number
        // and expiration date as a single encrypted text.
        // This reduces the storage space necessary in the
        // database/storage layer.
        if ($filter == self::SECURE_STORE) {
            return $this->_encrypt(sprintf('%02d%04d%s',
                $this->_attr[self::EXPIRE_MONTH],
                $this->_attr[self::EXPIRE_YEAR],
                $this->_attr[self::NUMBER]
            ));
        }

        // Attributes which should be encrypted
        $attrs = array(
            self::NUMBER,
            self::EXPIRE_MONTH,
            self::EXPIRE_YEAR
        );

        if (!empty($filter) && !is_array($filter)) {
            $singular = true;
            $filter = array($filter);
        }

        foreach ($this->_attr as $attr => $value) {
            if (!empty($filter) && !in_array($attr, $filter)) {
                continue;
            }

            if (in_array($attr, $attrs)) {
                $return[$attr] = $this->_encrypt($this->{$attr});
            } else {
                $return[$attr] = $this->{$attr};
            }

            if ($singular) {
                return $return[$attr];
            }
        }

        return $return;
    }

    /**
     * Returns the specified values for stored attributes (see class header)
     * as an array, generally for database insertion. Unset values will be
     * returned as null. For an associative array, see the toArray() method.
     *
     * @param   array       Attributes to include
     * @param   boolean     Encrypt secure values? For database storage?
     * @return  array
     */
    public function getValues(array $attrs = array(), $forStorage = true)
    {
        $return = array();

        if (empty($attrs)) {
            $attrs = array_keys($this->_attr);
        }

        foreach ($attrs as $attr) {
            $attr = $this->_lookupAttribute($attr);

            if (!isset($this->_attr[$attr])) {
                $return[] = null;
            } elseif ($forStorage) {
                $return[] = $this->getForStorage($attr);
            } else {
                $return[] = $this->get($attr);
            }
        }

        return $return;
    }

    /**
     * Returns all of the stored attributes as an associative array.
     * If $cleanNames is set to true, then instead of the class
     * attributes (see header of this file), clean text names will
     * be used instead. If $cleanNames is set to an array of text
     * labels to constants, then your labels will be used.
     *
     * @param   boolean     For database storage? (e.g.: encrypt some values)
     * @param   mixed       Use clean text for keys instead of constants.
     *                      If set to:
     *                          true)  Use text labels
     *                          array) Use custom labels (define them)
     * @return  array
     */
    public function toArray($forStorage = false, $cleanNames = true)
    {
        $return     = array();
        $textLabels = $this->_getTextLabels();

        foreach ($this->_attr as $attr => $value) {
            if ($forStorage) {
                $value = $this->getForStorage($attr);
            }

            if (isset($cleanNames[$attr])) {
                $return[$cleanNames[$attr]] = $value;
            } elseif ($cleanNames == true && isset($textLabels[$attr])) {
                $return[$textLabels[$attr]] = $value;
            } else {
                $return[$attr] = $value;
            }
        }

        return $return;
    }

    /**
     * Default text labels to describe the class constants.
     * @return  array
     */
    protected function _getTextLabels()
    {
        return array(
            self::NUMBER       => 'card_number',
            self::SECURE_STORE => 'secure_store',
            self::EXPIRE_MONTH => 'expire_month',
            self::EXPIRE_YEAR  => 'expire_year',
            self::CCV          => 'card_ccv',
            self::TYPE         => 'card_type',
            self::FIRST_NAME   => 'first_name',
            self::LAST_NAME    => 'last_name',
            self::ADDRESS      => 'address',
            self::CITY         => 'city',
            self::STATE        => 'state',
            self::POSTAL_CODE  => 'postal_code',
            self::COUNTRY      => 'country',
            self::PHONE        => 'phone'
        );
    }
}
