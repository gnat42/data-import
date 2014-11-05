<?php

namespace Ddeboer\DataImport\Step;
use Ddeboer\DataImport\Writer\WriterInterface;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class WriterStep implements PriorityStepInterface
{
    private $writers;

    public function __construct(array $writers = [])
    {
        $this->writers = new \SplObjectStorage();
    }

    public function add(WriterInterface $writer)
    {
        $this->writers->attach($writer);
    }

    public function clear()
    {
        $this->writers = new \SplObjectStorage();
    }

    public function process(&$item)
    {
        foreach ($this->writers as $writer) {
            $writer->writeItem($item);
        }

        return true;
    }

    public function getPriority()
    {
        return -128;
    }
} 