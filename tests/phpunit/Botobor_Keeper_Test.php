<?php
require_once dirname(__FILE__) . '/bootstrap.php';
require_once SRC_ROOT . '/botobor.php';

class Botobor_Keeper_Test extends PHPUnit_Framework_TestCase
{
	/**
	 * @see PHPUnit_Framework_TestCase::tearDown()
	 */
	protected function tearDown()
	{
		$p_isRobot = new ReflectionProperty('Botobor_Keeper', 'isRobot');
		$p_isRobot->setAccessible(true);
		$p_isRobot->setValue(Botobor_Keeper::get(), false);
	}

    /**
     * @covers Botobor_Keeper::get
     */
    public function test_get()
    {
        $keeper1 = Botobor_Keeper::get();
        $keeper2 = Botobor_Keeper::get();
        $this->assertSame($keeper1, $keeper2);
    }

    /**
     * @covers Botobor_Keeper::isRobot
     */
    public function test_isRobot()
    {
        $p_isHandled = new ReflectionProperty('Botobor_Keeper', 'isHandled');
        $p_isHandled->setAccessible(true);
        $p_isHandled->setValue(Botobor_Keeper::get(), true);

        $p_isRobot = new ReflectionProperty('Botobor_Keeper', 'isRobot');
        $p_isRobot->setAccessible(true);

        $p_isRobot->setValue(Botobor_Keeper::get(), false);
        $this->assertFalse(Botobor_Keeper::get()->isRobot());

        $p_isRobot->setValue(Botobor_Keeper::get(), true);
        $this->assertTrue(Botobor_Keeper::get()->isRobot());
    }

    /**
	 * @covers Botobor_Keeper::isResubmit
	 */
	public function test_isResubmit()
	{
		$meta = new Botobor_MetaData();
		$data = array(
			Botobor::META_FIELD_NAME => $meta->getEncoded()
		);

		$p_isHandled = new ReflectionProperty('Botobor_Keeper', 'isHandled');
		$p_isHandled->setAccessible(true);
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$_GET = $data;
		$p_isHandled->setValue(Botobor_Keeper::get(), false);
		$this->assertFalse(Botobor_Keeper::get()->isResubmit());

		$_GET = $data;
		$p_isHandled->setValue(Botobor_Keeper::get(), false);
		$this->assertTrue(Botobor_Keeper::get()->isResubmit());
	}

