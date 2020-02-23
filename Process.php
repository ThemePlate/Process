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
	private $success_callback;
	private $error_callback;
	private $success_output;
	private $error_output;


	public function __construct( $callback_func, $callback_args = array() ) {

		$this->callback_func = $callback_func;
		$this->callback_args = $callback_args;

		$this->generate_identifier();

		add_action( 'wp_ajax_' . $this->identifier, array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . $this->identifier, array( $this, 'handle' ) );

	}


	private function generate_identifier() {

		$cb_func = print_r( $this->callback_func, true );
		$cb_args = print_r( $this->callback_args, true );

		$this->identifier = 'tpp_' . md5( $cb_func . $cb_args );

	}


	public function handle() {

		session_write_close();

		if ( wp_verify_nonce( $_REQUEST['nonce'], $this->identifier ) ) {
			try {
				$this->success_output = call_user_func_array( $this->callback_func, (array) $this->callback_args );
			} catch ( \Throwable $throwable ) {
				$this->error_output = $throwable;
			} finally {
				$this->trigger();
			}
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


	public function then( $callback ) {

		$this->success_callback = $callback;

	}


	public function catch( $callback ) {

		$this->error_callback = $callback;

	}


	private function trigger() {

		if ( $this->error_output ) {
			return call_user_func( $this->error_callback, $this->error_output );
		} else {
			return call_user_func( $this->success_callback, $this->success_output );
		}

	}

}
