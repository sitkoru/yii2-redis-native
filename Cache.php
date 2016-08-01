<?php
namespace sitkoru\redis;

class Cache extends \yii\redis\Cache
{

    /**
     * @inheritdoc
     */
    protected function getValues($keys)
    {
        $response = $this->redis->executeCommand('MGET', [$keys]);
        $result = [];
        $i = 0;
        foreach ($keys as $key) {
            $result[$key] = $response[$i++];
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function setValue($key, $value, $expire)
    {
        if ($expire == 0) {
            return (bool)$this->redis->executeCommand('SET', [$key, $value]);
        } else {
            $expire = (int)($expire * 1000);
            return (bool)$this->redis->executeCommand('SET', [$key, $value, ['px' => $expire]]);
        }
    }

    /**
     * @inheritdoc
     */
    protected function addValue($key, $value, $expire)
    {
        if ($expire == 0) {
            return (bool)$this->redis->executeCommand('SETNX', [$key, $value]);
        } else {
            $expire = (int)($expire * 1000);
            $result = (bool)$this->redis->executeCommand('SETNX', [$key, $value]);
            if ($result) {
                $this->redis->executeCommand('PEXPIRE', [$key, $expire]);
            }
            return $result;
        }
    }

    /**
     * @inheritdoc
     */
    protected function setValues($data, $expire)
    {
        $args = [];
        foreach ($data as $key => $value) {
            $args[] = $key;
            $args[] = $value;
        }
        $failedKeys = [];
        if ($expire == 0) {
            $this->redis->executeCommand('MSET', [$args]);
        } else {
            $expire = (int)($expire * 1000);
            $this->redis->executeCommand('MULTI');
            $this->redis->executeCommand('MSET', [$args]);
            $index = [];
            foreach ($data as $key => $value) {
                $this->redis->executeCommand('PEXPIRE', [$key, $expire]);
                $index[] = $key;
            }
            $result = $this->redis->executeCommand('EXEC');
            array_shift($result);
            foreach ($result as $i => $r) {
                if ($r != 1) {
                    $failedKeys[] = $index[$i];
                }
            }
        }
        return $failedKeys;
    }


}