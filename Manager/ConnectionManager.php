<?php

namespace Kitano\ConnectionBundle\Manager;

use Kitano\ConnectionBundle\Model\ConnectionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Kitano\ConnectionBundle\Repository\ConnectionRepositoryInterface;
use Kitano\ConnectionBundle\Event\ConnectionEvent;
use Kitano\ConnectionBundle\Model\NodeInterface;
use Kitano\ConnectionBundle\Manager\FilterValidator;
use Kitano\ConnectionBundle\Exception\AlreadyConnectedException;
use Kitano\ConnectionBundle\Exception\NotConnectedException;

class ConnectionManager implements ConnectionManagerInterface
{

    /**
     * @var ConnectionRepositoryInterface
     */
    protected $connectionRepository;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var FilterValidator
     */
    protected $filterValidator;

    /**
     * {@inheritDoc}
     *
     * @throws AlreadyConnectedException When connection from source to destination already exists
     */
    public function connect(NodeInterface $source, NodeInterface $destination, $type)
    {
        $connections = $this->createConnection($source, $destination, $type);
        $this->getConnectionRepository()->update($connections);

        if ($this->dispatcher) {
            $this->dispatcher->dispatch(ConnectionEvent::CONNECTED, new ConnectionEvent($connections[0]));
        }

        return $connections;
    }

