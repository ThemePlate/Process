<?php

/**
 * Helper for background process
 *
 * @package ThemePlate
 * @since 0.1.0
 */

namespace ThemePlate;

class Process {

	private $callback_func;
	private $callback_args;


	public function __construct( $callback_func, $callback_args = null ) {

		$this->callback_func = $callback_func;
		$this->callback_args = $callback_args;

		add_action( 'wp_ajax_' . $this->callback_func, array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . $this->callback_func, array( $this, 'handle' ) );

	}


	public function handle() {

		if ( wp_verify_nonce( $_REQUEST['nonce'], $this->callback_func ) ) {
			call_user_func_array( $this->callback_func, $this->callback_args );
		}

		wp_die();

	}


	public function dispatch() {

		$query_args = array(
			'action' => $this->callback_func,
			'nonce'  => wp_create_nonce( $this->callback_func ),
		);
		$post_url   = add_query_arg( $query_args, admin_url( 'admin-ajax.php' ) );
		$post_args  = array(
			'body'     => $this->callback_args,
		);

		return wp_remote_post( esc_url_raw( $post_url ), $post_args );

	}

}
