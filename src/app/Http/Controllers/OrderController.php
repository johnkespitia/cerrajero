<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Order;
use App\OrderHistory;
use App\OrderPrices;
use App\OrderItemShipping;
use App\User;
use App\OrderStatus;
use App\ProductPresentationProviderPrice;
use App\ProductPriceStatus;
use App\OrderPayment;
use App\Product;
use App\PaymentHistory;
use App\Cupon;
use Illuminate\Support\Facades\Auth;
use App\Mail\OrderGenerate;
use Illuminate\Support\Facades\Mail;
class OrderController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $cart = $request->input("cart");
        $address = $request->input("address");
        $payment = $request->input("payment");
        $shippingCost = $request->input("shippingCost");
        $couponRequest = $request->input("coupon");
        $shippingComment = $request->input("shippingComment");
        if(!empty($couponRequest)){
            $coupon = Cupon::where("id",$couponRequest["id"])
            ->where("uses",">","0")
            ->where("active","1")
            ->where("expiration_date",">=", date("Y-m-d"))
            ->first();
        }
        if(\sizeof($cart)<1){
            return response()->json(["errors" => "Orden sin Productos, agregur productos al carro e intente nuevamente"  ] , 403);
        }
        if(!$address){
            return response()->json(["errors" => "No ha seleccionado la dirección de envío"  ] , 403);
        }
        if(!$payment){
            return response()->json(["errors" => "No ha seleccionado método de pago"  ] , 403);
        }
        if(!$shippingCost){
            return response()->json(["errors" => "Verifique la dirección de entrega"  ] , 403);
        }
        if(!empty($couponRequest) && empty($coupon)){
            return response()->json(["errors" => "Error al aplicar el cupón por favoer verifique de nuevo el cupón"  ] , 403);
        }
        $user = User::with('customer.addresses')->with('rol')->find($request->user()->id);
        $orderNew = new Order;
        $orderNew->customer_id=$user->customer->id;
        $orderNew->status_id = OrderStatus::where("name","Pendiente de Pago")->first()->id;
        $orderNew->address_id = $address["id"];
        $orderNew->total="0";
        $orderNew->save();

        $history = new OrderHistory;
        $history->order_id=$orderNew->id;
        $history->order_statuses_id=$orderNew->status_id;
        $history->save();
        
        $paymentRegister = new OrderPayment;
        $paymentRegister->order_id = $orderNew->id;
        $paymentRegister->payment_method_id = $payment["id"];
        $paymentRegister->payed = false;
        $paymentRegister->payment_reference = "";
        $paymentRegister->payed_value = 0;
        $paymentRegister->save();

        $total = 0;
        $total_shipping = 0;
        $provider_filter=[];
        foreach ($cart as $key => $prd) {
            $prprice = ProductPresentationProviderPrice::find($prd["product_price_selected"]);
            $shipping_cost_product = current(array_filter($shippingCost,function($it) use($prd){
                if(env("TYPE_SHIPPING")=="PER_PRODUCT"){
                    return $this->compareShippingByProduct($it,$prd);
                }else{
                    return $this->compareShippingByProvider($it,$prd);
                }
                
            }));
            if($shipping_cost_product){
                for($i=0;$i< $prd["quantity"]; $i++){
                    $item = new OrderPrices;
                    $item->order_id=$orderNew->id;
                    $item->product_price_id=$prd["product_price_selected"];
                    $item->price=$prprice->price;
                    $item->price_provider=$prprice->price_provider;
                    $item->tax = $prprice->price*0.19;
                    $item->quantity=1;
                    $item->comment_item=$prd["observation"];
                    $item->total_product=($item->price*$item->quantity);
                    $item->price_status_id=ProductPriceStatus::where("name","Comprado")->first()->id;
                    $item->save();
                    $total+=$item->total_product;
                    
                    $shiping= new OrderItemShipping();
                    if(env("TYPE_SHIPPING")=="PER_PRODUCT"){
                        $shiping->price_shipping = $shipping_cost_product["rate"]["total"];    
                    }else{
                        if(!in_array($prprice->provider_id,$provider_filter)){
                            $shiping->price_shipping = $shipping_cost_product["rate"]["total"];
                            $provider_filter[] = $prprice->provider_id;
                        }else{
                            $shiping->price_shipping = 0;
                        }
                    }
                    
                    $shiping->shipping_status = "RESERVADO";
                    $shiping->order_price_id = $item->id;
                    $shiping->quotation_id = $shipping_cost_product["rate"]["idRate"];
                    $shiping->save();
                    $total_shipping += $shiping->price_shipping;
                }
            }
        }
        
        $orderNew->total=($total+$total_shipping);

        $orderNew->save();
        $payedValue = 0;
        if(!empty($couponRequest) && !empty($coupon)){
            $paymentRegister = new OrderPayment;
            $paymentRegister->order_id = $orderNew->id;
            $paymentRegister->payment_method_id = 3;
            $paymentRegister->payed = true;
            $paymentRegister->payment_reference = $coupon->name." - ".$coupon->id;
            if($coupon->type == "percentage"){
                $payedValue = round($coupon->value*$total/100,0);
            }else{
                if($coupon->value>$total){
                    $payedValue =  $total;
                }else{
                    $payedValue = $coupon->value;
                }
            }
            $paymentRegister->payed_value = $payedValue;
            $paymentRegister->save();
            $coupon->uses = $coupon->uses - 1;
            $coupon->save();
        }

        if($paymentRegister->payment_method_id == 2){
            \MercadoPago\SDK::setAccessToken(env('MP_ACCESS_TOKEN'));
            // Crea un objeto de preferencia
            $preference = new \MercadoPago\Preference();
            $preference->back_urls = array(
                "success" => env("APP_URL")."payment/approved/".$orderNew->id,
                "failure" => env("APP_URL")."payment/failed/".$orderNew->id,
                "pending" => env("APP_URL")."payment/pending/".$orderNew->id
            );
            // Crea un ítem en la preferencia
            $item = new \MercadoPago\Item();
            $item->title = 'Compra Campo Verde ID '.$orderNew->id;
            $item->quantity = 1;
            $item->currency_id = "COP";
            $item->unit_price = intval(($orderNew->total-$payedValue));
            $preference->items = array($item);
            $preference->auto_return = "approved";
            $preference->save();
            $paymentRegister->payment_reference =  $preference->id;
            $paymentRegister->payment_endpoint =  $preference->init_point;
            $paymentRegister->save();
        }
        Mail::to($user->email)->send(new OrderGenerate($orderNew, $user, $address));
        Mail::to(env("MAIL_FROM_ADDRESS"))->send(new OrderGenerate($orderNew, $user, $address));
        Mail::to(env("MAIL_FROM_ADDRESS2"))->send(new OrderGenerate($orderNew, $user, $address));
        return response()->json($orderNew, 201);
    }

    /**
     * List all orders
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function list(){
        $user = User::with('customer.addresses')->with('rol')->find( Auth::id());
        $orderList = Order::where("customer_id",$user->customer->id)
            ->orderBy("id","DESC")
            ->with('address')
            ->with('payments.paymentMethod')
            ->with('status')
            ->with("ticket.messages")
            ->with('history')
            ->with('items.shipping')
            ->with('items.productPrice.presentation.product.images')
            ->with('items.status')
            ->get();
        return response()->json($orderList, 200);
        
    }

    /**
     * List all orders
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function listAdmin(){
        $orderList = Order::orderBy("id","DESC")
            ->with('address.city')
            ->with('payments.paymentMethod')
            ->with('status')
            ->with("ticket.messages")
            ->with('customer.User')
            ->with('history')
            ->with('items.shipping')
            ->with('items.productPrice.presentation.product.images')
            ->with('items.productPrice.provider')
            ->with('items.status')
            ->limit(100)
            ->get();
        return response()->json($orderList, 200);
        
    }

    /**
     * List all orders
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function get($order){
        $user = User::with('customer.addresses')->with('rol')->find( Auth::id());
        $orderList = Order::where("customer_id",$user->customer->id)
            ->where("id",$order)
            ->with('address')
            ->with('payments.paymentMethod')
            ->with('status')
            ->with("ticket.messages")
            ->with('history')
            ->with('items.shipping')
            ->with('items.productPrice')
            ->with('items.status')
            ->first();
        return response()->json($orderList, 200);
        
    }


    public function approvePayment(Request $request, Order $order){
        $payment = OrderPayment::where("order_id",$order->id)->where("payed",0)->where("payment_reference",$request->input("preference_id"))->first();
        if($payment){
            $result = print_r($request->toArray(),true);
            $ph = new PaymentHistory();
            $ph->payment_id = $payment->id;
            $ph->status = $request->input("status");
            $ph->result = $result;
            $ph->save();
            $payment->payed = ($ph->status == "approved")?1:0;
            $payment->payed_value = $order->total;
            $payment->save();
            $order->status_id = OrderStatus::where("name","Comprado")->first()->id;
            $order->save();
            return \Redirect::to(env("SITE_URL").'account/order/'.$order->id);
        }else{
            return \Redirect::to(env("SITE_URL").'404');
        }
        
    }

    public function pendingPayment(Request $request, Order $order){
        $payment = OrderPayment::where("order_id",$order->id)->where("payed",0)->where("payment_reference",$request->input("preference_id"))->first();
        if($payment){
            $result = print_r($request->toArray(),true);
            $ph = new PaymentHistory();
            $ph->payment_id = $payment->id;
            $ph->status = $request->input("status");
            $ph->result = $result;
            $ph->save();
            return \Redirect::to(env("SITE_URL").'account/order/'.$order->id);
        }else{
            return \Redirect::to(env("SITE_URL").'404');
        }
    }

    public function failedPayment(Request $request, Order $order){
        
        $payment = OrderPayment::where("order_id",$order->id)->where("payed",0)->where("payment_reference",$request->input("preference_id"))->first();
        if($payment){
            $result = print_r($request->toArray(),true);
            $ph = new PaymentHistory();
            $ph->payment_id = $payment->id;
            $ph->status = "RECHAZADO";
            $ph->result = $result;
            $ph->save();
            $order->status_id = OrderStatus::where("name","Rechazado")->first()->id;
            $order->save();
            return \Redirect::to(env("SITE_URL").'account/order/'.$order->id);
        }else{
            return \Redirect::to(env("SITE_URL").'404');
        }
    }

    protected function compareShippingByProduct($shippingObject, $product){
        return (
            isset($shippingObject['product_id']) && $shippingObject['product_id']==$product["product"]["id"] && 
            $shippingObject['product_presentation_selected']==$product["product_presentation_selected"] && 
            $shippingObject['product_price_selected']==$product["product_price_selected"] 
        );
    }

    protected function compareShippingByProvider($shippingObject, $product){
        $selected_presentation = current(array_filter($product["product"]["presentations"],function($it) use($product){
            return ($it['id']==$product["product_presentation_selected"]);
        }));
        $selecte_price = current(array_filter($selected_presentation["prices"],function($it) use($product){
            return ($it['id']==$product["product_price_selected"]);
        }));
        return (
            isset($shippingObject['provider_id']) && $shippingObject['provider_id']==$selecte_price["provider_id"]
        );
    }
}
