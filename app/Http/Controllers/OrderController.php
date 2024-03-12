<?php

namespace App\Http\Controllers;

use App\Models\Order;
use http\Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            "data" => $order->get()
        ]);
    }

    public function create(Request $request)
    {
        try{
            $user = $request->input("user");
            $course = $request->input("course");

            DB::beginTransaction();
            $order = Order::create([
                "user_id" => $user["id"],
                "course_id" => $course["id"]
            ]);

//         midtrans payload
            $transaction_details = [
                "order_id" => $order["id"]."-".Str::random(7),
                "gross_amount" => $course["price"],
            ];

            $item_details = [
                [
                    "id" => $course["id"],
                    "price" => $course["price"],
                    "quantity" => 1,
                    "name" => $course["name"],
                    "brand" => "ManBellCourses",
                    "category" => "Online Course"
                ]
            ];

            $customer_details = [
                "first_name" => $user["name"],
                "email" => $user["email"]
            ];

            $midtransParams = [
                "transaction_details" => $transaction_details,
                "item_details" => $item_details,
                "customer_details" => $customer_details
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
            DB::commit();

            return response()->json([
                "code" => 201,
                "status" => "Created",
                "data" => $order
            ], 201);

        } catch (\Exception $exception){
            DB::rollBack();
            return [
                "code" => 500,
                "status" => "Internal Server Error",
                "errors" => [
                    "message" => $exception->getMessage()
                ]
            ];
        }
    }

    public function getMidtransSnapUrl(array $request)
    {
        // Set your Merchant Server Key
        Config::$serverKey = env("MIDTRANS_SERVER_KEY");

        // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
        // Kalo nilai dari .env ingin dikonveri, gunakan function env()
        Config::$isProduction = (bool) env("MIDTRANS_PRODUCTION");

        // Set 3DS transaction for credit card to true
        Config::$is3ds = (bool) env("MIDTRANS_3DS");

        return Snap::createTransaction($request)->redirect_url;
    }
}
