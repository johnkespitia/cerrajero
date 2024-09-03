<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Banner;
use App\Mail\TestMail;
use App\Mail\ContactUsMail;
use Illuminate\Support\Facades\Mail;

class HomeController extends Controller
{

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    /**
     * getBanners
     *
     */
    public function banners()
    {
        return response()->json(Banner::where('status', 1)->orderBy("order", "asc")->get(), 200);
    }

    public function healthcheck()
    {
        Mail::to("jcespitia1@gmail.com")->send(new TestMail());
        return view('home');
    }
    public function sendMessage(Request $request)
    {
        Mail::to(env("MAIL_FROM_ADDRESS"))->send(new ContactUsMail($request->post()));
        Mail::to(env("MAIL_FROM_ADDRESS2"))->send(new ContactUsMail($request->post()));
        return response()->json(["msg" => "OK"]);
    }
}
