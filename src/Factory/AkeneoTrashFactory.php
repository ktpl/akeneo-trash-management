<?php

namespace KTPL\AkeneoTrashBundle\Factory;

use KTPL\AkeneoTrashBundle\Entity\AkeneoTrash;

/**
 * Akeneo trash factory
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class AkeneoTrashFactory
{
    /** @var string */
    protected $class;

    /**
     * @param string $class
     */
    public function __construct($class)
    {
        $this->class = $class;
    }

    /**
     * Create a version
     *
     * @return AkeneoTrash
     */
    public function create()
    {
        return new $this->class();
    }
}
