<?php

namespace App\Http\Controllers;

use App\Services\SiteContentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class SiteContentController extends Controller
{
    public function __construct(private SiteContentService $siteContentService)
    {
    }

    public function show()
    {
        return response($this->siteContentService->getAdminContent(), Response::HTTP_OK);
    }

    public function update(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'hero_slides' => 'nullable|array',
            'hero_slides.*.image_url' => 'required_with:hero_slides|string|max:500',
            'hero_slides.*.title' => 'nullable|string|max:200',
            'hero_slides.*.plan_title' => 'nullable|string|max:200',
            'hero_slides.*.plan_items' => 'nullable|array',
            'hero_slides.*.plan_items.*' => 'nullable|string|max:200',
            'hero_slides.*.cta_text' => 'nullable|string|max:100',
            'hero_slides.*.cta_link' => 'nullable|string|max:500',
            'home_video_url' => 'nullable|string|max:500',
            'home_video_poster_url' => 'nullable|string|max:500',
            'about_gallery' => 'nullable|array',
            'about_gallery.*.image_url' => 'required_with:about_gallery|string|max:500',
            'about_gallery.*.caption' => 'nullable|string|max:200',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $payload = $request->only([
            'hero_slides',
            'home_video_url',
            'home_video_poster_url',
            'about_gallery',
        ]);

        $content = $this->siteContentService->updateContent($payload);

        return response($content, Response::HTTP_OK);
    }
}
