<?php
/**
 * CreditCardFreezer_AuthorizeDotNet
 *
 * Extends CreditCardFreezer to add numerous convenience
 * routines for creating, updating and deleting credit card
 * charges for the Authorize.Net payment gateway using their
 * API.
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

class CreditCardFreezer_AuthorizeDotNet extends CreditCardFreezer
{
    /* Additional Attributes */
    const INVOICE               = 30;
    const DESCRIPTION           = 31;
    const COMPANY               = 32;
    const EMAIL                 = 33;
    const CUSTOMER_ID           = 34;
    const CUSTOMER_IP           = 35;

    /* Authorize.NET Configuration */
    const X_VERSION             = '3.1';
    const X_DELIM_DATA          = 'TRUE';
    const X_DELIM_CHAR          = '|';
    const X_RELAY_RESPONSE      = 'FALSE';
    const X_METHOD              = 'CC';
    const POST_URL              = '';

    private $_loginId           = null;
    private $_transactionKey    = null;
    private $_postUrl           = null;

    /**
     * Creates a CreditCardFreezer_AuthorizeDotNet object which stores
     * credit card and billing information and can make charges through
     * the Authorize.Net payment gateway.
     *
     * @param   string      Authorize.NET API Login ID
     * @param   string      Authorize.NET API Transaction Key
     * @param   string      Authorize.NET API URL (sandbox, production, etc.)
     */
    public function __construct($loginId, $transactionKey, $postUrl = null)
    {
        parent::__construct();

        if (!function_exists(curl_init)) {
            throw new CreditCardFreezer_Exception(
                'php-curl extension does not exist. This extension is required '
                . 'by CreditCardFreezer_AuthorizeDotNet.'
            );
        }

        $this->_loginId         = $loginId;
        $this->_transactionKey  = $transactionKey;
        $this->_postUrl         = $postUrl;

        if (!$this->_postUrl) {
            $this->_postUrl = self::POST_URL;
        }
    }

    /**
     * Default text labels to describe the class constants.
     * @return  array
     */
    protected function _getTextLabels()
    {
        return array_merge(parent::_getTextLabels(), array(
            self::INVOICE      => 'invoice',
            self::DESCRIPTION  => 'description',
            self::COMPANY      => 'company',
            self::EMAIL        => 'email',
            self::CUSTOMER_ID  => 'customer_id',
            self::CUSTOMER_IP  => 'customer_ip'
        ));
    }

    /**
     * Processes a one-time charge through the Authorize.NET
     * payment gateway.
     *
     * @param   float       Amount to charge
     * @return  integer     Transaction ID
     * @throws  CreditCardException
     */
    public function charge($amount)
    {
        if ($amount < 1) {
            throw new CreditCardFreezer_Exception(
                'Charge amount should be more than one dollar.'
            );
        }

        $params = $this->_getParams(array(
            'x_amount'              => $amount,
            'x_recurring_billing'   => 'FALSE',
            'x_type'                => 'AUTH_CAPTURE'
        ));

        $response = $this->_sendRequest($params);

        return $response['transactionId'];
    }

    /**
     * Adds an Authorize.net recurring subscription, which is a repeating charge
     * every X number of months until deleted or the customer cancels.
     *
     * @param   float       Amount to charge
     * @param   integer     Monthly increment (1 = charge once per month)
     * @param   timestamp   Start date (date of first charge, must be in future,
     *                      defaults to tomorrow)
     * @return  integer     Transaction ID
     * @throws  CreditCardException
     */
    public function addRecurring($amount, $months = 1, $ts = null)
    {
        if ($amount < 1) {
            throw new CreditCardFreezer_Exception(
                'Charge amount should be more than one dollar.'
            );
        }

        $params = $this->_getParams(array(
            'x_amount'              => $amount,
            'x_recurring_billing'   => 'TRUE',
            'x_type'                => 'AUTH_CAPTURE'
        ));

        $response = $this->_sendRequest($params);

        return $response['transactionId'];
    }


    /**
     * Translates an array of name/value pairs into a urlencoded
     * string for use in an HTTP POST or GET request.
     *
     * @param   array       Name/value pairs
     * @return  string      URL string
     */
    private function _getPostUrlString(array $params)
    {
        $postData = array();
        foreach ($params as $name => $value) {
            $postData[] = sprintf('%s=%s',
                $name,
                urlencode($value)
            );
        }

        return implode('&', $postData);
    }

    /**
     * Submits a request to the Authorize.net API.
     *
     * @param   mixed       (string) or (array) post data
     * @return  integer     Transaction ID #
     * @throws  CreditCardFreezer_Exception
     */
    private function _sendRequest($postData)
    {
        if (is_array($postData)) {
            $postData = $this->_getPostUrlString($postData);
        }

        $request = curl_init($this->_postUrl);
        curl_setopt($request, CURLOPT_HEADER, 0);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($request, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER, FALSE);
        $response = curl_exec($request);
        curl_close ($request);

        if (count($response) < 7) {
            throw new CreditCardException(
                'Authorize.net API request failed. Invalid and unexpected response.'
            );
        }

        list(
            $code,
            $subCode,
            $errorCode,
            $errorText,
            $authCode,
            $avs,
            $transactionId
        ) = explode('|', $response);

        if ($code == 1) {
            return array(
                'code'          => $code,
                'subCode'       => $subCode,
                'authCode'      => $authCode,
                'avs'           => $avs,
                'transactionId' => $transactionId
            );
        } else {
            throw new CreditCardException(
                "Authorize.net transaction failed with code $errorCode: $errorText."
            );
        }
    }

    /**
     * If you forget to fill in some of the Authorize.net
     * required attributes, make some educated guesses. If no
     * guess can be made and it's still blank, an exception will
     * be thrown.
     * @return void
     * @throws CreditCardFreezer_Exception
     */
    private function _checkAndFillRequiredAttributes()
    {
        $defaultValues = array(
            self::DESCRIPTION               => 'Charge',
            self::INVOICE                   => '1',
            self::CUSTOMER_IP               => $_SERVER['REMOTE_ADDR']
        );

        $requiredAttrs = array();

        foreach ($defaultValues as $attr => $value) {
            if (!$this->get($attr)) {
                $this->set($attr, $value);
            }
        }

        foreach ($requiredAttrs as $attr) {
            if (!$this->get($attr)) {
                throw new CreditCardFreezer_Exception(
                    $attr . ' is a required attribute for an Authorize.net '
                    . 'transaction.'
                );
            }
        }
    }

    /**
     * Returns a name/value pair of API parameters that are used
     * with all Authorize.net transactions.
     *
     * @param   array       Name/value pairs to append/replace
     * @return  array       Merged Name/value pairs
     * @throws  CreditCardFreezer_Exception
     */
    private function _getParams(array $myParams = array())
    {
        $this->_checkAndFillRequiredAttributes();

        $params = array(
            'x_login'               => $this->_loginId,
            'x_tran_key'            => $this->_transactionKey,
            'x_version'             => self::X_VERSION,
            'x_delim_data'          => self::X_DELIM_DATA,
            'x_delim_char'          => self::X_DELIM_CHAR,
            'x_relay_response'      => self::X_RELAY_RESPONSE,
            'x_method'              => self::X_METHOD,
            'x_card_num'            => $this->get(self::NUMBER),
            'x_exp_date'            => sprintf('%02d/%04d',
                                            $this->get(self::EXPIRE_MONTH),
                                            $this->get(self::EXPIRE_YEAR)
                                       ),
            'x_invoice_num'         => $this->get(self::INVOICE),
            'x_description'         => $this->get(self::DESCRIPTION),
            'x_first_name'          => $this->get(self::FIRST_NAME),
            'x_last_name'           => $this->get(self::LAST_NAME),
            'x_company'             => $this->get(self::COMPANY),
            'x_address'             => $this->get(self::ADDRESS),
            'x_city'                => $this->get(self::CITY),
            'x_state'               => $this->get(self::STATE),
            'x_zip'                 => $this->get(self::POSTAL_CODE),
            'x_country'             => $this->get(self::COUNTRY),
            'x_phone'               => $this->get(self::PHONE),
            'x_email'               => $this->get(self::EMAIL),
            'x_cust_id'             => $this->get(self::CUSTOMER_ID),
            'x_customer_ip'         => $this->get(self::CUSTOMER_IP)
        );

        foreach ($myParams as $name => $value) {
            $params[$name] = $value;
        }

        return $params;
    }
}
