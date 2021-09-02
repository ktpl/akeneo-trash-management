<?php

namespace KTPL\AkeneoTrashBundle\Remover;

use Akeneo\Tool\Component\StorageUtils\Event\RemoveEvent;
use Akeneo\Tool\Component\StorageUtils\Remover\BulkRemoverInterface;
use Akeneo\Tool\Component\StorageUtils\Remover\RemoverInterface;
use Akeneo\Tool\Component\StorageUtils\StorageEvents;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Util\ClassUtils;
use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Base permanent remover
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class BasePermanentRemover implements RemoverInterface, BulkRemoverInterface
{
    /** @var ObjectManager */
    protected $objectManager;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var string */
    protected $removedClass;

    /** @var AkeneoTrashManager */
    protected $akeneoTrashManager;

    /**
     * @param ObjectManager             $objectManager
     * @param EventDispatcherInterface  $eventDispatcher
     * @param string                    $removedClass
     * @param AkeneoTrashManager        $akeneoTrashManager
     */
    public function __construct(
        ObjectManager $objectManager,
        EventDispatcherInterface $eventDispatcher,
        $removedClass,
        AkeneoTrashManager $akeneoTrashManager
    ) {
        $this->objectManager = $objectManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->removedClass = $removedClass;
        $this->akeneoTrashManager = $akeneoTrashManager;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($object, array $options = [])
    {
        $this->validateObject($object);
        $options['unitary'] = true;
        $this->akeneoTrashManager->removeItemFromTrash($object);
        
        $objectId = $object->getId();
        $this->eventDispatcher->dispatch(StorageEvents::PRE_REMOVE, new RemoveEvent($object, $objectId, $options));

        $this->objectManager->remove($object);
        $this->objectManager->flush();

        $this->eventDispatcher->dispatch(StorageEvents::POST_REMOVE, new RemoveEvent($object, $objectId, $options));
    }

    /**
     * {@inheritdoc}
     */
    public function removeAll(array $objects, array $options = [])
    {
        if (empty($objects)) {
            return;
        }

        $options['unitary'] = false;

        $this->eventDispatcher->dispatch(StorageEvents::PRE_REMOVE_ALL, new RemoveEvent($objects, null));

        $removedObjects = [];
        foreach ($objects as $object) {
            $this->validateObject($object);
            $this->akeneoTrashManager->removeItemFromTrash($object);
        
            $removedObjects[$object->getId()] = $object;

            $this->eventDispatcher->dispatch(StorageEvents::PRE_REMOVE, new RemoveEvent($object, $object->getId(), $options));

            $this->objectManager->remove($object);
        }

        $this->objectManager->flush();

        foreach ($removedObjects as $id => $object) {
            $this->eventDispatcher->dispatch(StorageEvents::POST_REMOVE, new RemoveEvent($object, $id, $options));
        }

        $this->eventDispatcher->dispatch(
            StorageEvents::POST_REMOVE_ALL,
            new RemoveEvent($objects, array_keys($removedObjects))
        );
    }

    /**
     * @param $object
     */
    protected function validateObject($object)
    {
        if (!$object instanceof $this->removedClass) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Expects a "%s", "%s" provided.',
                    $this->removedClass,
                    ClassUtils::getClass($object)
                )
            );
        }
    }
}
