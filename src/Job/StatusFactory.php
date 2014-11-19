<?php

namespace Resque\Job;

use Resque\Exception\JobIdException;
use Resque\JobInterface;
use Resque\Resque;

class StatusFactory
{
    /**
     * @var Resque
     */
    protected $resque;

    public function __construct(Resque $resque)
    {
        $this->resque = $resque;
    }

    /**
     * @param String $id
     * @return \Resque\Job\Status
     */
    public function forId($id)
    {
        return new Status($id, $this->resque);
    }

    /**
     * @param JobInterface $job
     * @return Status
     * @throws \Resque\Exception\JobIdException
     */
    public function forJob(JobInterface $job)
    {
        $payload = $job->getPayload();

        if (empty($payload['id'])) {
            throw new JobIdException('Job has no ID in payload, cannot get Status object');
        }

        return $this->forId($payload['id']);
    }
}
