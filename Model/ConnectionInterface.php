<?php

namespace Kitano\ConnectionBundle\Model;

interface ConnectionInterface
{
    /**
     * Returns the Node from where the Connection (edge) start
     *
     * @return \Kitano\ConnectionBundle\Model\NodeInterface
     */
    public function getSource();

    /**
     * Sets the Node from where the Connection start
     *
     * @param NodeInterface $node
     */
    public function setSource(NodeInterface $node);

    /**
     * Returns the Node to which the Connection is directed
     *
     * @return \Kitano\ConnectionBundle\Model\NodeInterface
     */
    public function getDestination();

    /**
     * Sets the Node to which the Connection is directed
     *
     * @param NodeInterface $node
     */
    public function setDestination(NodeInterface $node);

    /**
     * Returns the "type" of this connection (user defined)
     * This type is used to identify the kind of connection which is linking a Node
     * to another (i.e: "like", "follow", ...)
     *
     * @return mixed
     */
    public function getType();

    /**
     * Sets the connection type
     *
     * @param string $type
     */
    public function setType($type);

    /**
     * Gets the number of nodes (distance) between the source and destination nodes
     *
     * @return integer
     */
    public function getDistance();

    /**
     * Sets the number of nodes (distance) between source and destination nodes
     *
     * @param integer $distance
     */
    public function setDistance($distance);

    /**
     * Get id's of all nodes between source and destination nodes
     *
     * @return array[]int
     */
    public function getLinkerNodes();

    /**
     * Set id's of all nodes between source and destination nodes
     *
     * @param array[]int $linkerNodes
     */
    public function setLinkerNodes(array $linkerNodes);

    /**
     * Add a node between source and destination nodes
     *
     * @param \Kitano\ConnectionBundle\Model\NodeInterface $node
     */
    public function addLinkerNode(NodeInterface $node);

    /**
     * Returns the date the connection was initially created at
     *
     * @return \DateTime
     */
    public function getCreatedAt();

    /**
     * Set the date the connection was initially created at
     *
     * @param \DateTime $createdAt
     */
    public function setCreatedAt(\DateTime $createdAt);
}
