<?php

// link to section for books
$url_books = 'http://books.toscrape.com/catalogue/category/books_1/';

// link to section for images 
$url_logo = 'http://books.toscrape.com/';

// first page of book list 
$first_page = 'index.html';

// file with the page from which you want to continue downloading 
$file_next_page = 'next_page.txt';

// file with a list of links
$file_urls_list = 'urls_list.txt';


// 1. get URLs pages

echo 'load URLs start<br>';
$page = file_get_contents($file_next_page) ?: $first_page;
if (strcasecmp($page, 'no_page')) {
	echo 'start page: '.$page.'<br>';
	
	// load URLs list
	$urls_list = [];
	if (strcasecmp($page, $first_page)) {
		$urls_list = file($file_urls_list, FILE_SKIP_EMPTY_LINES);
	}
	
	$flag = true;
	while ($flag) {
		// get content
		$content = file_get_contents($url_books.$page);
		
		// find books links
		preg_match_all('/<h3><a\s+href="([^"]+)/', $content, $m);
		if ($m) {
			$urls_list = array_merge($urls_list, $m[1]);
		}
		
		// find to next page link
		preg_match('/<li class="next"><a\s+href="([^"]+)/', $content, $m);
		if ($m) {
			$page = $m[1];
			file_put_contents($file_urls_list, implode("\n", $urls_list));
			file_put_contents($file_next_page, $page);
		} else {
			file_put_contents($file_next_page, 'no_page');
			$flag = false;
		}
	}
} else {
	echo 'load URLs complete<br>';
}


// 2. create table and load URLs to DB

require_once '../Database.php';
$table_books = 'books';
$db = Database::getInstance();
$record = $db->getRow('SHOW TABLES FROM `dekc` LIKE ?s;', $table_books);
if (!$record) {
	$db->query("CREATE TABLE `books` (
	`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`genre` VARCHAR(50) NOT NULL DEFAULT '' COLLATE 'utf8_general_ci',
	`name` VARCHAR(500) NOT NULL DEFAULT '' COLLATE 'utf8_general_ci',
	`description` TEXT(65535) NOT NULL COLLATE 'utf8_general_ci',
	`upc` VARCHAR(50) NOT NULL DEFAULT '' COLLATE 'utf8_general_ci',
	`type` VARCHAR(50) NOT NULL DEFAULT '' COLLATE 'utf8_general_ci',
	`price` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT '0.00',
	`tax` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT '0.00',
	`availability` SMALLINT(6) UNSIGNED NOT NULL DEFAULT '0',
	`reviews` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
	`logo` VARCHAR(500) NOT NULL DEFAULT '' COLLATE 'utf8_general_ci',
	`content` TEXT(65535) NOT NULL COLLATE 'utf8_general_ci',
	`url_page` VARCHAR(500) NOT NULL DEFAULT '' COLLATE 'utf8_general_ci',
	`url_logo` VARCHAR(500) NOT NULL DEFAULT '' COLLATE 'utf8_general_ci',
	PRIMARY KEY (`id`) USING BTREE
	)
	COLLATE='utf8_general_ci'
	ENGINE=MyISAM;");
	echo 'create table "books"<br>';
} else {
	echo 'found table "books"<br>';
}

$cnt = $db->getOne("SELECT COUNT(`id`) FROM ?n;", $table_books);
if (!$cnt) {
	$urls_list = file($file_urls_list, FILE_SKIP_EMPTY_LINES);
	$urls_list = array_unique($urls_list);
	foreach ($urls_list as $v) {
		$db->query('INSERT INTO ?n (`url_page`) VALUES (?s);', $table_books, trim($v));
	}
}


// 3. load pages to DB

echo 'load pages to DB<br>';
$urls_list = $db->getAll("SELECT `id`, `url_page` FROM ?n WHERE `content` IS NULL;", $table_books);
foreach ($urls_list as $v) {
	$content = file_get_contents($url_books.$v['url']);
	$db->query('UPDATE ?n SET `content` = ?s WHERE `id` = ?n LIMIT 1;', $table_books, $v['id'], $content);
}


// 4. parse content

echo 'parse content<br>';
$records = $db->getAll("SELECT `id`, `content` FROM ?n WHERE `name` = '';", $table_books);
foreach ($records as $v) {
	$book = [
		'genre' => '',
		'name' => '',
		'description' => '',
		'upc' => '',
		'type' => '',
		'price' => '',
		'tax' => '',
		'availability' => '',
		'reviews' => '',
		'url_logo' => ''
	];
	
	preg_match('/\.\.\/category\/books\/[^>]+>([^<]+)/', $v['content'], $m);
	if ($m) {
		$book['genre'] = $m[1];
	}
	
	preg_match('/<h1>([^<]+)<\/h1>/', $v['content'], $m);
	if ($m) {
		$book['name'] = $m[1];
	}
	
	preg_match('/<h2>Product Description<\/h2>[^<]+<\/div>[^<]+<p>(.+)?(<\/p>[^<]+<div class="sub-header">)/', $v['content'], $m);
	if ($m) {
		$book['description'] = $m[1];
	}
	
	preg_match('/UPC<\/th><td>([^<]+)</', $v['content'], $m);
	if ($m) {
		$book['upc'] = $m[1];
	}
	
	preg_match('/Price \(excl\. tax\)<\/th><td>£([^<]+)</', $v['content'], $m);
	if ($m) {
		$book['price'] = $m[1];
	}
	
	preg_match('/Tax<\/th><td>£([^<]+)</', $v['content'], $m);
	if ($m) {
		$book['tax'] = $m[1];
	}
	
	preg_match('/Availability<\/th>[^<]+<td>([^<]+)</', $v['content'], $m);
	if ($m) {
		$mm = preg_replace('/\D/', '', $m[1]);
		$book['availability'] = $mm + 0;
	}
	
	preg_match('/Number of reviews<\/th>[^<]+<td>([^<]+)</', $v['content'], $m);
	if ($m) {
		$book['reviews'] = $m[1];
	}
	
	preg_match('/item active">[^<]+<img src="([^"]+)"/', $v['content'], $m);
	if ($m) {
		$book['url_logo'] = $m[1];
	}
	
	$db->query('UPDATE ?n SET `genre` = ?s, `name` = ?s, `description` = ?s, `upc` = ?s, `type` = ?s, `price` = ?i, `tax` = ?i, `availability` = ?i, `reviews` = ?i, `url_logo` = ?s WHERE `id` = ?i LIMIT 1;', $table_books, $book['genre'], $book['name'], $book['description'], $book['upc'], $book['type'], $book['price'], $book['tax'], $book['availability'], $book['reviews'], $book['url_logo'], $v['id']);
}


// 5. loading files

echo 'loading files<br>';
$records = $db->getAll("SELECT `id`, `url_logo` FROM ?n WHERE `logo` = '';", $table_books);
foreach ($records as $v) {
	$file_logo = ltrim($v['url_logo'], './');
	$e = pathinfo($file_logo, PATHINFO_EXTENSION);
	$file_name = md5(uniqid(rand(), 1)).'.'.$e;
	$file_path = __DIR__.'/images/'.$file_name;
	file_put_contents($file_path, file_get_contents($url_logo.$file_logo));
	$db->query('UPDATE ?n SET `logo` = ?s WHERE `id` = ?i LIMIT 1;', $table_books, $file_name, $v['id']);
}


// 6. delete columns url_logo url_page content
//ALTER TABLE `books` DROP COLUMN `url_logo`, DROP COLUMN `url_page`, DROP COLUMN `content`;

echo 'COMPLETED';
