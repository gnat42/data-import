<?php

namespace Ddeboer\DataImport\ValueConverter;

/**
 * Converts item values using a callback
 *
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class CallbackValueConverter implements ValueConverterInterface
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
