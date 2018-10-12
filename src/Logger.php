<?php

namespace Facile\Sentry\Log;

use Facile\Sentry\Common\Sanitizer\Sanitizer;
use Facile\Sentry\Common\Sanitizer\SanitizerInterface;
use Facile\Sentry\Common\Sender\Sender;
use Facile\Sentry\Common\Sender\SenderInterface;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Raven_Client;

/**
 * Class Logger.
 */
class Logger implements LoggerInterface
{
    use LoggerTrait;

    /** @var Raven_Client */
    protected $client;

    /** @var SenderInterface */
    private $sender;

    /** @var SanitizerInterface */
    private $sanitizer;

    /** @var array */
    protected $psrPriorityMap = [
        LogLevel::EMERGENCY => Raven_Client::FATAL,
        LogLevel::ALERT => Raven_Client::ERROR,
        LogLevel::CRITICAL => Raven_Client::ERROR,
        LogLevel::ERROR => Raven_Client::ERROR,
        LogLevel::WARNING => Raven_Client::WARNING,
        LogLevel::NOTICE => Raven_Client::INFO,
        LogLevel::INFO => Raven_Client::INFO,
        LogLevel::DEBUG => Raven_Client::DEBUG,
    ];

    /**
     * Logger constructor.
     *
     * @param Raven_Client            $client
     * @param SenderInterface|null    $sender
     * @param SanitizerInterface|null $sanitizer
     */
    public function __construct(
        Raven_Client $client,
        SenderInterface $sender = null,
        SanitizerInterface $sanitizer = null
    ) {
        $this->client = $client;
        if (! $sender) {
            $sender = new Sender($client, $sanitizer);
            $sender->getStackTrace()->addIgnoreBacktraceNamespace(__NAMESPACE__);
        }
        $this->sender = $sender;
        $this->sanitizer = $sanitizer ?: new Sanitizer();
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param string        $level
     * @param string|object $message
     * @param array         $context
     *
     * @throws InvalidArgumentException
     *
     * @return void
     */
    public function log($level, $message, array $context = []): void
    {
        if (! \array_key_exists($level, $this->psrPriorityMap)) {
            throw new InvalidArgumentException(\sprintf(
                '$level must be one of PSR-3 log levels; received %s',
                \var_export($level, true)
            ));
        }

        if (\is_object($message) && ! \method_exists($message, '__toString')) {
            throw new InvalidArgumentException(
                '$message must implement magic __toString() method'
            );
        }

        $priority = $this->psrPriorityMap[$level];
        $message = $this->interpolate((string) $message, $context);

        $this->sender->send($priority, $message, $context);
    }

    /**
     * @param string $message
     * @param array  $context
     *
     * @return string
     */
    protected function interpolate(string $message, array $context = []): string
    {
        $replace = [];
        /** @var array $context */
        $context = $this->sanitizer->sanitize($context);
        foreach ($context as $key => $val) {
            if (\is_array($val)) {
                continue;
            }
            $replace['{' . $key . '}'] = (string) $val;
        }

        return \strtr($message, $replace);
    }
}
