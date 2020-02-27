<?php

/**
 * Helper for background tasks
 *
 * @package ThemePlate
 * @since 0.1.0
 */

namespace ThemePlate;

class Tasks {

	private $identifier;
	private $process;
	private $tasks = array();

	public function __construct( $identifier ) {

		$this->identifier = 'tpt_' . $identifier;
		$this->process    = new Process( array( $this, 'runner' ), $this->identifier );

	}


	public function runner( $identifier ) {

		if ( $this->is_running() ) {
			wp_die();
		}

		$this->lock();
		$this->tasks = get_option( $identifier . '_tasks', array() );

		foreach ( $this->tasks as $index => $task ) {
			call_user_func_array( $task['callback_func'], (array) $task['callback_args'] );
		}

		delete_option( $identifier . '_tasks' );
		$this->unlock();

	}


	public function execute() {

		update_option( $this->identifier . '_tasks', $this->tasks, false );
		$this->process->dispatch();

	}


	public function add( $callback_func, $callback_args = array() ) {

		$this->tasks[] = compact( 'callback_func', 'callback_args' );

	}


	private function is_running() {

		return get_option( $this->identifier . '_lock' );

	}


	private function lock() {

		update_option( $this->identifier . '_lock', microtime(), false );

	}


	private function unlock() {

		delete_option( $this->identifier . '_lock' );

	}

}
