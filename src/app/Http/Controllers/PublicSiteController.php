<?php

namespace App\Http\Controllers;

use App\Services\SiteContentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicSiteController extends Controller
{
    public function __construct(private SiteContentService $siteContentService)
    {
    }

    public function content()
    {
        return response($this->siteContentService->getPublicContent(), Response::HTTP_OK);
    }
}
