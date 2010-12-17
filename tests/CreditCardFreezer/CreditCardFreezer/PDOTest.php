<?php
class CreditCardFreezer_PDOTest extends CreditCardFreezer_TestCase
{
    private $_obj;
    private $_dbh;
    private $_file;

    public function setUp()
    {
        $path = dirname(__FILE__);
        $this->_file = "$path/../database.sdb";
        if (file_exists($this->_file)) {
            unlink($this->_file);
        }
        $this->_dbh = new PDO("sqlite:$this->_file");
        $this->_dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->_obj = new CreditCardFreezer_PDO($this->_dbh);
        $this->_obj->setTableName('credit_card');
        $this->_obj->setPrimaryKey('credit_card_id');

        $defaults = array(
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
        );

        foreach ($defaults as $attr => $value) {
            $this->_obj->set($attr, $value);
        }
    }

    public function tearDown()
    {
        $this->_obj = null;
        $this->_dbh = null;
        @unlink($this->_file);
    }

    public function testCreateTableInsertAndFind()
    {
        $cardId = $this->_obj->createTable()
            ->insert();

        $obj = new CreditCardFreezer_PDO($this->_dbh);
        $obj->find($cardId);

        foreach (array(
            CreditCardFreezer::NUMBER,
            CreditCardFreezer::EXPIRE_MONTH,
            CreditCardFreezer::EXPIRE_YEAR,
            CreditCardFreezer::TYPE,
            CreditCardFreezer::FIRST_NAME,
            CreditCardFreezer::LAST_NAME,
            CreditCardFreezer::ADDRESS,
            CreditCardFreezer::CITY,
            CreditCardFreezer::STATE,
            CreditCardFreezer::POSTAL_CODE,
            CreditCardFreezer::COUNTRY,
            CreditCardFreezer::PHONE
        ) as $attr) {
            $this->assertEquals($this->_obj->get($attr), $obj->get($attr));
        }
    }

    public function testCreateTableInsertAndDelete()
    {
        $cardId = $this->_obj->createTable()
            ->insert();
        $stmt = $this->_dbh->query('SELECT COUNT(*) FROM credit_card');
        $num = reset($stmt->fetch(PDO::FETCH_NUM));
        $this->assertEquals(1, $num);

        $this->_obj->delete();
        $stmt = $this->_dbh->query('SELECT COUNT(*) FROM credit_card');
        $num = reset($stmt->fetch(PDO::FETCH_NUM));
        $this->assertEquals(0, $num);
    }

    public function testCreateTableAndUpdate()
    {
        $this->_obj->createTable()->insert();
        $stmt = $this->_dbh->query('SELECT first_name FROM credit_card');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Andrew', $result['first_name']);

        $this->_obj->set(CreditCardFreezer::FIRST_NAME, 'Jim');
        $this->_obj->save();
        $stmt = $this->_dbh->query('SELECT first_name FROM credit_card');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Jim', $result['first_name']);
    }
}

