<?php
	// phpinfo();
	
	error_reporting(E_ALL);
	ini_set('display_errors', 1);

	$info = [
		'ini'        => ini_get_all(null, false),
		'constants'  => get_defined_constants(true),
		'extensions' => get_loaded_extensions(),
	];
	
	header('Content-Type: application/json');
	echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

?>