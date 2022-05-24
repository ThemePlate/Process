<?php

/**
 * @package ThemePlate
 */

namespace Tests;

use ThemePlate\Process\Report;
use PHPUnit\Framework\TestCase;

class ReportTest extends TestCase {
	public function for_to_string(): array {
		return array(
			'with empty data'       => array( array() ),
			'with numeric data'     => array( array( 'test', 'this' ) ),
			'with associative data' => array(
				array(
					'one'     => 'more',
					'another' => 'try',
				),
			),
			'with multidimension  ' => array(
				array(
					'here' => array( 1, 2 ),
					'say'  => array(
						'yes' => 'Please',
						'no'  => 'Sorry',
					),
				),
			),
		);
	}

	/**
	 * @dataProvider for_to_string
	 */
	public function test_to_string( array $data ): void {
		$time   = time();
		$report = new Report( $data, $time, $time );
		$time   = gmdate( Report::DATE_FORMAT, $time );

		$this->assertStringStartsWith( 'Tasks: Array', $report );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$this->assertStringContainsString( print_r( $data, true ), $report );
		$this->assertStringContainsString( 'Start: ' . $time, $report );
		$this->assertStringContainsString( 'End: ' . $time, $report );
	}
}
