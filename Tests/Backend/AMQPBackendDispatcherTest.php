<?php

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\NotificationBundle\Tests\Backend;

use PHPUnit\Framework\TestCase;
use Sonata\NotificationBundle\Backend\AMQPBackendDispatcher;

class AMQPBackendDispatcherTest extends TestCase
{
    protected function setUp()
    {
        if (!class_exists('PhpAmqpLib\Message\AMQPMessage')) {
            $this->markTestSkipped('AMQP Lib not installed');
        }
    }

    public function testQueue()
    {
        $mock = $this->getMockQueue('foo', 'message.type.foo', $this->once());
        $mock2 = $this->getMockQueue('bar', 'message.type.foo', $this->never());
        $fooBackend = ['type' => 'message.type.foo', 'backend' => $mock];
        $barBackend = ['type' => 'message.type.bar', 'backend' => $mock2];
        $backends = [$fooBackend, $barBackend];
        $dispatcher = $this->getDispatcher($backends);
        $dispatcher->createAndPublish('message.type.foo', []);
    }

    public function testDefaultQueue()
    {
        $mock = $this->getMockQueue('foo', 'message.type.foo', $this->once());
        $fooBackend = ['type' => 'default', 'backend' => $mock];
        $dispatcher = $this->getDispatcher([$fooBackend]);
        $dispatcher->createAndPublish('some.other.type', []);
    }

    public function testDefaultQueueNotFound()
    {
        $mock = $this->getMockQueue('foo', 'message.type.foo', $this->never());
        $fooBackend = ['type' => 'message.type.foo', 'backend' => $mock];
        $dispatcher = $this->getDispatcher([$fooBackend]);

        $this->setExpectedException('\Sonata\NotificationBundle\Exception\BackendNotFoundException');
        $dispatcher->createAndPublish('some.other.type', []);
    }

    public function testInvalidQueue()
    {
        $mock = $this->getMockQueue('foo', 'message.type.bar');
        $dispatcher = $this->getDispatcher(
            [['type' => 'bar', 'backend' => $mock]],
            [['queue' => 'foo', 'routing_key' => 'message.type.bar']]
        );

        $this->setExpectedException('\Sonata\NotificationBundle\Exception\BackendNotFoundException');
        $dispatcher->createAndPublish('message.type.bar', []);
    }

    public function testAllQueueInitializeOnce()
    {
        $queues = [
            ['queue' => 'foo', 'routing_key' => 'message.type.foo'],
            ['queue' => 'bar', 'routing_key' => 'message.type.bar'],
            ['queue' => 'baz', 'routing_key' => 'message.type.baz'],
        ];

        $backends = [];

        foreach ($queues as $queue) {
            $mock = $this->getMockQueue($queue['queue'], $queue['routing_key']);
            $mock->expects($this->once())
                ->method('initialize');
            $backends[] = ['type' => $queue['routing_key'], 'backend' => $mock];
        }

        $dispatcher = $this->getDispatcher($backends, $queues);

        $dispatcher->createAndPublish('message.type.foo', []);
        $dispatcher->createAndPublish('message.type.foo', []);
    }

    protected function getMockQueue($queue, $type, $called = null)
    {
        $methods = ['createAndPublish', 'initialize'];
        $args = ['', 'foo', false, 'message.type.foo'];
        $mock = $this->getMockBuilder('Sonata\NotificationBundle\Backend\AMQPBackend')
                     ->setConstructorArgs($args)
                     ->setMethods($methods)
                     ->getMock();

        if (null !== $called) {
            $mock->expects($called)
                ->method('createAndPublish')
            ;
        }

        return $mock;
    }

    protected function getDispatcher(array $backends, array $queues = [['queue' => 'foo', 'routing_key' => 'message.type.foo']])
    {
        $settings = [
                'host' => 'foo',
                'port' => 'port',
                'user' => 'user',
                'pass' => 'pass',
                'vhost' => '/',
        ];

        return new AMQPBackendDispatcher($settings, $queues, 'default', $backends);
    }
}
