<?php

/**
 * Helper for background process
 *
 * @package ThemePlate
 * @since 0.1.0
 */

namespace ThemePlate;

class Process {

	private $identifier;
	private $callback_func;
	private $callback_args;


	public function __construct( $callback_func, $callback_args = null ) {

		$this->identifier    = 'tpp_' . $callback_func;
		$this->callback_func = $callback_func;
		$this->callback_args = $callback_args;

		add_action( 'wp_ajax_' . $this->identifier, array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . $this->identifier, array( $this, 'handle' ) );

	}


	public function handle() {

		session_write_close();

		if ( wp_verify_nonce( $_REQUEST['nonce'], $this->identifier ) ) {
			call_user_func_array( $this->callback_func, $this->callback_args );
		}

		wp_die();

	}


	public function dispatch() {

		$query_args = array(
			'action' => $this->identifier,
			'nonce'  => wp_create_nonce( $this->identifier ),
		);
		$post_url   = add_query_arg( $query_args, admin_url( 'admin-ajax.php' ) );
		$post_args  = array(
			'timeout'  => 1,
			'blocking' => false,
			'body'     => $this->callback_args,
		);

		return wp_remote_post( esc_url_raw( $post_url ), $post_args );

	}

}
