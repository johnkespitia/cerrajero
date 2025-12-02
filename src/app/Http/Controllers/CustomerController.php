<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule; 
use App\Http\Controllers\Controller;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Buscar por DNI o NIT (para clientes corporativos)
        if ($request->has('dni')) {
            $document = $request->dni;
            return Customer::where(function($query) use ($document) {
                $query->where('dni', $document)
                      ->orWhere('company_nit', $document);
            })->with('kiosk_invoices')->first();
        }

        return Customer::with("kiosk_invoices")->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $customerType = $request->input('customer_type', 'person');
        
        $rules = [
            'customer_type' => 'required|in:person,company',
            'email' => 'required|email|unique:customers,email',
            'phone_number' => 'nullable|string|max:20',
            'active' => 'boolean',
        ];

        if ($customerType === 'person') {
            $rules['dni'] = 'required|unique:customers,dni|string|max:20';
            $rules['name'] = 'required|string|max:100';
            $rules['last_name'] = 'required|string|max:100';
        } else {
            $rules['company_name'] = 'required|string|max:200';
            $rules['company_nit'] = 'required|unique:customers,company_nit|string|max:50';
            $rules['company_legal_representative'] = 'required|string|max:200';
            $rules['company_address'] = 'nullable|string';
        }

        $request->validate($rules);
    
        $customer = Customer::create($request->all());
        return response()->json($customer, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        return $customer;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $customerType = $request->input('customer_type', $customer->customer_type ?? 'person');
        
        $rules = [
            'customer_type' => 'required|in:person,company',
            'email' => ['required', 'email', Rule::unique('customers')->ignore($customer->id)],
            'phone_number' => 'nullable|string|max:20',
            'active' => 'boolean',
        ];

        if ($customerType === 'person') {
            $rules['dni'] = ['required', Rule::unique('customers')->ignore($customer->id), 'string', 'max:20'];
            $rules['name'] = 'required|string|max:100';
            $rules['last_name'] = 'required|string|max:100';
        } else {
            $rules['company_name'] = 'required|string|max:200';
            $rules['company_nit'] = ['required', Rule::unique('customers')->ignore($customer->id), 'string', 'max:50'];
            $rules['company_legal_representative'] = 'required|string|max:200';
            $rules['company_address'] = 'nullable|string';
        }

        $request->validate($rules);
    
        $customer->update($request->all());
        return response()->json($customer, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        $customer->delete();
        return response()->json(null, 204);
    }
}