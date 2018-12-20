<?php


namespace App;

use React\Socket\ConnectionInterface;
use SplObjectStorage;

class ConnectionsPool
{
    /** @var SplObjectStorage  */
    private $connections;

    public function __construct()
    {
        $this->connections = new SplObjectStorage();
    }

    public function add(ConnectionInterface $connection)
    {
        $connection->write("Enter your name: ");
        $this->initEvents($connection);
        $this->setConnectionData($connection, []);
    }

    /**
     * @param ConnectionInterface $connection
     */
    private function initEvents(ConnectionInterface $connection)
    {
        // On receiving the data we loop through other connections
        // from the pool and write this data to them
        $connection->on('data', function ($data) use ($connection) {
            $connectionData = $this->getConnectionData($connection);

            // It is the first data received, so we consider it as
            // a user's name.
            if (empty($connectionData)) {
                $this->addNewMember($data, $connection);
                return;
            }

            $name = $connectionData['name'];
            $this->sendAll("$name: $data", $connection);
        });

        // When connection closes detach it from the pool
        $connection->on('close', function() use ($connection){
            $data = $this->getConnectionData($connection);
            $name = $data['name'] ?? '';

            $this->connections->offsetUnset($connection);
            $this->sendAll("User $name leaves the chat\n", $connection);
        });
    }

    /**
     * Send data to all connections from the pool except
     * the specified one.
     *
     * @param mixed $data
     * @param ConnectionInterface $except
     */
    private function sendAll($data, ConnectionInterface $except) {
        foreach ($this->connections as $conn) {
            if ($conn != $except) {
                $conn->write($data);
            }
        }
    }

    private function setConnectionData(ConnectionInterface $connection, $data)
    {
        $this->connections->offsetSet($connection, $data);
    }

    private function getConnectionData(ConnectionInterface $connection)
    {
        return $this->connections->offsetGet($connection);
    }

    private function addNewMember($name, $connection)
    {
        $name = str_replace(["\n", "\r"], "", $name);
        $this->setConnectionData($connection, ['name' => $name]);
        $this->sendAll("User {$name} joins the chat\n", $connection);
    }
}