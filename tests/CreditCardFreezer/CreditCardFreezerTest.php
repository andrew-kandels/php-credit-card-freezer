<?php
class CreditCardFreezerTest extends CreditCardFreezer_TestCase
{
    private $_obj;

    public function setUp()
    {
        $this->_obj = new CreditCardFreezer(array(
            CreditCardFreezer::NUMBER       => '1234123412341234',
            CreditCardFreezer::EXPIRE_MONTH => '12',
            CreditCardFreezer::EXPIRE_YEAR  => '2010',
            CreditCardFreezer::CCV          => '118',
            CreditCardFreezer::FIRST_NAME   => 'Andrew',
            CreditCardFreezer::LAST_NAME    => 'Kandels',
            CreditCardFreezer::ADDRESS      => '123 ABC St.',
            CreditCardFreezer::CITY         => 'Minneapolis',
            CreditCardFreezer::STATE        => 'MN',
            CreditCardFreezer::POSTAL_CODE  => '55406',
            CreditCardFreezer::COUNTRY      => 'US',
            CreditCardFreezer::PHONE        => '6515550199',
            CreditCardFreezer::TYPE         => 'Visa'
        ));
    }

    public function tearDown()
    {
        $this->_obj = null;
    }

    public function testConstructor()
    {
        $this->assertEquals('1234123412341234', $this->_obj->get(CreditCardFreezer::NUMBER));
    }

    public function testGet()
    {
        $this->assertEquals($value = '1234123412341234',
            $this->_obj->get(CreditCardFreezer::NUMBER)
        );
        $encrypted = $this->_obj->get(CreditCardFreezer::NUMBER, true);
    }

    public function testGetForStorage()
    {
        $value = '1234123412341234';
        $encrypted = $this->_obj->get(CreditCardFreezer::NUMBER, true);
        $this->assertNotEquals($value, $encrypted);
        $this->assertTrue(strlen($encrypted) > (CreditCardFreezer::ENCRYPT_CHUNK_STORAGE / 2));
    }

    public function testGetReturnsNull()
    {
        $this->assertTrue($this->_obj->get(0) === null);
    }

    public function testSet()
    {
        $obj = new CreditCardFreezer();
        $obj->set(CreditCardFreezer::NUMBER, $value = '1234123412341234');
        $this->assertEquals($value, $obj->get(CreditCardFreezer::NUMBER));
    }

    public function testSetReturnsSelf()
    {
        $return = $this->_obj->set(CreditCardFreezer::NUMBER, $value = '1234123412341234');
        $this->assertEquals($this->_obj, $return);
    }

    public function testSetForStorage()
    {
        $obj = new CreditCardFreezer();
        $obj->set(CreditCardFreezer::NUMBER, $value = '1234123412341234');
        $encrypted = $obj->get(CreditCardFreezer::NUMBER, true);
        $this->_obj->set(CreditCardFreezer::NUMBER, $encrypted, true);
        $this->assertEquals($value, $this->_obj->get(CreditCardFreezer::NUMBER));
    }

    public function testSetSecureStore()
    {
        $obj = new CreditCardFreezer();
        $obj->set(CreditCardFreezer::NUMBER, $num = '1234123412341234')
            ->set(CreditCardFreezer::EXPIRE_MONTH, $month = '12')
            ->set(CreditCardFreezer::EXPIRE_YEAR, $year = '2010');
        $encrypted = $obj->get(CreditCardFreezer::SECURE_STORE);

        $this->assertTrue(strlen($encrypted) > (CreditCardFreezer::ENCRYPT_CHUNK_STORAGE / 2));

        $this->_obj->set(CreditCardFreezer::SECURE_STORE, $encrypted);
        $this->assertEquals($num, $this->_obj->get(CreditCardFreezer::NUMBER));
        $this->assertEquals($month, $this->_obj->get(CreditCardFreezer::EXPIRE_MONTH));
        $this->assertEquals($year, $this->_obj->get(CreditCardFreezer::EXPIRE_YEAR));
    }

    public function testSetSecureStoreException()
    {
        try {
            $this->_obj->set(CreditCardFreezer::SECURE_STORE, 'bad text');
            $value = false;
        } catch (CreditCardFreezer_Exception $e) {
            $value = true;
        }
        $this->assertTrue($value);
    }

    public function testEncryptTooLarge()
    {
        $longString = str_repeat('1', CreditCardFreezer::ENCRYPT_CHUNK_BYTES + 100);
        $this->_obj->set(CreditCardFreezer::NUMBER, $longString);
        try {
            $this->_obj->get(CreditCardFreezer::NUMBER, true);
            $value = false;
        } catch(CreditCardFreezer_Exception $e) {
            $value = true;
        }
        $this->assertTrue($value);
    }

    public function testEncryptPadding()
    {
        $shortString = '1234';
        $longString = '1234123412341234';

        $this->_obj->set(CreditCardFreezer::NUMBER, $longString);
        $len1 = strlen($this->_obj->get(CreditCardFreezer::NUMBER, true));

        $this->_obj->set(CreditCardFreezer::NUMBER, $shortString);
        $len2 = strlen($this->_obj->get(CreditCardFreezer::NUMBER, true));

        $this->assertEquals($len1, $len2);
    }

