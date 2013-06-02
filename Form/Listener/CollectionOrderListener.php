<?php

/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\DoctrinePHPCRAdminBundle\Form\Listener;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * A listener for the parent form object to reorder a children collection based
 * on the order in the form request, which reflects the frontend order. Just
 * setting the right order will make PHPCR-ODM persist the reorderings.
 *
 * @author David Buchmann <david@liip.ch>
 */
class CollectionOrderListener
{
    private $name;

    /**
     * @param string $name the form field name used for the collection
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Reorder the children at $this->name
     *
     * @param FormEvent $event
     */
    public function onPostBind(FormEvent $event)
    {
        $data = $event->getData();
        if (! is_object($data)) {
            return;
        }
        $accessor = new PropertyAccessor();
        $newCollection = $accessor->getValue($data, $this->name);
        if (! $newCollection instanceof Collection) {
            return;
        } else if ( $newCollection instanceof PersistentCollection) {
            // Only clear a PersistentCollection if orphanRemoval is false
            $mapping = $newCollection->getMapping();
            if (!empty($mapping['orphanRemoval']) && !$mapping['orphanRemoval']) {
                $newCollection->clear();
            }
        } else {
            $newCollection->clear();
        }
        
        /** @var $newCollection Collection */
        /** @var $item FormBuilder */
        foreach ($event->getForm()->get($this->name) as $item) {
            if ($item->get('_delete')->getData()) {
                // do not re-add a deleted child and remove the item from the collection, 
                $newCollection->removeElement($item->getData());
                continue;
            }

            // Don't add the element if its already in there
            if (!$newCollection->contains($item->getData())) {
                if ($item->getName()) {
                    // keep key in collection
                    $newCollection[$item->getName()] = $item->getData();
                } else {
                    $newCollection[] = $item->getData();
                }
            }
        }
    }
}
