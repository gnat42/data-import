<?php

namespace Ddeboer\DataImport\Step;

use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class MappingStep implements StepInterface
{
    protected $mappings;

    public function __construct(array $mappings = [], PropertyAccessor $accessor = null)
    {
        $this->mappings = $mappings;
        $this->accessor = $accessor ?: new PropertyAccessor();
    }

    public function map($from, $to)
    {
        $this->mappings[$from] = $to;

        return $this;
    }

    public function process(&$item)
    {
        foreach ($this->mappings as $from => $to) {
            $value = $this->accessor->getValue($item, $from);
            $this->accessor->setValue($item, $to, $value);
        }
    }
} 