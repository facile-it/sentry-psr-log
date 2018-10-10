<?php

namespace Facile\Sentry\LogTest;

use Facile\Sentry\Common\Sanitizer\SanitizerInterface;
use Facile\Sentry\Common\Sender\SenderInterface;
use Facile\Sentry\Log\Logger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

class LoggerTest extends \PHPUnit\Framework\TestCase
{
    public function testLogWithInvalidLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $raven = $this->prophesize(\Raven_Client::class);
        $logger = new Logger($raven->reveal());

        $logger->log('foo', 'message');
    }

    public function testLogWithInvalidObject(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $raven = $this->prophesize(\Raven_Client::class);
        $logger = new Logger($raven->reveal());

        $logger->log(LogLevel::ALERT, new \stdClass());
    }

    public function testLogWithObject(): void
    {
        $raven = $this->prophesize(\Raven_Client::class);
        $sender = $this->prophesize(SenderInterface::class);
        $sanitizer = $this->prophesize(SanitizerInterface::class);
        $logger = new Logger(
            $raven->reveal(),
            $sender->reveal(),
            $sanitizer->reveal()
        );

        $object = new class() {
            public function __toString(): string
            {
                return 'object string';
            }
        };

        $context = [
            'foo' => 'name',
        ];

        $sanitizer->sanitize($context)->shouldBeCalled()->willReturn($context);
        $sender->send(\Raven_Client::ERROR, 'object string', $context)
            ->shouldBeCalled();

        $logger->log(LogLevel::ALERT, $object, $context);
    }

    public function testLog(): void
    {
        $raven = $this->prophesize(\Raven_Client::class);
        $sender = $this->prophesize(SenderInterface::class);
        $sanitizer = $this->prophesize(SanitizerInterface::class);
        $logger = new Logger(
            $raven->reveal(),
            $sender->reveal(),
            $sanitizer->reveal()
        );

        $context = [
            'foo' => 'name',
            'placeholder' => 'value',
        ];

        $sanitizer->sanitize($context)->shouldBeCalled()->willReturn($context);
        $sender->send(\Raven_Client::ERROR, 'message value', $context)
            ->shouldBeCalled();

        $logger->log(LogLevel::ALERT, 'message {placeholder}', $context);
    }

    public function testLogWithArrayPlaceholder(): void
    {
        $raven = $this->prophesize(\Raven_Client::class);
        $sender = $this->prophesize(SenderInterface::class);
        $sanitizer = $this->prophesize(SanitizerInterface::class);
        $logger = new Logger(
            $raven->reveal(),
            $sender->reveal(),
            $sanitizer->reveal()
        );

        $context = [
            'foo' => 'name',
            'placeholder' => [
                'foo' => 'bar',
            ],
        ];

        $sanitizer->sanitize($context)->shouldBeCalled()->willReturn($context);
        $sender->send(\Raven_Client::ERROR, 'message {placeholder}', $context)
            ->shouldBeCalled();

        $logger->log(LogLevel::ALERT, 'message {placeholder}', $context);
    }
}
