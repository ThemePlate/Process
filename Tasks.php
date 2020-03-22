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
	private $report_callback;
	private $start = 0;
	private $end   = 0;
	private $limit = 0;
	private $every = 20;
	private $tasks = array();

	public function __construct( $identifier ) {

		$this->identifier = 'tpt_' . $identifier;
		$this->process    = new Process( array( $this, 'runner' ), $this->identifier );

		add_action( $this->identifier . '_event', array( $this, 'runner' ) );
		add_filter( 'cron_schedules', array( $this, 'maybe_schedule' ) );

	}


	public function runner( $identifier ) {

		if ( $this->is_running() ) {
			wp_die();
		}


		$this->lock();

		$this->tasks = get_option( $identifier . '_tasks', array() );

		$done  = array();
		$index = 0;
		$total = count( $this->tasks );
		$limit = $this->limit ?: $total;

		if ( $limit > $total ) {
			$limit = $total;
		}

		while ( $index < $limit ) {
			$task = $this->tasks[ $index ];

			try {
				$output = call_user_func_array( $task['callback_func'], (array) $task['callback_args'] );
			} catch ( \Exception $e ) {
				$output = $e->getMessage();
			}

			$done[ $index ] = compact( 'task', 'output' );

			unset( $this->tasks[ $index ] );
			$index++;
		}

		$this->unlock();
		$this->_report( $done );

		if ( $index < $total ) {
			$this->next();
		} else {
			$this->complete();
		}

	}


	public function execute() {

		$this->save();
		$this->process->dispatch();

	}


	public function add( $callback_func, $callback_args = array() ) {

		$this->tasks[] = compact( 'callback_func', 'callback_args' );

		return $this;

	}


	public function limit( $number ) {

		$this->limit = $number;

		return $this;

	}


	public function every( $second ) {

		$this->every = $second;

		return $this;

	}


	public function report( $callback ) {

		$this->report_callback = $callback;

		return $this;

	}


	public function maybe_schedule( $schedules ) {

		if ( $this->limit ) {
			$interval = $this->every;

			$schedules[ $this->identifier . '_interval' ] = array(
				'interval' => $interval,
				'display'  => sprintf( __( 'Every %d Seconds' ), $interval ),
			);
		}

		return $schedules;

	}


	private function save() {

		$this->tasks = array_values( $this->tasks );

		update_option( $this->identifier . '_tasks', $this->tasks, false );

	}


	private function is_running() {

		return get_option( $this->identifier . '_lock' );

	}


	private function lock() {

		$this->start = time();

		update_option( $this->identifier . '_lock', microtime(), false );

	}


	private function unlock() {

		$this->end = time();

		delete_option( $this->identifier . '_lock' );

	}


	private function next() {

		$this->save();

		if ( ! wp_next_scheduled( $this->identifier . '_event', (array) $this->identifier ) ) {
			wp_schedule_event( $this->start + $this->every, $this->identifier . '_interval', $this->identifier . '_event', (array) $this->identifier );
		}

	}


	private function complete() {

		$timestamp = wp_next_scheduled( $this->identifier . '_event', (array) $this->identifier );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $this->identifier . '_event', (array) $this->identifier );
		}

		delete_option( $this->identifier . '_tasks' );

	}


	private function _report( $done ) {

		if ( ! $this->report_callback ) {
			return null;
		}

		$output = array(
			'start' => $this->start,
			'end'   => $this->end,
			'tasks' => $done,
		);

		return call_user_func( $this->report_callback, $output );

	}

}
