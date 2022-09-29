<?php

namespace Marvel\Http\Controllers;

use Exception;
use Cknow\Money\Money;
use Http\Discovery\Exception\NotFoundException;
use Marvel\Traits\Wallets;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Marvel\Enums\Permission;
use Marvel\Events\OrderCreated;
use Marvel\Exports\OrderExport;
use Marvel\Database\Models\Shop;
use Marvel\Database\Models\User;
use Illuminate\Http\JsonResponse;
// use Barryvdh\DomPDF\Facade as PDF;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Refund;
use Marvel\Database\Models\Wallet;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\Balance;
use Maatwebsite\Excel\Facades\Excel;
use Marvel\Database\Models\Settings;
use Marvel\Exceptions\MarvelException;
use Illuminate\Support\Facades\Session;
use Marvel\Database\Models\DownloadToken;
use Illuminate\Database\Eloquent\Collection;
use Marvel\Http\Requests\OrderCreateRequest;
use Marvel\Http\Requests\OrderUpdateRequest;
use Marvel\Database\Repositories\OrderRepository;
use niklasravnsborg\LaravelPdf\Facades\Pdf as PDF;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use App\Models\OrderPackages;
use Illuminate\Support\Facades\Redirect;
use Laravel\Sanctum\PersonalAccessToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\VNPay_Payment;

class OrderController extends CoreController
{
    use Wallets;
    public $repository;

