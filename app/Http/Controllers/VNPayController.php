<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Cart;
use DB;
use Illuminate\Support\Facades\Session;
class VNPayController extends Controller
{
    public function create(Request $request)
    {
        $now = Carbon::now();
        $username = DB::table('khachhang')->where('username','=',Session::get('kh'))->first();
        $sanpham = Cart::getContent();
        
        $madon = rand(1000,9999);

        if (Session::has('sotiengiam')) {
            # code...
            $hoadon = DB::table('donhang')->insertGetId(
                [
                    'dh_madon' => $madon,
                    'dh_nguoinhan' => $username->kh_hoten,
                    'dh_noinhan' => $username->kh_diachi,
                    'dh_tongtien' => Cart::getTotal(),
                    'dh_thoigiandathang' => $now,
                    'kh_id' => $username->kh_id,
                ]
            );
        } else {
            # code...
        }
        

        $hoadon = DB::table('donhang')->insertGetId(
            [
                'dh_madon' => $madon,
                'dh_nguoinhan' => $username->kh_hoten,
                'dh_noinhan' => $username->kh_diachi,
                'dh_tongtien' => Cart::getTotal(),
                'dh_thoigiandathang' => $now,
                'kh_id' => $username->kh_id,
            ]
        );

        foreach ($sanpham as $key => $value) {
            # code...
            DB::table('chitietdonhang')->insert([
                'sp_id' => $value->id,
                'dh_id' => $hoadon,
                'sp_dongia' => $value->price,
                'sp_soluongsp' => $value->quantity
            ]);
        }

        session(['cost_id' => $request->id]);
        session(['url_prev' => url()->previous()]);
        $vnp_TmnCode = "UDOPNWS1"; //Mã website tại VNPAY 
        $vnp_HashSecret = "EBAHADUGCOEWYXCMYZRMTMLSHGKNRPBN"; //Chuỗi bí mật
        $vnp_Url = "http://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
        $vnp_Returnurl = 'http://127.0.0.1:8000/thanh-toan';
        $vnp_TxnRef = $hoadon; //Mã đơn hàng. Trong thực tế Merchant cần insert đơn hàng vào DB và gửi mã này sang VNPAY
        $vnp_OrderInfo = "Thanh toán hóa đơn phí dich vụ";
        $vnp_OrderType = 'billpayment';
        
        $vnp_Amount =  Cart::getTotal() * 100;
        $vnp_Locale = 'vn';
        $vnp_IpAddr = request()->ip();

        $inputData = array(
            "vnp_Version" => "2.0.0",
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

        // if (isset($vnp_BankCode) && $vnp_BankCode != "") {
        //     $inputData['vnp_BankCode'] = $vnp_BankCode;
        // }
        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . $key . "=" . $value;
            } else {
                $hashdata .= $key . "=" . $value;
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url = $vnp_Url . "?" . $query;
        if (isset($vnp_HashSecret)) {
           // $vnpSecureHash = md5($vnp_HashSecret . $hashdata);
            $vnpSecureHash = hash('sha256', $vnp_HashSecret . $hashdata);
            $vnp_Url .= 'vnp_SecureHashType=SHA256&vnp_SecureHash=' . $vnpSecureHash;
        }
        return redirect($vnp_Url);
    }

    public function return(Request $request)
    {
        $url = session('url_prev','/');
        if($request->vnp_ResponseCode == "00") {
            $this->apSer->thanhtoanonline(session('cost_id'));
            Cart::clear();
            return redirect($url)->with('success' ,'Đã thanh toán phí dịch vụ');
        }
        session()->forget('url_prev');
        return redirect($url)->with('errors' ,'Lỗi trong quá trình thanh toán phí dịch vụ');
        // dd($url);
    }
}
