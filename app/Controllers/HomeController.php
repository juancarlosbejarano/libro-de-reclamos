<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Views\View;

final class HomeController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('home'));
    }
}
