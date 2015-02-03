<?php

namespace Ddeboer\DataImport\ItemConverter;

/**
 * Converts items using a callback
 *
 * @author Miguel Ibero <miguel@ibero.me>
 */
class CallbackItemConverter implements ItemConverterInterface
{
    /**
     * @var callable
     */
    private $callback;

    /**
     * Constructor
     *
     * @param callable $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * {@inheritDoc}
     */
    public function convert($input)
    {
        return call_user_func($this->callback, $input);
    }
}
