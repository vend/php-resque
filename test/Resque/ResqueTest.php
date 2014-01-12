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
        $this->assertInternalType('array', $this->resque->getWorkerIds());
    }

    public function testEmptyWorkerPids()
    {
        $this->assertInternalType('array', $this->resque->getWorkerPids());
    }
}
