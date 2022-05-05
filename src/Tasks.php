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
		add_action( 'shutdown', array( $this, 'execute' ) );

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

		if ( $this->is_running() || ! $this->has_queued() ) {
			return;
		}

		$queued = $this->get_queued( $identifier );

		$this->tasks = $queued['tasks'];

		if ( ! count( $this->tasks ) ) {
			return;
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
			$this->save( $queued['key'] );
			$index++;
		}

		$this->unlock();
		$this->reporter( $done );

		if ( $index >= $this->total ) {
			$this->complete( $queued['key'] );
		}

		remove_action( 'shutdown', array( $this, 'execute' ) );

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


	public function remove( callable $callback_func, array $callback_args = array() ): Tasks {

		$index = array_search( compact( 'callback_func', 'callback_args' ), $this->tasks, true );

		if ( false !== $index ) {
			unset( $this->tasks[ $index ] );
		}

		return $this;

	}


	public function clear(): Tasks {

		$this->tasks = array();

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

		if ( $this->has_queued() && ! $this->next_scheduled() && ! $this->is_running() ) {
			$this->runner( $this->identifier );
		}

	}


	private function save( string $key = null ): void {

		$tasks = array_values( $this->tasks );

		if ( null === $key ) {
			$key = $this->generate_key();
		}

		update_option( $key, $tasks, false );

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


	private function unschedule(): void {

		$timestamp = $this->next_scheduled();

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $this->identifier . '_event', array( $this->identifier ) );
		}

	}


	private function complete( string $key ): void {

		delete_option( $key );

		if ( ! $this->has_queued() ) {
			$this->unschedule();
		}

	}


	private function reporter( array $done ): void {

		if ( empty( $this->report_callback ) ) {
			return;
		}

		foreach ( $this->report_callback as $report_callback ) {
			$report_callback( new Report( $done, $this->start, $this->end ) );
		}

	}


	private function generate_key(): string {

		return $this->identifier . '_tasks_' . microtime( true );

	}


	private function has_queued(): bool {

		global $wpdb;

		$key = $wpdb->esc_like( $this->identifier . '_tasks_' ) . '%';
		$sql = "SELECT COUNT(*) FROM $wpdb->options WHERE `option_name` LIKE %s";

		return $wpdb->get_var( $wpdb->prepare( $sql, $key ) ) > 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	}


	private function get_queued( string $identifier ): array {

		global $wpdb;

		$key = $wpdb->esc_like( $identifier . '_tasks_' ) . '%';
		$sql = "SELECT * FROM $wpdb->options WHERE `option_name` LIKE %s ORDER BY `option_id` ASC LIMIT 1";
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'key'   => $row->option_name,
			'tasks' => maybe_unserialize( $row->option_value ),
		);

	}

}
