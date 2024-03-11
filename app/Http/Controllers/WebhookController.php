<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    public function midtransWebhook(Request $request)
    {
        try {
            $req = $request->all();
            $signatureKey = $req["signature_key"];

            $oriSignatureKey = hash(
                "sha512",
                $req["order_id"] . $req["status_code"] . $req["gross_amount"] . getenv("MIDTRANS_SERVER_KEY")
            );

            $transactionStatus = $req["transaction_status"];
            $paymentType = $req["payment_type"];
            $fraudStatus = $req["fraud_status"];

            if ($signatureKey !== $oriSignatureKey) {
                return response([
                    "code" => 400,
                    "status" => "Bad Request",
                    "errors" => [
                        "message" => "Invalid signature key"
                    ]
                ]);
            }

            $oriOrderId = explode("-", $req["order_id"]);
            $order = Order::find(intval($oriOrderId[0]));

            if (!$order) {
                return response([
                    "code" => 404,
                    "status" => "Not Found",
                    "errors" => [
                        "message" => "Order not found"
                    ]
                ]);
            }

            if ($transactionStatus == 'capture') {
                if ($fraudStatus == 'accept') {
                    // TODO set transaction status on your database to 'success'
                    $order->status = "success";
                }
            } else if ($transactionStatus == 'settlement') {
                // TODO set transaction status on your database to 'success'
                $order->status = "success";
            } else if ($transactionStatus == 'cancel' ||
                $transactionStatus == 'deny' ||
                $transactionStatus == 'expire') {
                // TODO set transaction status on your database to 'failure'
                $order->status = "failure";
            } else if ($transactionStatus == 'pending') {
                // TODO set transaction status on your database to 'pending' / waiting payment
                $order->status = "pending";
            }

            $paymentLog = [
                "status" => $transactionStatus,
                "payment_type" => $paymentType,
                "raw_response" => json_encode($req),
                "order_id" => $oriOrderId[0],
            ];

            DB::beginTransaction();

            PaymentLog::create($paymentLog);
            $order->save();

            DB::commit();

            $myCourse = [];
            $purchasedCourse = collect($myCourse);
            if ($order->status === "success") {
                $myCourse = createPremiumAccess([
                    "user_id" => $order->user_id,
                    "course_id" => $order->course_id
                ]);

                $purchasedCourse->add($myCourse);
            }

            return response()->json([
                "code" => 200,
                "status" => "OK",
                "data" => $purchasedCourse->all()
            ]);

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
}
