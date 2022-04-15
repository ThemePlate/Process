<?php

/**
 * @package ThemePlate
 * @since   0.1.0
 */

namespace ThemePlate\Process;

class Report {

	public $data;
	public int $start;
	public int $end;


	public function __construct( $data, int $start, int $end ) {

		$this->data  = $data;
		$this->start = $start;
		$this->end   = $end;

	}


	public function __toString(): string {

		$lines = array();

		$lines[] = 'Tasks: ' . print_r( $this->data, true );
		$lines[] = 'Start: ' . gmdate( 'Y-m-d H:i:s', $this->start );
		$lines[] = 'End: ' . gmdate( 'Y-m-d H:i:s', $this->end );

		return implode( "\n", $lines );

	}

}
