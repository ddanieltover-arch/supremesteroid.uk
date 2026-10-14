<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Services\Storefront\HomepageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, HomepageService $homepage): Response
    {
        return Inertia::render('Home', $homepage->page($request));
    }
}
