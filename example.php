<?php
require 'xslib-memcached.php';

// This is an example file to demonstrate how to use the xsMemcached library

// Connect to a memcached server. Amend the IP and port to your settings
if(!$m = xsMemcached::Connect('192.168.100.9', 21201))
{
	die('Failed to connect. This is where you should handle errors gracefully.');
}

// Make the text output a bit prettier in the browser
header('Content-Type: text/plain');

// Set foo to bar
$m->Set('foo', 'bar');

// Print foo. This is the most basic example of how to use this. The rest if to demonstrate other features
print 'foo = ' . $m->Get('foo') . "\r\n";

// Create a counter that starts from 0, but only if it does not exist (add = set if not exists)
$m->Add('counter1', 0);

// This counter should go 1,2,3,4,5,6,7,8,9,10,9,8,7,6,5,4,3,2,1,0,1... (see downflag)
$m->Add('counter2', 0);

// Increment counter1
$m->Incr('counter1');

// If downflag exists, decrement counter2. Otherwise, increment it
if($m->Get('downflag'))
	$m->Decr('counter2');
else
	$m->Incr('counter2');


print 'counter1 = ' . $m->Get('counter1') . "\r\n";
print 'counter2 = ' . $m->Get('counter2') . "\r\n";

$c = $m->Get('counter2');

if($c > 9)
	$m->Set('downflag', 1);
elseif($c < 1)
	$m->Delete('downflag');

// Close the connection. This is not necessary as PHP closes sockets anyways on shutdown anyways
$m->Quit();