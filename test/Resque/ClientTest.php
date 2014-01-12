<?php

namespace Resque;

class ClientTest extends Test
{
    public function testHashFunctions()
    {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Unable to fork child worker.');
        }

        if ($pid === 0) {
            $values = array(
                'one'   => 'abc',
                'two'   => 'def',
                'three' => 123,
                'four'  => 1.0 / 3
            );

            $this->redis->hmset('some_other_key', $values);
            $hash = $this->redis->hgetall('some_other_key');
        } elseif ($pid > 0) {
            $values = array(
                'one'   => 'abc',
                'two'   => 'def',
                'three' => 123,
                'four'  => 1.0 / 3
            );

            $this->redis->hmset('some_key', $values);
            $hash = $this->redis->hgetall('some_key');
        }

        $this->assertEquals($values, $hash);
    }
}
