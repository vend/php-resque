<?php

namespace Resque;

class ResqueTest extends Test
{
    public function testInvalidWorkerDoesNotExist()
    {
        $this->assertFalse($this->resque->workerExists('blah'));
    }
}
