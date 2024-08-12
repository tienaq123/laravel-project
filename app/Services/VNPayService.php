<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Request;

class VNPayService
{
    public function createPaymentUrl($order)
    {

        $vnp_Url = env('VNPAY_URL');
        $vnp_Returnurl = env('VNPAY_RETURN_URL');
        $vnp_TmnCode = env('VNPAY_TMN_CODE'); //Mã website tại VNPAY
        $vnp_HashSecret =  env('VNPAY_HASH_SECRET'); //Chuỗi bí mật

        $vnp_TxnRef =  9878; //Mã đơn hàng
        $vnp_OrderInfo = "Thanh toán hóa đơn cho đơn hàng " . $order->id; //nội dung thanh toán
        $vnp_OrderType = "billpayment";
        $vnp_Amount = $order->total_amount * 100;
        $vnp_Locale = "vn";
        $vnp_BankCode = "";
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];

        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_Returnurl,
            "vnp_TxnRef" => $vnp_TxnRef,
        );

        if (isset($vnp_BankCode) && $vnp_BankCode != "") {
            $inputData['vnp_BankCode'] = $vnp_BankCode;
        }
        if (isset($vnp_Bill_State) && $vnp_Bill_State != "") {
            $inputData['vnp_Bill_State'] = $vnp_Bill_State;
        }

        //var_dump($inputData);
        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url = $vnp_Url . "?" . $query;
        if (isset($vnp_HashSecret)) {
            $vnpSecureHash =   hash_hmac('sha512', $hashdata, $vnp_HashSecret); //
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }
        $returnData = array(
            'code' => $order->id,
            'message' => 'success',
            'data' => $vnp_Url
        );

        return $returnData;
        // if (isset($_POST['redirect'])) {
        //     header('Location: ' . $vnp_Url);
        //     die();
        // } else {
        //     echo json_encode($returnData);
        // }
    }


    public function handleReturn($request)
    {
        $vnp_HashSecret =  env('VNPAY_HASH_SECRET'); // Chuỗi bí mật của bạn
        $inputData = $request->all();

        // Kiểm tra xem khóa 'vnp_SecureHash' có tồn tại hay không
        if (!isset($inputData['vnp_SecureHash'])) {
            return ['status' => false, 'message' => 'vnp_SecureHash is missing'];
        }

        $vnp_SecureHash = $inputData['vnp_SecureHash'];
        unset($inputData['vnp_SecureHashType']);
        unset($inputData['vnp_SecureHash']);
        ksort($inputData);

        $hashData = "";
        $i = 0;
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

        if ($secureHash === $vnp_SecureHash) {
            if ($inputData['vnp_ResponseCode'] == '00') {
                // Cập nhật trạng thái đơn hàng
                $orderId = $inputData['vnp_TxnRef'];
                $order = Order::find($orderId);
                if ($order) {
                    $order->status_id = 2; // Đặt trạng thái đơn hàng là 'confirmed' hoặc trạng thái thành công khác
                    $order->save();
                }
                return ['status' => true, 'order_id' => $orderId];
            } else {
                return ['status' => false, 'message' => 'Payment failed'];
            }
        } else {
            return ['status' => false, 'message' => 'Invalid signature'];
        }
    }
}