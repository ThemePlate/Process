<?php

/**
 * Helper for background tasks
 *
 * @package ThemePlate
 * @since 0.1.0
 */

namespace ThemePlate\Process;

use Exception;

class Tasks {

	private string $identifier;
	private Async $async;
	/**
	 * @var callable[]
	 */
	private array $report_callback;
	private int $start   = 0;
	private int $end     = 0;
	private int $limit   = 0;
	private int $every   = 0;
	private int $total   = 0;
	private array $tasks = array();


	public function __construct( string $identifier ) {

		$this->identifier = 'tpt_' . $identifier;
		$this->async      = new Async( array( $this, 'runner' ), array( $this->identifier ) );

		add_action( $this->identifier . '_event', array( $this, 'runner' ) );
		// phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_filter( 'cron_schedules', array( $this, 'maybe_schedule' ) );
		add_action( 'init', array( $this, 'maybe_run' ) );

	}


	public function get_identifier(): string {

		return $this->async->get_identifier();

	}


	private function set_defaults(): void {

		if ( ! $this->limit && $this->every ) {
			$this->limit = 1;
		}

		if ( ! $this->every && $this->limit ) {
			$this->every = 60;
		}

		$this->total = count( $this->tasks );

	}


	public function runner( string $identifier ): void {

		if ( $this->is_running() ) {
			wp_die();
		}

		$this->tasks = get_option( $identifier . '_tasks', array() );

		if ( ! count( $this->tasks ) ) {
			wp_die();
		}

		$this->set_defaults();
		$this->lock();

		$done  = array();
		$index = 0;
		$limit = $this->limit ?: $this->total;

		if ( $limit >= $this->total ) {
			$limit = $this->total;
		} else {
			$this->schedule();
		}

		while ( $index < $limit ) {
			$task = $this->tasks[ $index ];

			try {
				$output = call_user_func_array( $task['callback_func'], $task['callback_args'] );
			} catch ( Exception $e ) {
				$output = $e->getMessage();
			}

			$done[ $index ] = compact( 'task', 'output' );

			unset( $this->tasks[ $index ] );
			$this->save();
			$index++;
		}

		$this->unlock();
		$this->reporter( $done );

		if ( $index >= $this->total ) {
			$this->complete();
		}

	}


	public function execute(): bool {

		if ( empty( $this->tasks ) ) {
			return false;
		}

		$this->save();

		return $this->async->dispatch();

	}


	public function add( callable $callback_func, array $callback_args = array() ): Tasks {

		$this->tasks[] = compact( 'callback_func', 'callback_args' );

		return $this;

	}


	public function limit( int $number ): Tasks {

		$this->limit = $number;

		return $this;

	}


	public function every( int $second ): Tasks {

		$this->every = $second;

		return $this;

	}


	public function report( callable $callback ): Tasks {

		$this->report_callback[] = $callback;

		return $this;

	}


	public function maybe_schedule( $schedules ) {

		$this->set_defaults();

		if ( $this->limit ) {
			$schedules[ $this->identifier . '_interval' ] = array(
				'interval' => $this->every,
				/* translators: %s: number of seconds */
				'display'  => sprintf( __( 'Every %d Seconds' ), $this->every ),
			);
		}

		return $schedules;

	}


	public function maybe_run(): void {

		$tasks = get_option( $this->identifier . '_tasks', array() );

		if ( count( $tasks ) && ! $this->next_scheduled() && ! $this->is_running() ) {
			$this->runner( $this->identifier );
		}

	}


	private function save(): void {

		$tasks = array_values( $this->tasks );

		update_option( $this->identifier . '_tasks', $tasks, false );

	}


	private function is_running() {

		return get_transient( $this->identifier . '_lock' );

	}


	private function lock(): void {

		$this->start = time();

		if ( $this->every ) {
			$timeout = $this->every * 2;
		} else {
			$timeout = $this->total * 60;
		}

		set_transient( $this->identifier . '_lock', $this->start, $timeout );

	}


	private function unlock(): void {

		$this->end = time();

		delete_transient( $this->identifier . '_lock' );

	}


	private function next_scheduled(): int {

		return wp_next_scheduled( $this->identifier . '_event', array( $this->identifier ) );

	}


	private function schedule(): void {

		if ( ! $this->next_scheduled() ) {
			wp_schedule_event( $this->start + $this->every, $this->identifier . '_interval', $this->identifier . '_event', array( $this->identifier ) );
		}

	}


	private function complete(): void {

		$timestamp = $this->next_scheduled();

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $this->identifier . '_event', array( $this->identifier ) );
		}

		delete_option( $this->identifier . '_tasks' );

	}


	private function reporter( array $done ): void {

		if ( empty( $this->report_callback ) ) {
			return;
		}

		foreach ( $this->report_callback as $report_callback ) {
			$report_callback( new Report( $done, $this->start, $this->end ) );
		}

	}

}
