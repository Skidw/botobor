<?php
require_once dirname(__FILE__) . '/bootstrap.php';
require_once SRC_ROOT . '/botobor.php';

class Botobor_Form_Test extends PHPUnit_Framework_TestCase
{
	/**
	 * @covers Botobor_Form::__construct
	 * @covers Botobor_Form::setDelay
	 * @covers Botobor_Form::setLifetime
	 */
	public function test_construct()
	{
		$form = $this->getMockBuilder('Botobor_Form')->
			setMethods(array('setDelay', 'setLifetime', 'getCode'))->
			disableOriginalConstructor()->getMock();
		$_SERVER['REQUEST_URI'] = '/index.php';
		$_SERVER['HTTP_HOST'] = 'example.org';
		Botobor::set('honeypots', array('name', 'mail'));
        /** @var Botobor_Form $form */
		$form->__construct('[form]');

		$p_meta = new ReflectionProperty('Botobor_Form', 'meta');
		$p_meta->setAccessible(true);
		$this->assertEquals('http://example.org/index.php', $p_meta->getValue($form)->referer);

		$p_honeypots = new ReflectionProperty('Botobor_Form', 'honeypots');
		$p_honeypots->setAccessible(true);
		$this->assertEquals(array('name', 'mail'), $p_honeypots->getValue($form));
	}

	/**
	 * @covers Botobor_Form::setCheck
	 */
	public function test_setCheck()
	{
		$form = $this->getMockBuilder('Botobor_Form')->setConstructorArgs(array('<foo>'))->
			setMethods(array('getCode'))->getMock();
        /** @var Botobor_Form $form */
		$form->setCheck('referer', false);

		$p_meta = new ReflectionProperty('Botobor_Form', 'meta');
		$p_meta->setAccessible(true);
		$this->assertFalse($p_meta->getValue($form)->checks['referer']);
	}

	/**
	 * @covers Botobor_Form::addHoneypot
	 */
	public function test_addHoneypot()
	{
		$form = $this->getMockBuilder('Botobor_Form')->setMethods(array('getCode'))->
			disableOriginalConstructor()->getMock();
        /** @var Botobor_Form $form */
		$form->addHoneypot('foo');
		$p_honeypots = new ReflectionProperty('Botobor_Form', 'honeypots');
		$p_honeypots->setAccessible(true);
		$this->assertEquals(array('foo'), $p_honeypots->getValue($form));
	}

	/**
	 * @covers Botobor_Form::setDelay
	 */
	public function test_setDelay()
	{
		$form = $this->getMockForAbstractClass('Botobor_Form', array('[form]'));
        /** @var Botobor_Form $form */
		$form->setDelay(123);
		$p_meta = new ReflectionProperty('Botobor_Form', 'meta');
		$p_meta->setAccessible(true);
		$this->assertEquals(123, $p_meta->getValue($form)->delay);
	}

	/**
	 * @covers Botobor_Form::setLifetime
	 */
	public function test_setLifetime()
	{
		$form = $this->getMockForAbstractClass('Botobor_Form', array('[form]'));
        /** @var Botobor_Form $form */
		$form->setLifetime(123);
		$p_meta = new ReflectionProperty('Botobor_Form', 'meta');
		$p_meta->setAccessible(true);
		$this->assertEquals(123, $p_meta->getValue($form)->lifetime);
	}

	/**
	* @covers Botobor_Form::createInput
	*/
	public function test_createInput()
	{
		$form = new Botobor_Form('<form>');
		$m_createInput = new ReflectionMethod('Botobor_Form', 'createInput');
		$m_createInput->setAccessible(true);
		$this->assertEquals('<input type="a" name="b">', $m_createInput->invoke($form, 'a', 'b'));
		$this->assertEquals('<input type="a" name="b" value="c">',
		$m_createInput->invoke($form, 'a', 'b', 'c'));
		$this->assertEquals('<input type="a" name="b" value="c" d>',
		$m_createInput->invoke($form, 'a', 'b', 'c', 'd'));
	}

	/**
	 * @covers Botobor_Form::createHoneypots
	 */
	public function test_createHoneypots()
	{
		$form = new Botobor_Form('<form>');
		$m_createHoneypots = new ReflectionMethod('Botobor_Form', 'createHoneypots');
		$m_createHoneypots->setAccessible(true);
		$this->assertRegExp(
			'~<form><div style="display: none;"><input type="text" name="a"></div><input name=".+"></form>~',
		$m_createHoneypots->invoke($form, '<form><input name="a"></form>', array('a', 'b')));
	}

	/**
	 * @covers Botobor_Form::getCode
	 */
	public function test_getCode()
	{
		$form = $this->getMockBuilder('Botobor_Form')->
		setMethods(array('createInput', 'createHoneypots'))->
		setConstructorArgs(array('<form></form>'))->getMock();
		$form->expects($this->once())->method('createHoneypots')->
		will($this->returnValue('<form><honeypots></form>'));
		$form->expects($this->once())->method('createInput')->
		with('hidden', 'botobor_meta_data', '[metadata]')->will($this->returnValue('<meta>'));

		$meta = $this->getMock('Botobor_MetaData', array('getEncoded'));
        /** @var Botobor_MetaData $meta */
		$meta->checks = get_botobor_checks();
		$meta->expects($this->once())->method('getEncoded')->will($this->returnValue('[metadata]'));

		$p_meta = new ReflectionProperty('Botobor_Form', 'meta');
		$p_meta->setAccessible(true);
		$p_meta->setValue($form, $meta);

		$this->assertEquals('<form><div style="display: none;"><meta></div><honeypots></form>',
        /** @var Botobor_Form $form */
        $form->getCode());
	}

	/**
	 * @link https://github.com/mekras/botobor/issues/2
	 * @covers Botobor_Form::getCode
	 */
	public function test_issue2()
	{
		$form = $this->getMockBuilder('Botobor_Form')->
		setMethods(array('createInput', 'createHoneypots'))->
		setConstructorArgs(array('<form></form>'))->getMock();
		$form->expects($this->any())->method('createHoneypots')->
		will($this->returnValue('<form><honeypots></form>'));
		$form->expects($this->any())->method('createInput')->
		with('hidden', 'botobor_meta_data', '[metadata]')->will($this->returnValue('<meta>'));

		$meta = $this->getMock('Botobor_MetaData', array('getEncoded'));
        /** @var Botobor_MetaData $meta */
        $meta->checks = get_botobor_checks();
        $meta->expects($this->once())->method('getEncoded')->will($this->returnValue('[metadata]'));

		$p_meta = new ReflectionProperty('Botobor_Form', 'meta');
		$p_meta->setAccessible(true);
		$p_meta->setValue($form, $meta);

		$form->setCheck('honeypots', false);
		$this->assertEquals('<form><div style="display: none;"><meta></div></form>',
            $form->getCode());
	}
}