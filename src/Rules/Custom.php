<?php
declare(strict_types=1);

namespace Respect\Validation\Rules;

use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validatable;

final class Custom extends AbstractRule
{
    private $callback;
    private $arguments;

    public function __construct(callable $callback, ...$arguments)
    {
        $this->callback = $callback;
        $this->arguments = $arguments;
    }

    /**
     * {@inheritDoc}
     */
    public function validate($input): bool
    {
        return $this->_getResult($input) === true;
    }

    public function assert($input): void
    {
        $result = $this->_getResult($input);
        if ($result === true) {
            return;
        }

        if ($result instanceof ValidationException) {
            $this->setTemplate(
                $result instanceof NestedValidationException
                    ? $result->getFullMessage()
                    : $result->getMessage(),
            );
            throw $result;
        }

        $extraParams = [];
        if (is_string($result) || is_array($result)) {
            $result = (array) $result;
            $extraParams = array_slice($result, 1);
            $this->setTemplate($result[0]);
        }

        throw $this->reportError($input, $extraParams);
    }

    // ------------------------------------------------------
    // -
    // -    Internal methods
    // -
    // ------------------------------------------------------

    protected function _getResult($input)
    {
        $arguments = $this->arguments;
        array_unshift($arguments, $input);

        try {
            $result = call_user_func_array($this->callback, $arguments);
        } catch (ValidationException $e) {
            $result = $e;
        }

        if ($result instanceof Validatable) {
            try {
                if ($this->name !== null) {
                    $result->setName($this->name);
                }
                $result->assert($input);
                return true;
            } catch (ValidationException $e) {
                $result = $e;
            }
        }

        if ($result instanceof ValidationException) {
            if ($this->name !== null) {
                $result->setName($this->name);
            }
            return $result;
        }

        if (is_bool($result) || is_string($result)) {
            return $result;
        }

        if (is_array($result) && is_string($result[0] ?? null)) {
            return $result;
        }

        throw new \LogicException("Invalid return from custom validator.");
    }
}
