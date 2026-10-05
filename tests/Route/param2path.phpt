<?php declare(strict_types=1);

/**
 * Test: Nette\Routing\Route path encoding.
 */

use Nette\Routing\Route;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('path encoding preserves allowed characters and encodes consecutive bytes', function () {
	foreach ([
		['', ''],
		['ordinary-slug_123', 'ordinary-slug_123'],
		['.~!$&\'()*+,;=:@"/-', '.~!$&\'()*+,;=:@"/-'],
		['/a+b?c=d%2F', '/a+b%3Fc=d%252F'],
		["\u{65E5}\u{672C}\u{8A9E}", '%E6%97%A5%E6%9C%AC%E8%AA%9E'],
		["\xFF\x00\x80\xC0\xAF", '%FF%00%80%C0%AF'],
		['a ?#%[]{}\\b', 'a%20%3F%23%25%5B%5D%7B%7D%5Cb'],
	] as [$input, $expected]) {
		Assert::same($expected, Route::param2path($input));
	}
});


test('batched encoding is bytewise equivalent to single-byte encoding', function () {
	$inputs = [];
	$bytes = '';
	for ($i = 0; $i < 256; $i++) {
		$byte = chr($i);
		$bytes .= $byte;
		$inputs[] = $byte;
		$inputs[] = str_repeat($byte, 8);
		$inputs[] = "/$byte%$byte/$byte";
	}

	$inputs[] = $bytes;
	$inputs[] = strrev($bytes);
	mt_srand(42);
	for ($i = 0; $i < 300; $i++) {
		$input = '';
		for ($j = 0; $j < 30; $j++) {
			$input .= chr(mt_rand(0, 255));
		}

		$inputs[] = $input;
	}

	foreach ($inputs as $input) {
		$expected = preg_replace_callback('#[^\w.~!$&\'()*+,;=:@"/-]#', fn($m) => rawurlencode($m[0]), $input);
		Assert::same($expected, Route::param2path($input));
	}
});


test('encoded path parameters round trip through URL generation and matching', function () {
	$route = new Route('<param .+>');
	foreach ([
		["\u{65E5}\u{672C}\u{8A9E}", '/%E6%97%A5%E6%9C%AC%E8%AA%9E'],
		['a/b ?#%2F', '/a/b%20%3F%23%252F'],
	] as [$input, $path]) {
		Assert::same('http://example.com' . $path, testRouteOut($route, ['param' => $input]));
		testRouteIn($route, $path, ['param' => $input, 'test' => 'testvalue'], $path . '?test=testvalue');
	}
});
