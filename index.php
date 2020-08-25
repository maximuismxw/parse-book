<?php

require_once 'BooksApi.php';

try {
	$api = new BooksApi();
	echo $api->run();
} catch (Exception $e) {
	echo json_encode(['error' => $e->getMessage()]);
}
