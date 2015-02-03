<?php

namespace Ddeboer\DataImport\Step;
use Ddeboer\DataImport\Filter\FilterInterface;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class FilterStep implements StepInterface
{
    /**
     * @var \SplObjectStorage
     */
    private $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = new \SplObjectStorage($filters);
    }

    public function add(FilterInterface $filter)
    {
        $this->filters->attach($filter);

        return $this;
    }

    public function process(&$item)
    {
        foreach ($this->filters as $filter) {
            if (false === $filter->filter($item)) {
                return false;
            }
        }

        return true;
    }
} 