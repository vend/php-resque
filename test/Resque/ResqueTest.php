<?php

namespace Resque;

class ResqueTest extends Test
{
    public function testInvalidWorkerDoesNotExist()
    {
        $this->assertFalse($this->resque->workerExists('blah'));
    }

    public function testEmptyWorkerIds()
    {
        $this->assertEquals(array(), $this->resque->getWorkerIds());
    }

    public function testEmptyWorkerPids()
    {
        $this->assertEquals(array(), $this->resque->getWorkerPids());
    }
}
