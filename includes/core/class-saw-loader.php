<?php
/**
 * SAW Hook Loader - WordPress Hook Registration Manager
 *
 * Centralizes registration of all WordPress hooks and filters.
 * Provides a queue system for hooks that are registered at once.
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hook loader class
 *
 * @since 1.0.0
 */
class SAW_Loader {

    /**
     * Registered actions queue
     *
     * @since 1.0.0
     * @var array
     */
    protected $actions;

    /**
     * Registered filters queue
     *
     * @since 1.0.0
     * @var array
     */
    protected $filters;

    /**
     * Constructor - initialize hook arrays
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->actions = [];
        $this->filters = [];
    }

    /**
     * Add action to queue
     *
     * @since 1.0.0
     * @param string $hook          WordPress hook name
     * @param object $component     Instance containing callback method
     * @param string $callback      Method name to call
     * @param int    $priority      Hook priority (default: 10)
     * @param int    $accepted_args Number of arguments (default: 1)
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add filter to queue
     *
     * @since 1.0.0
     * @param string $hook          WordPress hook name
     * @param object $component     Instance containing callback method
     * @param string $callback      Method name to call
     * @param int    $priority      Hook priority (default: 10)
     * @param int    $accepted_args Number of arguments (default: 1)
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add hook to array
     *
     * @since 1.0.0
     * @param array  $hooks         Hooks array to add to
     * @param string $hook          WordPress hook name
     * @param object $component     Instance containing callback
     * @param string $callback      Method name
     * @param int    $priority      Hook priority
     * @param int    $accepted_args Number of arguments
     * @return array Updated hooks array
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        ];

        return $hooks;
    }

    /**
     * Register all queued hooks with WordPress
     *
     * Loops through all registered actions and filters
     * and registers them with WordPress.
     *
     * @since 1.0.0
     */
    public function run() {
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}