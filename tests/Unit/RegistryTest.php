<?php

use framework\components\Registry;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
{
    private Registry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new Registry();
    }

    public function testSetAndGet()
    {
        $this->registry->set('name', 'John');

        $this->assertEquals('John', $this->registry->get('name'));
    }

    public function testGetReturnsDefaultWhenKeyNotFound()
    {
        $this->assertNull($this->registry->get('nonexistent'));
        $this->assertEquals('default', $this->registry->get('nonexistent', 'default'));
    }

    public function testHasReturnsTrueWhenKeyExists()
    {
        $this->registry->set('name', 'John');

        $this->assertTrue($this->registry->has('name'));
        $this->assertFalse($this->registry->has('nonexistent'));
    }

    public function testSetOverwritesExistingValue()
    {
        $this->registry->set('name', 'John');
        $this->registry->set('name', 'Jane');

        $this->assertEquals('Jane', $this->registry->get('name'));
    }

    public function testSetAcceptsMixedValues()
    {
        $this->registry->set('string', 'value');
        $this->registry->set('int', 42);
        $this->registry->set('array', ['a', 'b']);
        $this->registry->set('object', (object)['key' => 'val']);

        $this->assertEquals('value', $this->registry->get('string'));
        $this->assertEquals(42, $this->registry->get('int'));
        $this->assertEquals(['a', 'b'], $this->registry->get('array'));
        $this->assertEquals((object)['key' => 'val'], $this->registry->get('object'));
    }
}
