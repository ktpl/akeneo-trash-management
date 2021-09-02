<?php

namespace KTPL\AkeneoTrashBundle\Entity;

class AkeneoTrash
{
    /**
    * @var int
    */
    protected $id;

    /**
     * @var string
     */
    protected $author;

    /**
     * @var string
     */
    protected $resourceName;

    /**
     * @var string
     */
    protected $resourceId;

    /**
     * @var array
     */
    protected $options;

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set id
     *
     * @param int $id
     *
     * @return AkeneoTrash
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set author
     *
     * @return AkeneoTrash
     */
    public function setAuthor($auther)
    {
        return $this->author = $auther;

        return $this;
    }

    /**
     * Get author
     *
     * @return string
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * Set resource id
     *
     * @return AkeneoTrash
     */
    public function setResourceId($resourceId)
    {
        return $this->resourceId = $resourceId;

        return $this;
    }

    /**
     * Get resource id
     *
     * @return string
     */
    public function getResourceId()
    {
        return $this->resourceId;
    }

    /**
     * Set resource name
     *
     * @return AkeneoTrash
     */
    public function setResourceName($resourceName)
    {
        return $this->resourceName = $resourceName;

        return $this;
    }

    /**
     * Get resource name
     *
     * @return string
     */
    public function getResourceName()
    {
        return $this->resourceName;
    }

    /**
     * Set resource options
     *
     * @return AkeneoTrash
     */
    public function setOptions($options)
    {
        return $this->options = $options;

        return $this;
    }

    /**
     * Get resource options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }
}
