<?php

namespace Ddeboer\DataImport\Step;
use Ddeboer\DataImport\ValueConverter\ValueConverterInterface;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class ValueConverterStep implements StepInterface
{
    protected $converters = [];

    public function add($property, ValueConverterInterface $converter)
    {
        if (!isset($this->converters[$property])) {
            $this->converters[$property] = new \SplObjectStorage();
        }

        $this->converters[$property]->attach($converter);

        return $this;
    }

    public function process(&$item)
    {
        foreach ($this->converters as $property => $converters) {
            if (isset($item[$property])) {
                foreach ($converters as $converter) {
                    $item[$property] = $converter->convert($item[$property]);
                }
            }
        }

        return true;
    }
} 