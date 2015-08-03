<?php

namespace Ddeboer\DataImport\Writer;

use Ddeboer\DataImport\Writer;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * A bulk Doctrine writer
 *
 * See also the {@link http://www.doctrine-project.org/docs/orm/2.1/en/reference/batch-processing.html Doctrine documentation}
 * on batch processing.
 *
 * @author David de Boer <david@ddeboer.nl>
 */
class DoctrineWriter implements Writer, FlushableWriter
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var string
     */
    protected $entityName;

    /**
     * @var EntityRepository
     */
    protected $entityRepository;

    /**
     * @var string
     */
    protected $entityRepositoryMethod;

    /**
     * @var ClassMetadata
     */
    protected $entityMetadata;

    /**
     * Original Doctrine logger
     *
     * @var SQLLogger
     */
    protected $originalLogger;

    /**
     * Whether to truncate the table first
     *
     * @var boolean
     */
    protected $truncate = true;

    /**
     * List of fields used to lookup an entity
     *
     * @var array
     */
    protected $lookupFields = array();

    /**
     * @param EntityManagerInterface $entityManager
     * @param string                 $entityName
     * @param string|array           $index Field or fields to find current entities by
     */
    public function __construct(EntityManagerInterface $entityManager, $entityName, $index = null)
    {
        $this->entityManager = $entityManager;
        $this->entityRepository = $entityManager->getRepository($entityName);
        $this->entityMetadata = $entityManager->getClassMetadata($entityName);
        //translate entityName in case a namespace alias is used
        $this->entityName = $this->entityMetadata->getName();
        if ($index) {
            if (is_array($index)) {
                $this->lookupFields = $index;
            } else {
                $this->lookupFields = [$index];
            }
        }
    }

    /**
     * @return boolean
     */
    public function getTruncate()
    {
        return $this->truncate;
    }

    /**
     * Set whether to truncate the table first
     *
     * @param boolean $truncate
     *
     * @return $this
     */
    public function setTruncate($truncate)
    {
        $this->truncate = $truncate;

        return $this;
    }

    /**
     * Disable truncation
     *
     * @return $this
     */
    public function disableTruncate()
    {
        $this->truncate = false;

        return $this;
    }

    /**
     * Disable Doctrine logging
     *
     * @return $this
     */
    public function prepare()
    {
        $this->disableLogging();

        if (true === $this->truncate) {
            $this->truncateTable();
        }
    }

    /**
     * Return a new instance of the entity
     *
     * @throws \Exception
     * @return object
     */
    protected function getNewInstance()
    {
        if (class_exists($this->entityName) === false) {
            throw new \Exception('Unable to create new instance of ' . $this->entityName);
        }

        return new $this->entityName;
    }

    /**
     * Call a setter of the entity
     *
     * @param object $entity
     * @param mixed  $value
     * @param string $setter
     */
    protected function setValue($entity, $value, $setter)
    {
        if (method_exists($entity, $setter)) {
            $entity->$setter($value);
        }
    }

    /**
     * Re-enable Doctrine logging
     *
     * @return $this
     */
    public function finish()
    {
        $this->flush();
        $this->reEnableLogging();
    }

    /**
     * {@inheritdoc}
     */
    public function writeItem(array $item)
    {
        $entity = $this->findOrCreateItem($item);

        $this->updateAssociations($item,$entity);
        $this->loadAssociationObjectsToEntity($item, $entity);
        $this->updateEntity($item, $entity);

        $this->entityManager->persist($entity);
    }

    /**
     * @param array  $item
     * @param object $entity
     */
    protected function updateEntity(array $item, $entity)
    {
        $fieldNames = array_merge($this->entityMetadata->getFieldNames(), $this->entityMetadata->getAssociationNames());
        foreach ($fieldNames as $fieldName) {
            $value = null;
            if (isset($item[$fieldName])) {
                $value = $item[$fieldName];
            }

            if (null === $value) {
                continue;
            }

            if (!($value instanceof \DateTime)
                || $value != $this->entityMetadata->getFieldValue($entity, $fieldName)
            ) {
                $setter = 'set' . ucfirst($fieldName);
                $this->setValue($entity, $value, $setter);
            }
        }
    }

    /**
     * @param array $item
     * @param $entity
     * @throws \Exception
     */
    public function updateAssociations(array &$item, $entity)
    {
        foreach ($this->entityMetadata->getAssociationMappings() as $map) {
            // There is data for the association
            if (isset($item[$map['fieldName']]) && is_array($item[$map['fieldName']])) {
                $this->updateAssociation($item, $entity, $map['fieldName'], $map['targetEntity']);
            }
        }
    }

    /**
     * @param array $item
     * @param object $entity
     * @param string $fieldName
     * @param string $targetEntity
     * @throws \Exception
     */
    public function updateAssociation(array &$item, $entity, $fieldName, $targetEntity)
    {
        $getterMethod = sprintf('get%s', $fieldName);

        if (method_exists($entity, $getterMethod)) {
            $orgRepository = $this->entityRepository;
            $orgMetadata = $this->entityMetadata;
            $orgName = $this->entityName;

            $this->entityRepository = $this->entityManager->getRepository($targetEntity);
            $this->entityMetadata = $this->entityManager->getClassMetadata($targetEntity);
            $this->entityName = $this->entityMetadata->getName();

            $association = call_user_func([$entity,$getterMethod]);
            if (!$association) {
                $association = $this->getNewInstance();
            }

            $value = $item[$fieldName];
            unset($item[$fieldName]);

            $this->updateEntity($value, $association);

            $setterMethod = sprintf('set%s',$fieldName);
            call_user_func([$entity, $setterMethod],$association);

            $this->entityRepository = $orgRepository;
            $this->entityMetadata = $orgMetadata;
            $this->entityName = $orgName;
        }
    }

    /**
     * Add the associated objects in case the item have for persist its relation
     *
     * @param array  $item
     * @param object $entity
     */
    protected function loadAssociationObjectsToEntity(array $item, $entity)
    {
        foreach ($this->entityMetadata->getAssociationMappings() as $associationMapping) {

            $value = null;
            if (isset($item[$associationMapping['fieldName']]) && !is_object($item[$associationMapping['fieldName']]) && !is_array($item[$associationMapping['fieldName']])) {
                $value = $this->entityManager->getReference($associationMapping['targetEntity'], $item[$associationMapping['fieldName']]);
            }

            if (null === $value) {
                continue;
            }

            $setter = 'set' . ucfirst($associationMapping['fieldName']);
            $this->setValue($entity, $value, $setter);
        }
    }

    /**
     * Truncate the database table for this writer
     */
    protected function truncateTable()
    {
        $tableName = $this->entityMetadata->table['name'];
        $connection = $this->entityManager->getConnection();
        $query = $connection->getDatabasePlatform()->getTruncateTableSQL($tableName, true);
        $connection->executeQuery($query);
    }

    /**
     * Disable Doctrine logging
     */
    protected function disableLogging()
    {
        $config = $this->entityManager->getConnection()->getConfiguration();
        $this->originalLogger = $config->getSQLLogger();
        $config->setSQLLogger(null);
    }

    /**
     * Re-enable Doctrine logging
     */
    protected function reEnableLogging()
    {
        $config = $this->entityManager->getConnection()->getConfiguration();
        $config->setSQLLogger($this->originalLogger);
    }

    /**
     * Finds existing entity or create a new instance
     *
     * @param array $item
     * @return mixed|null|object
     */
    protected function findOrCreateItem(array $item)
    {
        $entity = null;
        // If the table was not truncated to begin with, find current entity
        // first
        if (false === $this->truncate) {
            if (!empty($this->lookupFields)) {
                $lookupConditions = array();
                foreach ($this->lookupFields as $fieldName) {
                    $lookupConditions[$fieldName] = $item[$fieldName];
                }

                if ($this->entityRepositoryMethod && method_exists($this->entityRepository, $this->entityRepositoryMethod)) {
                    $entity = call_user_func([$this->entityRepository, $this->entityRepositoryMethod], $lookupConditions);
                } else {
                    $entity = $this->entityRepository->findOneBy(
                        $lookupConditions
                    );
                }
            } else {
                $entity = $this->entityRepository->find(current($item));
            }
        }

        if (!$entity) {
            return $this->getNewInstance();
        }

        return $entity;
    }

    /**
     * Flush and clear the entity manager
     */
    public function flush()
    {
        $this->entityManager->flush();
        $this->entityManager->clear($this->entityName);
    }

    /**
     * @param string $entityRepositoryMethod
     * @return DoctrineWriter
     */
    public function setEntityRepositoryMethod($entityRepositoryMethod)
    {
        $this->entityRepositoryMethod = $entityRepositoryMethod;
        return $this;
    }
}
