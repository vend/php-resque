<?php

namespace Resque\Util;

/**
 * Configurable
 *
 * A simple protected interface for configuring options, passed into the
 * constructor.
 */
class Configurable
{
    /**
     * Options
     *
     * @var array<string => mixed>
     */
    protected $options;

    /**
     * Constructor
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->configure($options);
    }

    /**
     * Configures options
     *
     * To implement additional options in subclasses, override this method,
     * provide defaults, and merge them into $options, which is passed to this
     * class using parent::configure($options).
     *
     * @param array $options
     */
    protected function configure(array $options)
    {
        $this->options = $options;
    }
}