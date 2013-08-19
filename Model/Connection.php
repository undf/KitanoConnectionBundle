<?php

namespace Kitano\ConnectionBundle\Model;

class Connection implements ConnectionInterface
{

    /**
     * @var object
     */
    protected $source;

    /**
     * @var object
     */
    protected $destination;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var integer
     */
    protected $distance;

    /**
     * @var string
     */
    protected $linkerNodes = '';

    /**
     * @var \DateTime
     */
    protected $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * {@inheritdoc}
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * {@inheritdoc}
     */
    public function setSource(NodeInterface $source)
    {
        $this->source = $source;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDestination()
    {
        return $this->destination;
    }

    /**
     * {@inheritdoc}
     */
    public function setDestination(NodeInterface $destination)
    {
        $this->destination = $destination;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDistance()
    {
        return $this->distance;
    }

    /**
     * {@inheritdoc}
     */
    public function setDistance($distance)
    {
        $this->distance = $distance;
    }

    /**
     * {@inheritdoc}
     */
    public function getLinkerNodes()
    {
        return explode(':', trim($this->linkerNodes, ':'));
    }

    /**
     * {@inheritdoc}
     */
    public function setLinkerNodes(array $linkerNodes)
    {
        $this->linkerNodes = ':'. trim(implode(':', $linkerNodes), ':'). ':';
    }

    /**
     * {@inheritdoc}
     */
    public function addLinkerNode(NodeInterface $node)
    {
        $this->linkerNodes. ':'. $node->getId() . ':';
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * {@inheritdoc}
     */
    public function setCreatedAt(\DateTime $value)
    {
        $this->createdAt = $value;

        return $this;
    }

}