    public function __construct(OrderRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Collection|Order[]
     */
    public function index(Request $request)
    {
        $limit = $request->limit ?   $request->limit : 10;
        return $this->fetchOrders($request)->paginate($limit)->withQueryString();
    }

    public function fetchOrders(Request $request)
    {
        $user = $request->user();

        if ($user && $user->hasPermissionTo(Permission::SUPER_ADMIN) && (!isset($request->shop_id) || $request->shop_id === 'undefined')) {
            return $this->repository->with('children')->where('id', '!=', null)->where('parent_id', '=', null); //->paginate($limit);
        } else if ($this->repository->hasPermission($user, $request->shop_id)) {
            // if ($user && $user->hasPermissionTo(Permission::STORE_OWNER)) {
            return $this->repository->with('children')->where('shop_id', '=', $request->shop_id)->where('parent_id', '!=', null); //->paginate($limit);
            // } elseif ($user && $user->hasPermissionTo(Permission::STAFF)) {
            //     return $this->repository->with('children')->where('shop_id', '=', $request->shop_id)->where('parent_id', '!=', null); //->paginate($limit);
            // }
        } else {
            return $this->repository->with('children')->where('customer_id', '=', $user->id)->where('parent_id', '=', null); //->paginate($limit);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param OrderCreateRequest $request
     * @return LengthAwarePaginator|\Illuminate\Support\Collection|mixed
     */
    public function store(OrderCreateRequest $request)
    {
        return $this->repository->storeOrder($request);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, $params)
    {
        $user = $request->user() ?? null;
        $language = $request->language ?? DEFAULT_LANGUAGE;
        try {
            $order = $this->repository->where('language', $language)->with(['products', 'status', 'children.shop', 'wallet_point'])->where('id', $params)->orWhere('tracking_number', $params)->firstOrFail();
        } catch (\Exception $e) {
            throw new MarvelException(NOT_FOUND);
        }
        if (!$order->customer_id) {
            return $order;
        }
        if ($user && $user->hasPermissionTo(Permission::SUPER_ADMIN)) {
            return $order;
        } elseif (isset($order->shop_id)) {
            if ($user && ($this->repository->hasPermission($user, $order->shop_id) || $user->id == $order->customer_id)) {
                return $order;
            }
        } elseif ($user && $user->id == $order->customer_id) {
            return $order;
        } else {
            throw new MarvelException(NOT_AUTHORIZED);
        }
    }
    public function findByTrackingNumber(Request $request, $tracking_number)
    {
        $user = $request->user() ?? null;
        try {
            $order = $this->repository->with(['products', 'status', 'children.shop', 'wallet_point'])
                ->findOneByFieldOrFail('tracking_number', $tracking_number);

            if ($order->customer_id === null) {
                return $order;
            }
            if ($user && ($user->id === $order->customer_id || $user->can('super_admin'))) {
                return $order;
            } else {
                throw new MarvelException(NOT_AUTHORIZED);
            }
        } catch (\Exception $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param OrderUpdateRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(OrderUpdateRequest $request, $id)
    {
        $request->id = $id;
        return $this->updateOrder($request);
    }


    public function updateOrder(Request $request)
    {
        try {
            $order = $this->repository->findOrFail($request->id);
        } catch (\Exception $e) {
            throw new MarvelException(NOT_FOUND);
        }
        $user = $request->user();
        if (isset($order->shop_id)) {
            if ($this->repository->hasPermission($user, $order->shop_id)) {
                return $this->changeOrderStatus($order, $request->status);
            }
        } else if ($user->hasPermissionTo(Permission::SUPER_ADMIN)) {
            return $this->changeOrderStatus($order, $request->status);
        } else {
            throw new MarvelException(NOT_AUTHORIZED);
        }
    }

    public function changeOrderStatus($order, $status)
    {
        $order->status = $status;
        $order->save();
        try {
            $children = json_decode($order->children);
        } catch (\Throwable $th) {
            $children = $order->children;
        }
        if (is_array($children) && count($children)) {
            foreach ($order->children as $child_order) {
                $child_order->status = $status;
                $child_order->save();
            }
        }
        return $order;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id)
    {
        try {
            return $this->repository->findOrFail($id)->delete();
        } catch (\Exception $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * Export order dynamic url
     *
     * @param Request $request
     * @param int $shop_id
     * @return string
     */
    public function exportOrderUrl(Request $request, $shop_id = null)
    {
        $user = $request->user();

        if ($user && !$this->repository->hasPermission($user, $request->shop_id)) {
            throw new MarvelException(NOT_AUTHORIZED);
        }

        $dataArray = [
            'user_id' => $user->id,
            'token'   => Str::random(16),
            'payload' => $request->shop_id
        ];
        $newToken = DownloadToken::create($dataArray);

        return route('export_order.token', ['token' => $newToken->token]);
    }

    /**
     * Export order to excel sheet
     *
     * @param string $token
     * @return void
     */
    public function exportOrder($token)
    {
        $shop_id = 0;
        try {
            $downloadToken = DownloadToken::where('token', $token)->first();

            $shop_id = $downloadToken->payload;
            if ($downloadToken) {
                $downloadToken->delete();
            } else {
                return ['message' => TOKEN_NOT_FOUND];
            }
        } catch (Exception $e) {
            throw new MarvelException(TOKEN_NOT_FOUND);
        }

        try {
            return Excel::download(new OrderExport($this->repository, $shop_id), 'orders.xlsx');
        } catch (Exception $e) {
            return ['message' => NOT_FOUND];
        }
    }

    /**
     * Export order dynamic url
     *
     * @param Request $request
     * @param int $shop_id
     * @return string
     */
    public function downloadInvoiceUrl(Request $request)
    {
        $user = $request->user();

        if ($user && !$this->repository->hasPermission($user, $request->shop_id)) {
            throw new MarvelException(NOT_AUTHORIZED);
        }

        if(empty($request->order_id)){
            throw new NotFoundException(NOT_FOUND);
        }

        $language = $request->language ?? DEFAULT_LANGUAGE;
        $isRTL = $request->is_rtl ?? false;

        $translatedText = $this->formatInvoiceTranslateText($request->translated_text);

        $payload = [
            'user_id'           => $user->id,
            'order_id'          => intval($request->order_id),
            'language'          => $language,
            'translated_text'   => $translatedText,
            'is_rtl'            => $isRTL
        ];

        $data = [
            'user_id' => $user->id,
            'token'   => Str::random(16),
            'payload' => serialize($payload)
        ];

        $newToken = DownloadToken::create($data);

        return route('download_invoice.token', ['token' => $newToken->token]);
    }

    /**
     * Helper method for generate default translated text for invoice
     *
     * @param array $translatedText
     * @return array
     */
    public function formatInvoiceTranslateText($translatedText = [])
    {
        return [
            'subtotal'      => Arr::has($translatedText, 'subtotal') ? $translatedText['subtotal'] : 'SubTotal',
            'discount'      => Arr::has($translatedText, 'discount') ? $translatedText['discount'] : 'Discount',
            'tax'           => Arr::has($translatedText, 'tax') ? $translatedText['tax'] : 'Tax',
            'delivery_fee'  => Arr::has($translatedText, 'delivery_fee') ? $translatedText['delivery_fee'] : 'Delivery Fee',
            'total'         => Arr::has($translatedText, 'total') ? $translatedText['total'] : 'Total',
            'products'      => Arr::has($translatedText, 'products') ? $translatedText['products'] : 'Products',
            'quantity'      => Arr::has($translatedText, 'quantity') ? $translatedText['quantity'] : 'Qty',
            'invoice_no'    => Arr::has($translatedText, 'invoice_no') ? $translatedText['invoice_no'] : 'Invoice No',
            'date'          => Arr::has($translatedText, 'date') ? $translatedText['date'] : 'Date',
        ];
    }

    /**
     * Export order to excel sheet
     *
     * @param string $token
     * @return void
     */
    public function downloadInvoice($token)
    {
        $payloads = [];
        try {
            $downloadToken = DownloadToken::where('token', $token)->first();
            $payloads      = unserialize($downloadToken->payload);

            if ($downloadToken) {
                $downloadToken->delete();
            } else {
                return ['message' => TOKEN_NOT_FOUND];
            }
        } catch (Exception $e) {
            throw new MarvelException(TOKEN_NOT_FOUND);
        }

        try {
            $settings = Settings::getData($payloads['language']);
            $order = $this->repository->with(['products', 'status', 'children.shop', 'wallet_point'])->where('id', $payloads['order_id'])->firstOrFail();
        } catch (\Exception $e) {
            throw new MarvelException(NOT_FOUND);
        }

        $invoiceData = [
            'order'           => $order,
            'settings'        => $settings,
            'translated_text' => $payloads['translated_text'],
            'is_rtl'          => $payloads['is_rtl'],
            'language'        => $payloads['language'],
        ];

        $pdf = PDF::loadView('pdf.order-invoice', $invoiceData);
        $filename = 'invoice-order-' . $payloads['order_id'] . '.pdf';

        return $pdf->download($filename);
    }

    // for wallet
    public function OrderPointVnpay(Request $request){
        $bearerToken = $request->token;

        $token = PersonalAccessToken::findToken($bearerToken);
        $order = OrderPackages::create([
            'user_id'=> $token->tokenable->id,
            'price' => $request->amount,
        ]);
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $startTime = date("YmdHis");
        $txtexpire = date('YmdHis',strtotime('+15 minutes',strtotime($startTime)));

        $vnp_TmnCode = "GWC7RIQM"; //Website ID in VNPAY System
        $vnp_HashSecret = "TYMPTRHCTUOVTPUKONECCTOWHGBKSXPN"; //Secret key
        $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
        $vnp_Returnurl = route('vnpay.return.point');
        $vnp_apiUrl = "http://sandbox.vnpayment.vn/merchant_webapi/merchant.html";
        //Config input format
        //Expire      
        $vnp_TxnRef = $order->id; //Mã đơn hàng. Trong thực tế Merchant cần insert đơn hàng vào DB và gửi mã này sang VNPAY
        $vnp_OrderInfo = 'Coin Payment';
        $vnp_OrderType = 'billpayment';
        $vnp_Amount = $request->amount * 100;
        $vnp_Locale = 'vn';
        $vnp_BankCode = '';
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
        //Add Params of 2.0.1 Version
        $vnp_ExpireDate = $txtexpire;
        //Billing$vnp_Bill_Mobile = $_POST['txt_billing_mobile'];
        
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
            "vnp_ExpireDate"=> $vnp_ExpireDate,
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
            $vnpSecureHash =   hash_hmac('sha512', $hashdata, $vnp_HashSecret);//  
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }
        header('Location: ' . $vnp_Url);
        die();
    }
    public function return_vnpay_point(Request $request){
    
        $dt = Carbon::now('Asia/Ho_Chi_Minh');
       if($request->vnp_TxnRef != null && $request->vnp_ResponseCode=='00'){
       
        try{
            DB::beginTransaction();
        $coint = $request->vnp_Amount*1;
        $order = OrderPackages::where('id',$request->vnp_TxnRef)->first(); 
        $wallet = Wallet::where('customer_id', $order->user_id)->first();
        $update_wallet =  $wallet->update(['total_points'=> $wallet->total_points +$coint,
        'available_points'=> $wallet->available_points +  $coint]);

        $payment_vnp=array();
        
        if($order){
          
                $payment_vnp['p_user_id'] = $order->user_id;
                $payment_vnp['p_transaction_id']=$order->id;
                $payment_vnp['p_transaction_code']=$request->vnp_TxnRef;
                $payment_vnp['p_money']=$request->vnp_Amount;
                $payment_vnp['p_node']=$request->vnp_OrderInfo;
                $payment_vnp['p_vnp_response_code']=$request->vnp_ResponseCode;
                $payment_vnp['p_code_vnpay']=$request->vnp_TransactionNo;
                $payment_vnp['p_code_bank']=$request->vnp_BankCode;
                $payment_vnp['p_time']= $dt->toDateTimeString();
                
                $payment_vnp = VNPay_Payment::insert($payment_vnp);
        }
        $date = Carbon::now();
        $order->update(['status'=>1]);
    
        DB::commit();
        $user = User::where('id', $order->user_id)->first();
        $url = config('shop.shop_url_device'); // from shop -rest
        $data = array(
            'token' => $user->createToken('auth_token')->plainTextToken,
            'redirect' => '/wallet',
        );

        $href = $url . '?' . http_build_query($data);
        return  Redirect::away($href);
  
       // return ["done" => 'done'];
      }
       catch(Exception $exception){
          DB::rollBack();
          Log::error('Message'.$exception->getMessage().'Line'.$exception->getLine());
    
        }
       }
       return ['message' => SOMETHING_WENT_WRONG, 'success' => false];
      
    }

}
