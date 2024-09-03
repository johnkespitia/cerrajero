<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Order;
use App\User;
use App\Ticket;
use App\Message;
use Illuminate\Support\Facades\Auth;
use App\Mail\CustomerService;
use Illuminate\Support\Facades\Mail;
class CustomerServiceController extends Controller
{
    /**
     * Store a newly created customer in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        
            $ticket = new Ticket();
            $ticket->client_id = Auth::id();
            $ticket->status = "OPEN";
            $ticket->order_id = $request->input("order_id");
            $ticket->save();
            $message = new Message();
            $message->ticket_id = $ticket->id;
            $message->message = $request->input("message");
            $message->author = null;
            $message->save();
            Mail::to(env("MAIL_FROM_ADDRESS"))->send(new CustomerService($ticket->order_id, Auth::user(), $ticket, $message));
            Mail::to(env("MAIL_FROM_ADDRESS2"))->send(new CustomerService($ticket->order_id, Auth::user(), $ticket, $message));

            return response()->json(Ticket::with('messages')->where("order_id",$ticket->order_id)->first(), 201);
        

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function showByOrder($id)
    {
        try{
            return Ticket::with('messages')->where("order_id",$id)->first();
        }catch(\Exception $exception){
            return response()->json(["errors" => "Server Error"] , 403);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function newMessageClient(Request $request)
    {
        try{
            $message = new Message();
            $message->ticket_id = $request->input("ticket_id");
            $message->message = $request->input("message");
            $message->author = null;
            $message->save();
            return response()->json($message, 201);
        }catch(\Exception $exception){
            return response()->json(["errors" => "Server Error"  ] , 403);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function newMessageAdmin(Request $request)
    {
        try{
            $message = new Message();
            $message->ticket_id = $request->input("ticket_id");
            $message->message = $request->input("message");
            $message->author = Auth::name();
            $message->save();
            return response()->json($message, 201);
        }catch(\Exception $exception){
            return response()->json(["errors" => "Server Error"  ] , 403);
        }
    }
}