    public function testSetNumericOnly()
    {
        $numericKeys = array(
            CreditCardFreezer::NUMBER,
            CreditCardFreezer::EXPIRE_MONTH,
            CreditCardFreezer::EXPIRE_YEAR,
            CreditCardFreezer::CCV
        );
        $nonNumericKeys = array(
            CreditCardFreezer::FIRST_NAME,
            CreditCardFreezer::LAST_NAME,
            CreditCardFreezer::ADDRESS,
            CreditCardFreezer::CITY,
            CreditCardFreezer::STATE,
            CreditCardFreezer::POSTAL_CODE,
            CreditCardFreezer::TYPE,
            CreditCardFreezer::PHONE,
            CreditCardFreezer::COUNTRY
        );

        $numericValue = '1234';
        $nonNumericValue = 'abcd';

        foreach ($numericKeys as $key) {
            $this->_obj->set($key, $numericValue);
            $this->assertEquals($numericValue, $this->_obj->get($key));

            $this->_obj->set($key, $nonNumericValue);
            $this->assertNotEquals($nonNumericValue, $this->_obj->get($key));
        }

        foreach ($nonNumericKeys as $key) {
            $this->_obj->set($key, $numericValue);
            $this->assertEquals($numericValue, $this->_obj->get($key));

            $this->_obj->set($key, $nonNumericValue);
            $this->assertEquals($nonNumericValue, $this->_obj->get($key));
        }
    }

    public function testAccessorShortcuts()
    {
        $this->assertEquals(
            $this->_obj->get(CreditCardFreezer::NUMBER),
            $this->_obj->{CreditCardFreezer::NUMBER}
        );

        $this->_obj->{CreditCardFreezer::NUMBER} = $num = '12341234';

        $this->assertEquals($num, $this->_obj->{CreditCardFreezer::NUMBER});
    }

    public function testGetPassKeyWhenNoneGeneratesSomething()
    {
        $this->assertTrue(strlen($this->_obj->getPassKey()) == CreditCardFreezer::KEY_LENGTH);
    }

    public function testGetPassKeyTooLongTruncates()
    {
        $this->_obj->setPassKey(str_repeat('X', CreditCardFreezer::KEY_LENGTH * 2));
        $this->assertTrue(strlen($this->_obj->getPassKey()) == CreditCardFreezer::KEY_LENGTH);
    }

    public function testGetPassKeyTooShortAppends()
    {
        $this->_obj->setPassKey(str_repeat('X', CreditCardFreezer::KEY_LENGTH / 2));
        $this->assertTrue(strlen($this->_obj->getPassKey()) == CreditCardFreezer::KEY_LENGTH);
    }

    public function testSetPassKeyReturnsSelf()
    {
        $this->assertEquals($this->_obj, $this->_obj->setPassKey('test'));
    }

    public function testGetForStorageSecureStore()
    {
        $obj = new CreditCardFreezer();
        $obj->set(CreditCardFreezer::SECURE_STORE,
            $this->_obj->getForStorage(CreditCardFreezer::SECURE_STORE)
        );
        $this->assertEquals($obj->get(CreditCardFreezer::NUMBER),
            $this->_obj->get(CreditCardFreezer::NUMBER)
        );
    }

    public function testGetForStorageSingleIsScalar()
    {
        $this->assertFalse(is_array($this->_obj->getForStorage(CreditCardFreezer::NUMBER)));
    }

    public function testGetForStorageManyIsNotScalar()
    {
        $this->assertTrue(is_array($this->_obj->getForStorage(array(
            CreditCardFreezer::NUMBER,
            CreditCardFreezer::EXPIRE_MONTH
        ))));
    }

    public function testGetForStorageIsEncrypted()
    {
        $encrypted = array(
            CreditCardFreezer::NUMBER,
            CreditCardFreezer::EXPIRE_MONTH,
            CreditCardFreezer::EXPIRE_YEAR
        );
        foreach ($encrypted as $key) {
            $this->assertNotEquals($this->_obj->get($key),
                $this->_obj->getForStorage($key)
            );
        }
        $this->assertEquals($this->_obj->get(CreditCardFreezer::FIRST_NAME),
            $this->_obj->getForStorage(CreditCardFreezer::FIRST_NAME)
        );
    }

    public function testGetForStorageIsNotEncrypted()
    {
        $this->assertEquals($this->_obj->get(CreditCardFreezer::FIRST_NAME),
            $this->_obj->getForStorage(CreditCardFreezer::FIRST_NAME)
        );
    }

    public function testGetValues()
    {
        $keys = array(
            CreditCardFreezer::FIRST_NAME,
            CreditCardFreezer::NUMBER,
            CreditCardFreezer::EXPIRE_MONTH,
            0
        );
        $values = array();

        foreach ($keys as $key) {
            $values[] = $this->_obj->get($key, false);
        }

        $this->assertEquals($values, $this->_obj->getValues($keys, false));
    }

    public function testToArray()
    {
        $val = $this->_obj->toArray(false, false);
        $this->assertEquals($val[CreditCardFreezer::NUMBER],
            $this->_obj->get(CreditCardFreezer::NUMBER, false)
        );
    }

    public function testToArrayClean()
    {
        $val = $this->_obj->toArray(false, true);
        $this->assertEquals($val['card_number'],
            $this->_obj->get(CreditCardFreezer::NUMBER, false)
        );
    }

    public function testToArrayCustom()
    {
        $val = $this->_obj->toArray(false, array(CreditCardFreezer::NUMBER, 'test'));
        $this->assertEquals($val['test'],
            $this->_obj->get(CreditCardFreezer::NUMBER, false)
        );
    }
}
