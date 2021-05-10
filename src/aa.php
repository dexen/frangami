<?php

define('PARAM_S', 1000 * 500);
define('PARAM_N', 200);

	# note: use hrtime() for PHP >= 7.3
$timing = [
	'rubefore' => getrusage(),
	'wall_start_u' => microtime(true),
];

$v = range(0, PARAM_S);
$in = [];
$out = [];
$total = null;
foreach (range(0, PARAM_N) as $n)
	$in[$n] = $v;

foreach ($in as $v)
	$out[] = array_sum($v);

$total = array_sum($out);

printf("Total: %d\n", $total);

$timing['ruafter'] = getrusage();
$timing['wall_end_u'] = microtime(true);
$rusage = getrusage();

echo '<pre>';
printf("Results:\n");
printf("Wall: %.3f\n", ($timing['wall_end_u'] - $timing['wall_start_u']));

$ub = $timing['rubefore']['ru_utime.tv_sec'] + ($timing['rubefore']['ru_utime.tv_usec']/1000000.);
$ua = $timing['ruafter']['ru_utime.tv_sec'] + ($timing['ruafter']['ru_utime.tv_usec']/1000000.);
printf("User: %.3f\n", $ua - $ub);

$sb = $timing['rubefore']['ru_stime.tv_sec'] + ($timing['rubefore']['ru_stime.tv_usec']/1000000.);
$sa = $timing['ruafter']['ru_stime.tv_sec'] + ($timing['ruafter']['ru_stime.tv_usec']/1000000.);
printf("System: %.3f\n", $sa - $sb);
printf("Memory, peak usage, emalloc(): %d [kb]\n", memory_get_peak_usage()/1024);
printf("Memory, peak usage: OS: %d [kb]\n", memory_get_peak_usage()/1024);

printf("\n####\n");

foreach ($rusage as $k => $v)
	printf("%s: %s\n", $k, $v);
