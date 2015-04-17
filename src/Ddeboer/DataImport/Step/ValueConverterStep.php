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
        $accessor = new \Symfony\Component\PropertyAccess\PropertyAccessor();
        foreach ($this->converters as $property => $converters) {
            foreach ($converters as $converter) {
                $orgValue = $accessor->getValue($item, $property);
                $value = $converter->convert($orgValue);
                $accessor->setValue($item,$property,$value);
            }
        }

        return true;
    }
} 