<?php

namespace Facile\Sentry\Log;

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

    /**
     * @var Raven_Client
     */
    protected $ravenClient;
    /**
     * @var array
     */
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

    const DEBUG = 'debug';
    const INFO = 'info';
    const WARN = 'warning';
    const WARNING = 'warning';
    const ERROR = 'error';
    const FATAL = 'fatal';

    /**
     * Logger constructor.
     *
     * @param Raven_Client $ravenClient
     */
    public function __construct(Raven_Client $ravenClient)
    {
        $this->setRavenClient($ravenClient);
    }

    /**
     * @return Raven_Client
     */
    public function getRavenClient()
    {
        return $this->ravenClient;
    }

    /**
     * @param Raven_Client $ravenClient
     *
     * @return $this
     */
    public function setRavenClient(Raven_Client $ravenClient)
    {
        $this->ravenClient = $ravenClient;

        return $this;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @throws InvalidArgumentException
     */
    public function log($level, $message, array $context = [])
    {
        if (!array_key_exists($level, $this->psrPriorityMap)) {
            throw new InvalidArgumentException(sprintf(
                '$level must be one of PSR-3 log levels; received %s',
                var_export($level, 1)
            ));
        }

        if (is_object($message) && !method_exists($message, '__toString')) {
            throw new InvalidArgumentException(
                '$message must implement magic __toString() method'
            );
        }

        $priority = $this->psrPriorityMap[$level];

        if ($this->objectIsThrowable($message)) {
            /* @var \Throwable $message */
            $this->getRavenClient()->captureException(
                $message,
                [
                    'extra' => $this->sanitizeContextData($context),
                    'level' => $priority,
                ]
            );

            return;
        }

        $message = (string) $message;

        if ($this->contextContainsException($context)) {
            /** @var \Throwable $exception */
            $exception = $context['exception'];
            unset($context['exception']);

            $message = $this->interpolate($message, $context);

            $exception = new ContextException($message, $exception->getCode(), $exception);

            $this->getRavenClient()->captureException(
                $exception,
                [
                    'extra' => $this->sanitizeContextData($context),
                    'level' => $priority,
                ]
            );

            return;
        }

        $this->getRavenClient()->captureMessage(
            $this->interpolate($message, $context),
            ['extra' => $this->sanitizeContextData($context)],
            $priority
        );
    }

    /**
     * @param array $context
     *
     * @return array
     */
    protected function sanitizeContextData(array $context)
    {
        array_walk_recursive($context, [$this, 'sanitizeContextItem']);

        return $context;
    }

    /**
     * @param mixed $value
     */
    protected function sanitizeContextItem(&$value)
    {
        if ($value instanceof \Traversable) {
            $value = $this->sanitizeContextData(iterator_to_array($value));
        }
        if (is_object($value)) {
            $value = method_exists($value, '__toString') ? (string) $value : get_class($value);
        } elseif (is_resource($value)) {
            $value = get_resource_type($value);
        }
    }

    /**
     * @param mixed $object
     *
     * @return bool
     */
    protected function objectIsThrowable($object)
    {
        return $object instanceof \Throwable || $object instanceof \Exception;
    }

    /**
     * @param array $context
     *
     * @return bool
     */
    protected function contextContainsException(array $context)
    {
        if (!array_key_exists('exception', $context)) {
            return false;
        }

        return $this->objectIsThrowable($context['exception']);
    }

    /**
     * @param string $message
     * @param array  $context
     *
     * @return string
     */
    protected function interpolate($message, array $context = [])
    {
        $replace = [];
        $context = $this->sanitizeContextData($context);
        foreach ($context as $key => $val) {
            if (is_array($val)) {
                continue;
            }
            $replace['{'.$key.'}'] = (string) $val;
        }

        return strtr($message, $replace);
    }
}
