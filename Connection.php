<?php

namespace sitkoru\redis;

use Redis;
use yii\base\Exception;

class Connection extends \yii\redis\Connection
{

    /**
     * @var Redis redis socket connection
     */
    private $_socket;


    /**
     * @inheritdoc
     */
    public function open()
    {
        if ($this->_socket !== null) {
            return;
        }
        $connection = ($this->unixSocket ?: $this->hostname . ':' . $this->port) . ', database=' . $this->database;
        \Yii::trace('Opening redis DB connection: ' . $connection, __METHOD__);
        $this->_socket = new \Redis();
        $this->_socket->connect($this->unixSocket ?: $this->hostname, $this->port);
        if ($this->_socket) {
            if ($this->password !== null) {
                $this->_socket->auth($this->password);
            }
            $this->_socket->select($this->database);
            $this->initConnection();
        } else {
            \Yii::error(
                "Failed to open redis DB connection ($connection): " . $this->_socket->getLastError(),
                __CLASS__
            );
            $message = YII_DEBUG ? "Failed to open redis DB connection ($connection): $this->_socket->getLastError()" : 'Failed to open DB connection.';
            throw new Exception($message, $this->_socket->getLastError());
        }
    }

    /**
     * @inheritdoc
     */
    public function close()
    {
        if ($this->_socket !== null) {
            $this->_socket->close();
            $this->_socket = null;
        }
    }

    public function evalCommand($name, $params)
    {
        $this->open();
        array_unshift($params, $name);
        $result = call_user_func_array([$this->_socket, 'rawCommand'], $params);
        if ($result === false && $this->_socket->getLastError()) {
            throw new Exception('Redis error: ' . $this->_socket->getLastError() . '\nRedis command was: ' . $name);
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function executeCommand($name, $params = [])
    {
        $this->open();
        $result = call_user_func_array([$this->_socket, $name], $params);
        if ($result === false && $this->_socket->getLastError()) {
            throw new Exception('Redis error: ' . $this->_socket->getLastError() . '\nRedis command was: ' . $name);
        }
        return $result;
    }

    public function getLastError()
    {
        return $this->_socket->getLastError();
    }
}