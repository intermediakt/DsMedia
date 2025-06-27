<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function normalize_file_name( $file_name ){
	if (strpos($file_name, '-') !== false) {
		$kick_mid = explode('-', $file_name);
		array_pop($kick_mid);
	} else {
		$kick_mid = [$file_name];
	}

	$half_way = implode(' ', $kick_mid);
	if (strpos($half_way, '_') !== false) {
		$kick_under = explode('_', $half_way);
		$final = implode(' ', $kick_under);
		return $final;
	}
	
	return $half_way;
}