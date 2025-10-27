<?php
/**
 * SAW Router - URL Routing System (CLEAN VERSION)
 * 
 * Zpracovává: /admin/*, /manager/*, /terminal/*, /visitor/*
 * 
 * @package    SAW_Visitors
 * @subpackage SAW_Visitors/includes
 * @since      4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Router {

	private $route;
	private $path;

	public function __construct() {
		$this->route = get_query_var( 'saw_route', '' );
		$this->path = get_query_var( 'saw_path', '' );
	}

	/**
	 * Main dispatch method
	 */
	public function dispatch() {
		if ( empty( $this->route ) ) {
			return;
		}

		// Log pro debugging
		if ( SAW_DEBUG ) {
			error_log( sprintf( 
				'[SAW Router] Route: %s, Path: %s', 
				$this->route, 
				$this->path 
			) );
		}

		// Dispatch podle route
		switch ( $this->route ) {
			case 'admin':
				$this->handle_admin_routes();
				break;

			case 'manager':
				$this->handle_manager_routes();
				break;

			case 'terminal':
				$this->handle_terminal_routes();
				break;

			case 'visitor':
				$this->handle_visitor_routes();
				break;

			default:
				$this->render_404();
				break;
		}

		exit;
	}

	/**
	 * Handle admin routes
	 */
	private function handle_admin_routes() {
		// Zatím jen placeholder - implementujeme v dalších chatech
		$this->render_placeholder( 'Admin Panel', 'Bude implementováno v příštích chatech.' );
	}

	/**
	 * Handle manager routes
	 */
	private function handle_manager_routes() {
		$this->render_placeholder( 'Manager Panel', 'Bude implementováno v příštích chatech.' );
	}

	/**
	 * Handle terminal routes
	 */
	private function handle_terminal_routes() {
		$this->render_placeholder( 'Terminal Panel', 'Bude implementováno v příštích chatech.' );
	}

	/**
	 * Handle visitor routes
	 */
	private function handle_visitor_routes() {
		$this->render_placeholder( 'Visitor Panel', 'Bude implementováno v příštích chatech.' );
	}

	/**
	 * Render placeholder page
	 */
	private function render_placeholder( $title, $message ) {
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html( $title ); ?> - SAW Visitors</title>
			<style>
				body {
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
					background: #f0f0f1;
					padding: 50px 20px;
					margin: 0;
				}
				.container {
					max-width: 600px;
					margin: 0 auto;
					background: white;
					padding: 40px;
					border-radius: 8px;
					box-shadow: 0 1px 3px rgba(0,0,0,0.1);
					text-align: center;
				}
				h1 {
					color: #1d2327;
					margin-bottom: 20px;
				}
				p {
					color: #50575e;
					line-height: 1.6;
				}
				.badge {
					display: inline-block;
					background: #2271b1;
					color: white;
					padding: 8px 16px;
					border-radius: 4px;
					font-size: 14px;
					margin-top: 20px;
				}
			</style>
		</head>
		<body>
			<div class="container">
				<h1><?php echo esc_html( $title ); ?></h1>
				<p><?php echo esc_html( $message ); ?></p>
				<p><strong>Route:</strong> <?php echo esc_html( $this->route ); ?></p>
				<p><strong>Path:</strong> <?php echo esc_html( $this->path ); ?></p>
				<div class="badge">SAW Visitors v<?php echo SAW_VISITORS_VERSION; ?></div>
			</div>
		</body>
		</html>
		<?php
	}

	/**
	 * Render 404 page
	 */
	private function render_404() {
		status_header( 404 );
		$this->render_placeholder( '404 Not Found', 'Požadovaná stránka nebyla nalezena.' );
	}
}