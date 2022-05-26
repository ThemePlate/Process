<?php

/**
 * @package ThemePlate
 */

namespace Tests;

use ThemePlate\Process\Report;
use ThemePlate\Process\Tasks;
use WP_UnitTestCase;

class TasksTest extends WP_UnitTestCase {
	private Tasks $tasks;
	private string $identifier;

	protected function setUp(): void {
		$this->tasks = new Tasks( 'test' );

		$this->identifier = 'tpt_test';

		if ( 'test_runner_with_schedule_via_limit' === $this->getName() ) {
			$this->tasks->limit( 3 );
		}

		if ( 'test_runner_with_schedule_via_every' === $this->getName() ) {
			$this->tasks->every( 30 );
		}
	}

	public function test_instantiating_class_add_hooks(): void {
		$this->assertIsString( $this->tasks->get_identifier() );
		$this->assertSame( 10, has_action( $this->identifier . '_event', array( $this->tasks, 'runner' ) ) );
		$this->assertSame( 10, has_filter( 'cron_schedules', array( $this->tasks, 'maybe_schedule' ) ) );
	}

	public function test_execute_with_nothing_added(): void {
		$this->assertFalse( $this->tasks->execute() );
	}

	public function test_execute_with_something_added(): void {
		$this->tasks->add( 'time' );
		$this->assertTrue( $this->tasks->execute() );
	}

	public function test_removing_previously_added(): void {
		$callback = function() {
			microtime();
		};

		$this->tasks->add( $callback )->remove( $callback );
		$this->test_execute_with_nothing_added();
	}

	public function test_clearing_the_current_list(): void {
		$this->tasks->add( 'rand' )->add( 'pi' )->clear();
		$this->test_execute_with_nothing_added();
	}

	public function test_maybe_run(): void {
		do_action( 'init' );
		$this->assertTrue( true );
	}

	public function tasks_callback( $output ): void {
		$this->assertInstanceOf( Report::class, $output );
	}

	protected function execute_runner(): void {
		$this->tasks->execute();
		$this->tasks->runner( $this->identifier );
		$this->assertTrue( true );
	}

	public function test_limit_every_report_dump(): void {
		$limit  = 2;
		$every  = 4;
		$tasks  = array(
			array(
				'callback_func' => 'microtime',
				'callback_args' => array(),
			),
			array(
				'callback_func' => 'date',
				'callback_args' => array( 'l' ),
			),
			array(
				'callback_func' => 'mktime',
				'callback_args' => array( 'intended to fail' ),
			),
		);
		$report = array( $this, 'tasks_callback' );

		$data = $this->tasks
			->add( $tasks[0]['callback_func'], $tasks[0]['callback_args'] )
			->add( $tasks[1]['callback_func'], $tasks[1]['callback_args'] )
			->add( $tasks[2]['callback_func'], $tasks[2]['callback_args'] )
			->limit( $limit )->every( $every )->report( $report )->dump();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'limit', $data );
		$this->assertArrayHasKey( 'every', $data );
		$this->assertArrayHasKey( 'tasks', $data );
		$this->assertArrayHasKey( 'report', $data );
		$this->assertSame( $limit, $data['limit'] );
		$this->assertSame( $every, $data['every'] );
		$this->assertSame( $tasks, $data['tasks'] );
		$this->assertSame( array( $report ), $data['report'] );
		$this->execute_runner();
	}

	public function test_runner_no_schedule(): void {
		$this->tasks->add( 'uniqid' );
		$this->execute_runner();
	}

	public function test_runner_with_schedule_via_limit(): void {
		for ( $i = 1; $i <= 4; $i++ ) {
			$this->tasks->add( 'localtime' );
		}

		$this->execute_runner();
		do_action( $this->identifier . '_event', $this->identifier );
	}

	public function test_runner_with_schedule_via_every(): void {
		for ( $i = 1; $i <= 2; $i++ ) {
			$this->tasks->add( 'timezone_version_get' );
		}

		$this->execute_runner();
		do_action( $this->identifier . '_event', $this->identifier );
	}

	public function test_runner_already_running_is_skipped(): void {
		$tasks = $this->createPartialMock( Tasks::class, array( 'get_queued', 'is_running' ) );

		$tasks->expects( self::never() )->method( 'get_queued' );
		$tasks->expects( self::once() )->method( 'is_running' )->willReturn( time() );

		$tasks->runner( $this->identifier );
	}

	public function test_runner_nothing_queued_is_skipped(): void {
		$tasks = $this->createPartialMock( Tasks::class, array( 'get_queued', 'has_queued' ) );

		$tasks->expects( self::never() )->method( 'get_queued' );
		$tasks->expects( self::once() )->method( 'has_queued' )->willReturn( false );

		$tasks->runner( $this->identifier );
	}

	public function test_empty_queued_tasks_is_deleted(): void {
		$tasks = $this->createPartialMock( Tasks::class, array( 'next_scheduled', 'has_queued', 'get_queued' ) );

		$tasks->expects( self::never() )->method( 'next_scheduled' );
		$tasks->expects( self::once() )->method( 'has_queued' )->willReturn( true );
		$tasks->expects( self::once() )->method( 'get_queued' )->willReturn(
			array(
				'key'   => $this->identifier,
				'tasks' => array(),
			)
		);

		$tasks->runner( $this->identifier );
	}
}
