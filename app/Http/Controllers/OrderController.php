<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;

class OrderController extends Controller
{
    public function getByUserId(Request $request)
    {
        $userId = $request->input("user_id");
        $order = Order::query();

        $order->when(isset($userId), function (Builder $query) use ($userId){
            $query->where("user_id", $userId);
        });

        return response()->json([
            "code" => 200,
            "status" => "OK",
            "data" => $order
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->input("user");
        $course = $request->input("course");

        $order = Order::create([
            "user_id" => $user["id"],
            "course_id" => $course["id"]
        ]);

        // midtrans payload
        $transactionDetails = [
            "order_id" => Str::random(10) . $order["id"],
            "gross_amount" => $course["price"],
        ];

        $item_details = [
            "id" => $course["id"],
            "price" => $order["price"],
            "quantity" => 1,
            "name" => $course["name"],
            "brand" => "ManBellCourses",
            "category" => "Online Course"
        ];

        $customerDetails = [
            "first_name" => $user["name"],
            "email" => $user["email"]
        ];

        $midtransParams = [
            "transaction_details" => $transactionDetails,
            "item_details" => $item_details,
            "customer_details" => $customerDetails
        ];

        // get midtrans snap_url
        $midtransSnapUrl = $this->getMidtransSnapUrl($midtransParams);

        $order->snap_url = $midtransSnapUrl;

        $order->metadata = [
            "course_id" => $course["id"],
            "course_name" => $course["name"],
            "course_price" => $course["price"],
            "course_thumbnail" => $course["thumbnail"],
            "course_level" => $course["level"],
        ];

        $order->save();

        return response()->json([
            "code" => 201,
            "status" => "Created",
            "data" => $order
        ], 201);
    }
    public function getMidtransSnapUrl($params)
    {
        // Set your Merchant Server Key
        Config::$serverKey = getenv("MIDTRANS_SERVER_KEY");

        // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
        Config::$isProduction = boolval(getenv("MIDTRANS_PRODUCTION"));

        // Set 3DS transaction for credit card to true
        Config::$is3ds = boolval(getenv("MIDTRANS_3DS"));

        return Snap::createTransaction($params)->redirect_url;
    }
}
