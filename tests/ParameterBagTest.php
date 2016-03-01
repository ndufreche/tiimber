<?php
namespace KissPHP\Tests;

use KissPHP\ParameterBag;

use PHPUnit_Framework_TestCase;

class ParameterBagTest extends PHPUnit_Framework_TestCase
{
  private $object;

  public function __construct()
  {
    $this->object = new ParameterBag(['foo' => 'bar']);
  }

  public function testGet()
  {
    $this->assertEquals('bar', $this->object->get('foo'));
    $this->assertNull($this->object->get('baz'));
    $this->assertTrue($this->object->get('baz', true));
  }

  public function testSet()
  {
    $this->object->set('baz', 'faz');
    $this->assertEquals('faz', $this->object->get('baz'));
    $this->object->set('foo', 'faz');
    $this->assertEquals('faz', $this->object->get('foo'));
  }

  public function testIterator()
  {
    $count = 0;
    $this->object->set('baz', 'faz');
    $this->object->set('foo', 'faz');

    foreach ($this->object as $key => $value) {
      $this->assertTrue(in_array($key, ['foo', 'baz']));
      $this->assertEquals('faz', $value);
      $count++;
    }

    $this->assertEquals(2, $count);
  }
}