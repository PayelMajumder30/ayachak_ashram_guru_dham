<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Interfaces\CartInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Cart;
use App\Models\CartOffer;
use App\Models\ProductColorSize;
use App\Models\Coupon;
use Illuminate\Support\Facades\Auth;
use DB;

class CartController extends Controller
{
    public function __construct(CartInterface $cartRepository) 
    {
        $this->cartRepository = $cartRepository;
    }

    public function couponCheck(Request $request)
    {
        $couponData = $this->cartRepository->couponCheck($request->code);
        return $couponData;
    }

    public function couponRemove(Request $request)
    {
        $couponData = $this->cartRepository->couponRemove();
        return $couponData;
    }

    public function add(Request $request) 
    {
        // dd($request->all());

        $request->validate([
            "product_id" => "required|string|max:255",
            "product_name" => "required|string|max:255",
            "product_style_no" => "required|string|max:255",
            "product_image" => "required|string|max:255",
            "product_slug" => "required|string|max:255",
            "product_variation_id" => "nullable|integer",
            "price" => "required|string",
            "offer_price" => "required|string",
            "qty" => "required|integer|min:1",
            "user_id" => "nullable",
            "token" => "nullable"
        ]);

        $params = $request->except('_token');

        $cartStore = $this->cartRepository->addToCart($params);

        if ($cartStore) {
            return response()->json(['status' => 200, 'message' => 'Product added to cart', 'response' => $cartStore]);
            // return redirect()->back()->with('success', 'Product added to cart');
        } else {
            return response()->json(['status' => 400, 'message' => 'Product cannot be added to cart']);
            // return redirect()->back()->with('failure', 'Something happened');
        }
    }

    public function index(Request $request)
    {
       if(Auth::guard('web')->check()){
           $total_price_excluding = 0;
            $userId =Auth::guard('web')->user()->id;
            $mobile = Auth::guard('web')->user()->mobile;
            $cartProductDetails = Cart::with('productDetails')->latest()->where('user_id',$userId)->get();
            $couponData = Coupon::where('user_mobile', $mobile)->take(5)->where('status', 1)->get();
            if(count($cartProductDetails)){
                foreach($cartProductDetails as $key =>$item){
                     $price = $item->offer_price?$item->offer_price:$item->price;
                     $total_price_excluding += $price;
                }
            }
            return view('front.cartList',compact('cartProductDetails','couponData', 'total_price_excluding'));
       }else{
            return redirect()->route('front.user.login');
       }
    }
    public function add_to_checkoout(Request $request){
        $userId = Auth::guard('web')->user()->id;
        $exist_checkout = DB::table('checkout')->where('user_id', $userId)->first();
        if ($exist_checkout) {
            DB::table('checkout')->where('user_id', $userId)->delete();
            DB::table('checkout_products')->where('user_id', $userId)->delete();
        }
        $data = DB::table('checkout')->insert([
            'user_id' => $userId,
            'sub_total_amount' => $request->input('final_sub_total'), // Assuming you have a 'product_id' in your request
            'discount_amount' => $request->input('final_coupon_amount'), // Assuming you have a 'quantity' in your request
            'gst_amount' => $request->input('final_gst_amount'), // Assuming you have a 'total_amount' in your request
            'final_amount' => $request->input('final_total_amount')
        ]);
        if($data){
            $checkout = DB::table('checkout')->where('user_id', $userId)->first();
            $sub_total_amount = 0;
            $total_discount_amount = 0;
            $total_gst_amount = 0;
            $total_final_amount = 0;
            $count = 0;
            foreach($request->variation as $key =>$item){
                $ProductColorSize = ProductColorSize::where('id', $item)->first();
                if($ProductColorSize){
                    $price = $ProductColorSize->offer_price?$ProductColorSize->offer_price:$ProductColorSize->price;
                    $gst = $ProductColorSize->productDetails?$ProductColorSize->productDetails->gst:0;
                    // Calculate GST amount
                    $gst_amount = ($price * $gst) / 100;
                    // Accumulate GST amount for all items
                    $total_gst_amount += $gst_amount;
                    // Calculate price excluding GST for the current item
                    // $price_excluding_gst = $price - $gst_amount;
                    // Accumulate price excluding GST for all items
                    // $total_price_excluding_gst += $price_excluding_gst;
                    $sub_total_amount += $price;
                    $discount_amount =$request->coupons[$key]?100:0;
                    $total_discount_amount += $discount_amount;
                    $final_amount = $price-$discount_amount;
                    $total_final_amount +=$final_amount;

                    $data = DB::table('checkout_products')->insert([
                        'user_id' => $userId,
                        'checkout_id' => $checkout->id, 
                        'product_id' => $ProductColorSize->product_id, 
                        'product_name' => $ProductColorSize->productDetails?$ProductColorSize->productDetails->name:"", 
                        'product_image' => $request->images[$key], 
                        'product_slug' => $ProductColorSize->productDetails?$ProductColorSize->productDetails->slug:"", 
                        'product_variation_id' => $item, 
                        'colour_name' => $ProductColorSize->color_name, 
                        'size_name' => $ProductColorSize->color_name, 
                        'sku_code' => $ProductColorSize->code,
                        'coupon_code' => $request->coupons[$key], 
                        'price' => $ProductColorSize->price,
                        'offer_price' => $ProductColorSize->offer_price,
                        'gst' => $gst_amount, 
                        'qty' => 1, 
                    ]);
                    $count += 1;
                }
            }
            DB::table('checkout')
            ->where('user_id', $userId) // Assuming $checkoutId is the ID of the record you want to update
            ->update([
                'sub_total_amount' => $sub_total_amount,
                'discount_amount' => $total_discount_amount,
                'gst_amount' => $total_gst_amount,
                'final_amount' => $total_final_amount
            ]);
            return redirect()->route('front.checkout.index')->with('success', ''.$count.' items successfully added');
        }
    }

    /*
    public function viewByIp(Request $request)
    {
        $data = $this->cartRepository->viewByIp();

        $currentDate = date('Y-m-d');
        $cartOffers = CartOffer::where('status', 1)->whereRaw("date(valid_from) <= '$currentDate' AND date(valid_upto) >= '$currentDate'")->orderBy('min_cart_order', 'desc')->get();

        if ($data) {
            return view('front.cart.index', compact('data', 'cartOffers'));
        } else {
            return view('front.404');
        }
    }
    */

    public function delete(Request $request, $id)
    {
        $data = $this->cartRepository->delete($id);

        if ($data) {
            return redirect()->route('front.cart.index')->with('success', 'Product removed from cart');
        } else {
            return redirect()->route('front.cart.index')->with('failure', 'Something happened wrong');
        }
    }

    public function qtyUpdate(Request $request, $id, $type)
    {
        $data = $this->cartRepository->qtyUpdate($id, $type);

        if ($data) {
            return redirect()->route('front.cart.index')->with('success', $data);
        } else {
            return redirect()->route('front.cart.index')->with('failure', 'Something happened');
        }
    }
}
