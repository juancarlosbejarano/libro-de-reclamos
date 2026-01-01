<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Http\Kernel;
use App\Http\Request;

$request = Request::fromGlobals();
$kernel = new Kernel();
$response = $kernel->handle($request);
$response->send();
