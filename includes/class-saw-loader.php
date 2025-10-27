<?php
/**
 * Loader třída pro registraci všech hooks a filters
 * 
 * Centralizuje správu všech WordPress hooks (actions & filters).
 * Díky tomu máme přehledné místo, kde vidíme všechny registrované hooky.
 *
 * @package SAW_Visitors
 */

// Zabránít přímému přístupu
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Loader {

	/**
	 * Pole registrovaných actions
	 * 
	 * @var array
	 */
	protected $actions;

	/**
	 * Pole registrovaných filters
	 * 
	 * @var array
	 */
	protected $filters;

	/**
	 * Konstruktor
	 * Inicializuje prázdná pole pro actions a filters
	 */
	public function __construct() {
		$this->actions = array();
		$this->filters = array();
	}

	/**
	 * Přidat action do fronty
	 * 
	 * @param string $hook WordPress hook name
	 * @param object $component Instance třídy, která obsahuje callback metodu
	 * @param string $callback Název metody v třídě
	 * @param int $priority Priorita (default 10)
	 * @param int $accepted_args Počet argumentů (default 1)
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Přidat filter do fronty
	 * 
	 * @param string $hook WordPress hook name
	 * @param object $component Instance třídy, která obsahuje callback metodu
	 * @param string $callback Název metody v třídě
	 * @param int $priority Priorita (default 10)
	 * @param int $accepted_args Počet argumentů (default 1)
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Utility funkce pro přidání hooku do pole
	 * 
	 * @param array $hooks Pole hooks
	 * @param string $hook WordPress hook name
	 * @param object $component Instance třídy
	 * @param string $callback Název metody
	 * @param int $priority Priorita
	 * @param int $accepted_args Počet argumentů
	 * @return array Aktualizované pole hooks
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
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
	 * Registrace všech definovaných hooks do WordPressu
	 * 
	 * Tato metoda se volá pouze jednou při inicializaci pluginu.
	 * Projde všechny actions a filters a zaregistruje je do WP.
	 */
	public function run() {
		// Registrovat actions
		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		// Registrovat filters
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}