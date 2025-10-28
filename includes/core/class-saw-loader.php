<?php
/**
 * SAW Hook Loader
 * Centralizes registration of all WordPress hooks and filters
 *
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Loader {

    /**
     * Registered actions
     * 
     * @var array
     */
    protected $actions;

    /**
     * Registered filters
     * 
     * @var array
     */
    protected $filters;

    /**
     * Constructor
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }

    /**
     * Add action to queue
     * 
     * @param string $hook WordPress hook name
     * @param object $component Instance containing callback method
     * @param string $callback Method name
     * @param int $priority Priority (default 10)
     * @param int $accepted_args Number of arguments (default 1)
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add filter to queue
     * 
     * @param string $hook WordPress hook name
     * @param object $component Instance containing callback method
     * @param string $callback Method name
     * @param int $priority Priority (default 10)
     * @param int $accepted_args Number of arguments (default 1)
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add hook to array
     * 
     * @param array $hooks Hooks array
     * @param string $hook WordPress hook name
     * @param object $component Instance
     * @param string $callback Method name
     * @param int $priority Priority
     * @param int $accepted_args Number of arguments
     * @return array Updated hooks array
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    /**
     * Register all defined hooks with WordPress
     */
    public function run() {
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}