<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use App\Customer;
use App\Rol;
use App\DocumentType;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Mail\NewUserNotification;
use Illuminate\Support\Facades\Mail;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try{
            return Customer::with('user')->with("addresses.city")->with("tipo_documento")->get();
        }catch (\Exception $exception){
            return response()->json(["errors" => "Server Error {$exception->getMessage()}"  ] , 403);
        }

    }

    /**
     * Store a newly created customer in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //validation rules
        $validation_rules = [
            "email" => 'required|email|unique:users,email',
            "document" => 'numeric',
            "nombre" => 'required' ,
            "apellido" => 'required' ,
            "genero" => 'in:m,f,n',
            "phone" => 'required',
            "tipo_doc" => 'in:CC,TI,CE' ,
            "password" => 'required' ,
            "password_validation" => 'required|same:password'
        ];
        //validation
        $validador = Validator::make($request->all() , $validation_rules);
        if($validador->fails()){
            return response()->json(['errors'=>$validador->errors()], 400);
        }
        
        try{
            //Create User
            $user =  User::create(
                [
                    'name' => $request->input('username'),
                    'username' => $request->input('username'),
                    'email' => $request->input('email'),
                    'password' => Hash::make($request->input('password') ),
                    'rol_id' => Rol::where("name" , "customer")->first()->id,
                    'phone' => $request->input('phone'),
                ]
            );
            //Create Customer
            $customer = new Customer();
            $customer->user_id = $user->id;
            $customer->document = $request->input('document');
            $customer->nombre= $request->input('nombre');
            $customer->apellido = $request->input('apellido');
            $customer->genre = $request->input('genero');
            $customer->state = 1;
            $customer->tipo_doc_id =  DocumentType::where("tipo_documento" , $request->input('tipo_doc'))->first()->id;
            $customer->save();
            $user->generateToken();
            //Response
            Mail::to($user->email)->send(new NewUserNotification($user));
            return response()->json(User::with('customer.addresses')->with('rol')->find($user->id), 201);
        }catch(\Exception $exception){
            //Database and many other exceptions
            if(isset($customer)){
                $customer->delete();
            }
            if(isset($user)){
                $user->delete();
            }
            return response()->json(["errors" => ["Server Error"=>$exception->getMessage()]  ] , 400);
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try{

            return Customer::with('addresses')->find($id);

        }catch(\Exception $exception){
            //Database and many other exceptions
            return response()->json(["errors" => "Server Error"  ] , 403);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request,Customer $customer)
    {
        //validation rules
        $validation_rules = [
            "email" => 'email|unique:users,email',
            "document" => 'numeric',
            "genero" => 'in:m,f,n',
            "tipo_doc" => 'in:CC,TI,CE' ,
            "password_validation" => 'same:password'
        ];
        //validation
        $validador = Validator::make($request->all() , $validation_rules);
        if($validador->fails()){
            return response()->json(['errors'=>$validador->errors()], 400);
        }

        //update customer and user:
        try{
            //update customer
            $customer->nombre= $request->input('nombre')??$customer->nombre;
            $customer->apellido= $request->input('apellido')??$customer->apellido;
            $customer->genre= $request->input('genero')??$customer->genre;
            $customer->state = $request->input('state')??$customer->state;
            $customer->document= $request->input('document')??$customer->document;
            if($request->input("tipo_doc")){
                $customer->tipo_doc_id = DocumentType::where("tipo_documento" ,
                                                   $request->input('tipo_doc'))
                                                    ->first()
                                                    ->id;
            }
            $customer->save();
            //update Customer´s user
            $user = $customer->User;
            $user->email = $request->input('email')??$user->email;
            if($request->input('password') && $request->input('password_validation') ){
                $user->password = Hash::make($request->input('password'));
            }
            if($request->input('phone') ){
                $user->phone = $request->input('phone');
            }
            $user->save();

            return response()->json(User::with('customer.addresses')->with('rol')->find($user->id), 201);



        }catch(\Exception $exception){

            return response()->json(['errors'=>$exception->getMessage()], 403);
        }
    }



    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