    /**
     * {@inheritDoc}
     */
    public function disconnect(NodeInterface $source, NodeInterface $destination, array $filters = array())
    {
        $connections = $this->filterConnectionsForDestroy($source, $destination, $filters);

        $this->getConnectionRepository()->destroy($connections);

        if ($this->dispatcher) {
            foreach ($connections as $connection) { // hum ?
                $this->dispatcher->dispatch(ConnectionEvent::DISCONNECTED, new ConnectionEvent($connection));
            }
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function connectBulk(ConnectionCommand $command)
    {
        $connectCommands = $command->getConnectCommands();
        $connections = array();

        foreach ($connectCommands as $connectCommand) {
            $connections[] = array_merge($connections, $this->createConnection($connectCommand['source'], $connectCommand['destination'], $connectCommand['type']));
        }

        $this->getConnectionRepository()->update($connections);

        return $connections;
    }

    /**
     * {@inheritDoc}
     */
    public function disconnectBulk(ConnectionCommand $command)
    {
        $disconnectCommands = $command->getDisconnectCommands();
        $toDisconnectCollection = array();

        foreach ($disconnectCommands as $disconnectCommand) {
            $matchedConnections = $this->filterConnectionsForDestroy($disconnectCommand['source'], $disconnectCommand['destination'], $disconnectCommand['filters']);

            foreach ($matchedConnections as $c) {
                $toDisconnectCollection[] = $c;
            }
        }
        $this->getConnectionRepository()->destroy($toDisconnectCollection);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function destroy(ConnectionInterface $connection)
    {
        $this->getConnectionRepository()->destroy($connection);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function areConnected(NodeInterface $nodeA, NodeInterface $nodeB, array $filters = array())
    {
        $this->filterValidator->validateFilters($filters);

        return $this->getConnectionRepository()->areConnected($nodeA, $nodeB, $filters);
    }

    /**
     * {@inheritDoc}
     */
    public function isConnectedTo(NodeInterface $source, NodeInterface $destination, array $filters = array())
    {
        $this->filterValidator->validateFilters($filters);

        $connectionsTo = $this->getConnectionsTo($destination, $filters);

        foreach ($connectionsTo as $connectionTo) {
            if ($connectionTo->getSource() === $source) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function hasConnections(NodeInterface $node, array $filters = array())
    {
        $this->filterValidator->validateFilters($filters);

        return count($this->getConnections($node, $filters)) > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function getConnectionsTo(NodeInterface $node, array $filters = array())
    {
        $this->filterValidator->validateFilters($filters);

        return $this->getConnectionRepository()->getConnectionsWithDestination($node, $filters);
    }

    /**
     * {@inheritDoc}
     */
    public function getConnectionsFrom(NodeInterface $node, array $filters = array())
    {
        $this->filterValidator->validateFilters($filters);

        return $this->getConnectionRepository()->getConnectionsWithSource($node, $filters);
    }

    /**
     * {@inheritDoc}
     */
    public function getConnections(NodeInterface $node, array $filters = array(), $includeIndirectConnections = false)
    {
        $this->filterValidator->validateFilters($filters);

        return $this->getConnectionRepository()->getConnections($node, $filters, $includeIndirectConnections);
    }

    /**
     * @param \Kitano\ConnectionBundle\Model\NodeInterface $source
     * @param \Kitano\ConnectionBundle\Model\NodeInterface $destination
     * @param $type
     * @return \Kitano\ConnectionBundle\Model\ConnectionInterface
     * @throws \Kitano\ConnectionBundle\Exception\AlreadyConnectedException
     */
    protected function createConnection(NodeInterface $source, NodeInterface $destination, $type)
    {
        if ($this->isConnectedTo($source, $destination, array('type' => $type, 'distance' => 1))) {
            throw new AlreadyConnectedException(
            sprintf('Objects %s (%s) and %s (%s) are already connected', get_class($source), $source->getId(), get_class($destination), $destination->getId()
            )
            );
        }

        $relatedConnections = array();

        $sourceConnections = $this->getConnections($source, array('type' => $type));
        $destinationConnections = $this->getConnections($destination, array('type' => $type));
        foreach ($sourceConnections as $key => $sourceConnection) {
            $isSelfConnection = intval($sourceConnection->getSourceObjectId()) === $destination->getId();
            $isSelfConnection |= intval($sourceConnection->getDestinationObjectId()) === $destination->getId();
            if ($isSelfConnection) {
                $sourceConnection->setDistance(1);
                $sourceConnection->setLinkerNodes(array());
                $relatedConnections[] = $sourceConnection;
                unset($sourceConnections[$key]);
            }
        }
        if (empty($relatedConnections)) {
            $newConnection = $this->getConnectionRepository()->createEmptyConnection();
            $newConnection->setType($type);
            $newConnection->setSource($source);
            $newConnection->setDestination($destination);
            $newConnection->setDistance(1);
            $newConnection->setLinkerNodes(array());
            $relatedConnections[] = $newConnection;
        }

        return array_merge($relatedConnections, $this->createRelatedConnections($source, $sourceConnections, $destination, $destinationConnections), $this->createRelatedConnections($destination, $destinationConnections, $source, $sourceConnections));
    }

    protected function createRelatedConnections(NodeInterface $source, array $sourceConnections, NodeInterface $destination, array $destinationConnections)
    {
        $relatedConnections = array();
        foreach ($sourceConnections as $sourceConnection) {
            $isSelfConnection = $sourceConnection->getSourceObjectId() === $destination->getId();
            $isSelfConnection |= $sourceConnection->getDestinationObjectId() === $destination->getId();
            if (!$isSelfConnection) {
                //Create a new connection for the given destination node based on the current source node connection
                $newConnection = $this->getConnectionRepository()->createEmptyConnection();
                $newConnection->setType($sourceConnection->getType());

                //Replace given source node with given destination node
                if (intval($sourceConnection->getSourceObjectId()) === $source->getId()) {
                    $newConnection->setSource($destination);
                    $newConnection->setDestination($sourceConnection->getDestination());
                } else {
                    $newConnection->setDestination($destination);
                    $newConnection->setSource($sourceConnection->getSource());
                }
                //Increment the distance to the other node in the connection
                $newConnection->setDistance($sourceConnection->getDistance() + 1);
                $newConnection->setLinkerNodes(array_merge($sourceConnection->getLinkerNodes(), array($source->getId())));

                //Check if the new connection already exists
                $alreadyExists = false;
                foreach ($destinationConnections as $destinationConnection) {
                    $matchedNewSource = $newConnection->getSource()->getId() === $destinationConnection->getSource()->getId();
                    $matchedNewSource |= $newConnection->getSource()->getId() === $destinationConnection->getDestination()->getId();
                    $matchedNewDestination = $newConnection->getDestination()->getId() === $destinationConnection->getSource()->getId();
                    $matchedNewDestination |= $newConnection->getDestination()->getId() === $destinationConnection->getDestination()->getId();
                    if ($matchedNewSource && $matchedNewDestination) {
                        $alreadyExists = true;
                        if ($newConnection->getDistance() < $destinationConnection->getDistance()) {
                            //Update the existing connection with the new data
                            $destinationConnection->setDistance($newConnection->getDistance());
                            $destinationConnection->setLinkerNodes($newConnection->getLinkerNodes());
                        }
                        break;
                    }
                }
                $relatedConnections[] = $alreadyExists ? $destinationConnection : $newConnection;
            }
        }

        return $relatedConnections;
    }

    /**
     * @param \Kitano\ConnectionBundle\Model\NodeInterface $source
     * @param \Kitano\ConnectionBundle\Model\NodeInterface $destination
     * @param $filters
     * @return array
     * @throws \Kitano\ConnectionBundle\Exception\NotConnectedException
     */
    protected function filterConnectionsForDestroy(NodeInterface $source, NodeInterface $destination, $filters)
    {
        if (!$this->areConnected($source, $destination, array('type' => $filters['type'], 'distance' => 1))) {
            throw new NotConnectedException(
            sprintf('Objects %s (%s) and %s (%s) are not connected', get_class($source), $source->getId(), get_class($destination), $destination->getId()
            )
            );
        }

        $this->filterValidator->validateFilters($filters);
        $connections = $this->getConnectionRepository()->getConnections($source, $filters);

        foreach ($connections as $i => $connection) {
            if ($connection->getDestination() !== $destination && $connection->getSource() !== $destination) {
                unset($connections[$i]);
            }
        }
        if (count($connections) == 0) {
            throw new NotConnectedException(
            sprintf('Objects %s (%s) and %s (%s) are not connected', get_class($source), $source->getId(), get_class($destination), $destination->getId()
            )
            );
        }
        return array_merge($connections, $this->filterRelatedConnectionsForDestroy($source, $destination, $filters), $this->filterRelatedConnectionsForDestroy($destination, $source, $filters));
    }

    /**
     * @param \Kitano\ConnectionBundle\Model\NodeInterface $source
     * @param \Kitano\ConnectionBundle\Model\NodeInterface $destination
     * @param $filters
     * @return array
     * @throws \Kitano\ConnectionBundle\Exception\NotConnectedException
     */
    protected function filterRelatedConnectionsForDestroy(NodeInterface $source, NodeInterface $destination, $filters)
    {
        $connections = array();
        $indirectConnections = $this->getConnectionRepository()->getConnectionsByLinkerNodes(array($destination), $filters);
        foreach ($indirectConnections as $indirectConnection) {
            $isConnectionForDestroy = $indirectConnection->getSourceObjectId() === $source->getId() || $indirectConnection->getDestinationObjectId();
            $isConnectionForDestroy |= in_array($source->getId(), $indirectConnection->getLinkerNodes());
            if ($isConnectionForDestroy) {
                $connections[] = $indirectConnection;
            }
        }
        return $connections;
    }

    /**
     * @param EventDispatcherInterface $dispatcher
     */
    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * @param ConnectionRepositoryInterface $connectionRepository
     */
    public function setConnectionRepository(ConnectionRepositoryInterface $connectionRepository)
    {
        $this->connectionRepository = $connectionRepository;
    }

    /**
     * @return ConnectionRepositoryInterface $connectionRepository
     */
    public function getConnectionRepository()
    {
        return $this->connectionRepository;
    }

    /**
     * @param \Kitano\ConnectionBundle\Manager\FilterValidator
     */
    public function setFilterValidator(FilterValidator $validator)
    {
        $this->filterValidator = $validator;

        return $this;
    }

    /**
     * @return \Kitano\ConnectionBundle\Manager\FilterValidator
     */
    public function getFilterValidator()
    {
        return $this->filterValidator;
    }

}
