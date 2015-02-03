<?php

namespace Ddeboer\DataImport;

use Ddeboer\DataImport\Exception\ExceptionInterface;
use Ddeboer\DataImport\Reader\ReaderInterface;
use Ddeboer\DataImport\Step\PriorityStepInterface;
use Ddeboer\DataImport\Step\StepInterface;
use Ddeboer\DataImport\Writer\WriterInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * A mediator between a reader and one or more writers and converters
 *
 * @author David de Boer <david@ddeboer.nl>
 */
final class Workflow implements WorkflowInterface
{
    /**
     * Reader
     *
     * @var ReaderInterface
     */
    private $reader;

    /**
     * Identifier for the Import/Export
     *
     * @var string|null
     */
    private $name = null;

    /**
     * @var boolean
     */
    private $skipItemOnFailure = false;

    /**
     * @var \Psr\Log\NullLogger
     */
    private $logger;

    /**
     * @var \SplPriorityQueue
     */
    private $steps;

    /**
     * @var WriterInterface[]
     */
    private $writers = [];

    /**
     * Construct a workflow
     *
     * @param ReaderInterface $reader
     * @param string $name
     */
    public function __construct(ReaderInterface $reader, LoggerInterface $logger = null, $name = null)
    {
        $this->name = $name;
        $this->logger = $logger ?: new NullLogger();
        $this->reader = $reader;
        $this->steps = new \SplPriorityQueue();
    }

    public function addStep(StepInterface $step, $priority = null)
    {
        $priority = null === $priority && $step instanceof PriorityStepInterface ? $step->getPriority() : null;
        $priority = null === $priority ? 0 : $priority;

        $this->steps->insert($step, $priority);

        return $this;
    }

    public function addWriter(WriterInterface $writer)
    {
        array_push($this->writers, $writer);

        return $this;
    }

    /**
     * Process the whole import workflow
     *
     * 1. Prepare the added writers.
     * 2. Ask the reader for one item at a time.
     * 3. Filter each item.
     * 4. If the filter succeeds, convert the item’s values using the added
     *    converters.
     * 5. Write the item to each of the writers.
     *
     * @throws ExceptionInterface
     *
     * @return Result Object Containing Workflow Results
     */
    public function process()
    {
        $count      = 0;
        $exceptions = new \SplObjectStorage();
        $startTime  = new \DateTime;
        $steps      = clone $this->steps;

        foreach ($this->writers as $writer) {
            $writer->prepare();
        }

        // Read all items
        foreach ($this->reader as $item) {
            try {
                foreach ($steps as $step) {
                    if (!$step->process($item)) {
                        continue;
                    }
                }

                foreach ($this->writers as $writer) {
                    $writer->writeItem($item);
                }
            } catch(ExceptionInterface $e) {
                if (!$this->skipItemOnFailure) {
                    throw $e;
                }

                $exceptions->attach($e);
                $this->logger->error($e->getMessage());
            }

            $count++;
        }

        foreach ($this->writers as $writer) {
            $writer->finish();
        }

        return new Result($this->name, $startTime, new \DateTime, $count, $exceptions);
    }

    /**
     * Set skipItemOnFailure.
     *
     * @param boolean $skipItemOnFailure then true skip current item on process exception and log the error
     *
     * @return $this
     */
    public function setSkipItemOnFailure($skipItemOnFailure)
    {
        $this->skipItemOnFailure = $skipItemOnFailure;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }
}
