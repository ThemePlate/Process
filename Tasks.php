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

		$this->tasks = get_transient( $identifier . '_tasks', array() );

		foreach ( $this->tasks as $index => $task ) {
			call_user_func_array( $task['callback_func'], (array) $task['callback_args'] );
		}

		delete_transient( $identifier . '_tasks' );

	}


	public function execute() {

		set_transient( $this->identifier . '_tasks', $this->tasks );
		$this->process->dispatch();

	}


	public function add( $callback_func, $callback_args = array() ) {

		$this->tasks[] = compact( 'callback_func', 'callback_args' );

	}

}
