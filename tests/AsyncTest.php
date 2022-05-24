<?php

/**
 * @package ThemePlate
 */

namespace Tests;

use ThemePlate\Process\Async;
use WP_Ajax_UnitTestCase;
use WPAjaxDieStopException;

class AsyncTest extends WP_Ajax_UnitTestCase {
	public function test_instantiating_class_add_hooks(): void {
		$async = new Async( 'time' );

		$this->assertSame( 10, has_action( 'wp_ajax_' . $async->get_identifier(), array( $async, 'handle' ) ) );
		$this->assertSame( 10, has_action( 'wp_ajax_nopriv_' . $async->get_identifier(), array( $async, 'handle' ) ) );
	}

	public function test_multiple_similar_callbacks(): void {
		$async = array();

		for ( $i = 1; $i <= 3; $i++ ) {
			$async[] = new Async( 'time' );
		}

		$this->assertNotSame( $async[0]->get_identifier(), $async[1]->get_identifier() );
		$this->assertNotSame( $async[1]->get_identifier(), $async[2]->get_identifier() );
		$this->assertNotSame( $async[0]->get_identifier(), $async[2]->get_identifier() );
	}

	public function test_handle_and_dispatch(): void {
		$async = new Async(
			function() {
				microtime();
			}
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		global $_POST;

		$_POST['nonce'] = wp_create_nonce( $async->get_identifier() );

		try {
			$this->_handleAjax( $async->get_identifier() );
		} catch ( WPAjaxDieStopException $exception ) {
			$this->assertSame( '', $exception->getMessage() );
		}

		$this->assertFalse( $async->dispatch() );
	}

	public function for_then_and_catch(): array {
		return array(
			'with a successful execution' => array( array( 'here' ) ),
			'with a failed execution'     => array( array( array( 'here' ) ) ),
		);
	}

	/**
	 * @dataProvider for_then_and_catch
	 */
	public function test_then_and_catch( array $args ): void {
		$async = new Async( array( $this, 'ajax_callback' ), $args );

		$async->then( array( $this, 'then_callback' ) );
		$async->catch( array( $this, 'catch_callback' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		global $_POST;

		$_POST['nonce'] = wp_create_nonce( $async->get_identifier() );

		$this->expectException( 'WPAjaxDieStopException' );
		$this->_handleAjax( $async->get_identifier() );
	}

	public function ajax_callback( string $test ): string {
		return 'Hello from ' . $test;
	}

	public function then_callback( string $output ): void {
		$this->assertSame( $this->ajax_callback( 'here' ), $output );
	}

	public function catch_callback( string $output ): void {
		$this->assertNotSame( $this->ajax_callback( 'here' ), $output );
	}
}