	/**
	 * @covers Botobor_Keeper::handleRequest
	 * @covers Botobor_Keeper::testHoneypots
	 * @covers Botobor_Keeper::testReferer
	 * @covers Botobor_Keeper::testTimings
     * @covers Botobor_Keeper::isRobot
     * @covers Botobor_Keeper::getFailedCheck
	 */
	public function test_handleRequest()
	{
        /** @var Botobor_MetaData $meta */
        $checks = get_botobor_checks();
        foreach ($checks as $check => $state)
		{
			Botobor::set('check.' . $check, true);
		}

        $p_isHandled = new ReflectionProperty('Botobor_Keeper', 'isHandled');
        $p_isHandled->setAccessible(true);
        
        $keeper = Botobor_Keeper::get();
        
        $p_isHandled->setValue($keeper, false);
		$this->assertTrue($keeper->isRobot());
        $this->assertEquals('error', $keeper->getFailedCheck());


		$_SERVER['REQUEST_METHOD'] = 'GET';
		$keeper->handleRequest();
		$this->assertTrue($keeper->isRobot());
        $this->assertEquals('meta', $keeper->getFailedCheck());


		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST[Botobor::META_FIELD_NAME] = true;
		$keeper->handleRequest();
		$this->assertTrue($keeper->isRobot());
        $this->assertEquals('meta', $keeper->getFailedCheck());


		$meta = new Botobor_MetaData();
        /** @var Botobor_MetaData $meta */
        $meta->checks = get_botobor_checks();
        $meta->aliases = array('aaa' => 'name');
		$data = array(
			Botobor::META_FIELD_NAME => $meta->getEncoded(),
			'name' => 'RobotName',
			'aaa' => 'HumanName',
		);
		$keeper->handleRequest($data);
		$this->assertTrue($keeper->isRobot());
		$this->assertEquals('HumanName', $data['name']);
        $this->assertEquals('honeypots', $keeper->getFailedCheck());


		$meta = new Botobor_MetaData();
        /** @var Botobor_MetaData $meta */
        $meta->checks = get_botobor_checks();
        $_POST[Botobor::META_FIELD_NAME] = $meta->getEncoded() . 'break_sign';
		$keeper->handleRequest();
		$this->assertTrue($keeper->isRobot());
        $this->assertEquals('meta', $keeper->getFailedCheck());


		$meta = new Botobor_MetaData();
        /** @var Botobor_MetaData $meta */
        $meta->checks = get_botobor_checks();
        $meta->timestamp = time();
		$meta->delay = 10;
		$data = array(Botobor::META_FIELD_NAME => $meta->getEncoded());
		$keeper->handleRequest($data);
		$this->assertTrue($keeper->isRobot());
        $this->assertEquals('delay', $keeper->getFailedCheck());


		$meta = new Botobor_MetaData();
        /** @var Botobor_MetaData $meta */
        $meta->checks = get_botobor_checks();
        $meta->timestamp = time() - 11 * 60;
		$meta->lifetime = 10;
		$_POST[Botobor::META_FIELD_NAME] = $meta->getEncoded();
		$keeper->handleRequest();
		$this->assertTrue($keeper->isRobot());
        $this->assertEquals('lifetime', $keeper->getFailedCheck());


		$meta = new Botobor_MetaData();
        /** @var Botobor_MetaData $meta */
        $meta->checks = get_botobor_checks();
        $meta->timestamp = time() - 15;
		$meta->delay = 10;
		$meta->lifetime = 10;
		$meta->referer = 'http://example.org/index.php';
		$_POST[Botobor::META_FIELD_NAME] = $meta->getEncoded();
		$keeper->handleRequest();
		$this->assertTrue($keeper->isRobot());
        $this->assertEquals('referer', $keeper->getFailedCheck());


		$meta = new Botobor_MetaData();
        /** @var Botobor_MetaData $meta */
        $meta->checks = get_botobor_checks();
        $meta->timestamp = time() - 15;
		$meta->delay = 10;
		$meta->lifetime = 10;
		$meta->referer = 'http://example.org/index.php';
		$_POST[Botobor::META_FIELD_NAME] = $meta->getEncoded();
		$_SERVER['HTTP_REFERER'] = 'http://example.org/';
		$keeper->handleRequest();
		$this->assertTrue($keeper->isRobot());
        $this->assertEquals('referer', $keeper->getFailedCheck());


		$meta = new Botobor_MetaData();
        /** @var Botobor_MetaData $meta */
        $meta->checks = get_botobor_checks();
        $meta->timestamp = time() - 15;
		$meta->delay = 10;
		$meta->lifetime = 10;
		$meta->referer = 'http://example.org/index.php';
		$_POST[Botobor::META_FIELD_NAME] = $meta->getEncoded();
		$_SERVER['HTTP_REFERER'] = 'http://example.org/index.php';
		$keeper->handleRequest();
		$this->assertFalse($keeper->isRobot());
        $this->assertNull($keeper->getFailedCheck());
	}

	/**
	 * @covers Botobor_Keeper::handleRequest
	 */
	public function test_handleRequest_custom()
	{
		$req = array('name' => 'RobotName', 'aaa' => 'HumanName');
		$meta = new Botobor_MetaData();
		$meta->aliases = array('aaa' => 'name');
		$req[Botobor::META_FIELD_NAME] = $meta->getEncoded();

        $keeper = Botobor_Keeper::get();
		$keeper->handleRequest($req);
		$this->assertTrue(Botobor_Keeper::get()->isRobot());
		$this->assertEquals('HumanName', $req['name']);
	}
}