<?php

namespace Facile\Sentry\LogTest;

use Facile\Sentry\Log\ContextException;
use Facile\Sentry\Log\Logger;
use Prophecy\Argument;
use Psr\Log\LogLevel;

class LoggerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Psr\Log\InvalidArgumentException
     */
    public function testLogWithInvalidLevel()
    {
        $raven = $this->prophesize(\Raven_Client::class);
        $logger = new Logger($raven->reveal());

        $logger->log('foo', 'message');
    }

    /**
     * @expectedException \Psr\Log\InvalidArgumentException
     */
    public function testLogWithInvalidObject()
    {
        $raven = $this->prophesize(\Raven_Client::class);
        $logger = new Logger($raven->reveal());

        $logger->log(LogLevel::ALERT, new \stdClass());
    }

    public function testLogWithException()
    {
        $raven = $this->prophesize(\Raven_Client::class);
        $logger = new Logger($raven->reveal());

        $exception = $this->prophesize(\Exception::class);
        $context = [
            'foo' => 'name',
            'object' => new \stdClass(),
        ];

        $raven->captureException(
            $exception->reveal(),
            ['extra' => ['foo' => 'name', 'object' => 'stdClass'], 'level' => \Raven_Client::ERROR]
        )
            ->shouldBeCalled();

        $logger->log(LogLevel::ALERT, $exception->reveal(), $context);
    }

    public function testLogWithExceptionInContext()
    {
        $raven = $this->prophesize(\Raven_Client::class);
        $logger = new Logger($raven->reveal());

        $exception = new \RuntimeException('foo exception message', 102);
        $context = [
            'exception' => $exception,
            'foo' => 'name',
            'object' => new \stdClass(),
        ];

        $raven->captureException(
            Argument::that(function ($e) use ($exception) {
                return $e instanceof ContextException &&
                $e->getMessage() === 'foo name' &&
                $e->getCode() === 102 &&
                $e->getPrevious() === $exception;
            }),
            ['extra' => ['foo' => 'name', 'object' => 'stdClass'], 'level' => \Raven_Client::ERROR]
        )
            ->shouldBeCalled();

        $logger->log(LogLevel::ALERT, 'foo {foo}', $context);
    }

    public function testLogWithMessage()
    {
        $raven = $this->prophesize(\Raven_Client::class);
        $logger = new Logger($raven->reveal());

        $context = [
            'foo' => 'name',
            'object' => new \stdClass(),
            'resource' => tmpfile(),
            'array_object' => new \ArrayObject(['foo' => 'bar']),
        ];

        $raven->captureMessage(
            'foo',
            [
                'extra' => [
                    'foo' => 'name',
                    'object' => 'stdClass',
                    'resource' => 'stream',
                    'array_object' => ['foo' => 'bar'],
                ],
            ],
            \Raven_Client::ERROR
        )
            ->shouldBeCalled();

        $logger->log(LogLevel::ALERT, 'foo', $context);
    }
}
