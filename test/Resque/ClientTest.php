<?php

namespace Resque;

class ClientTest extends Test
{
    public function testHashFunctions()
    {
        $values = array(
            'one'   => 'abc',
            'two'   => 'def',
            'three' => 123,
            'four'  => 1.0 / 3
        );

        $success  = $this->redis->hmset('some_other_key', $values);
        $hash     = $this->redis->hgetall('some_other_key');

        $this->assertTrue((boolean)$success, 'HMSET command returns success');
        $this->assertEquals($values, $hash, 'HGETALL command returns array');
    }
}
