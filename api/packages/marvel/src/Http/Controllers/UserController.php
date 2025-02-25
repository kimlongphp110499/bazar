<?php

namespace Marvel\Http\Controllers;

use Exception;
use Newsletter;
use Carbon\Carbon;
use Marvel\Traits\Wallets;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Marvel\Enums\Permission;
use Illuminate\Http\Response;
use Marvel\Mail\ContactAdmin;
use Marvel\Database\Models\User;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Wallet;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\Profile;
use Marvel\Otp\Gateways\OtpGateway;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Marvel\Database\Models\Attachment;
use Marvel\Exceptions\MarvelException;
use Marvel\Database\Models\OrderedFile;
use Laravel\Socialite\Facades\Socialite;
use Marvel\Database\Models\DownloadToken;
use Marvel\Http\Requests\UserCreateRequest;
use Marvel\Http\Requests\UserUpdateRequest;
use Illuminate\Validation\ValidationException;
use Marvel\Http\Requests\ChangePasswordRequest;
use Marvel\Database\Repositories\UserRepository;
use Marvel\Database\Repositories\DownloadRepository;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Marvel\Database\Models\Permission as ModelsPermission;
use \Swift_SmtpTransport;
use \Swift_Mailer;
use \Swift_Message;
use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Support\Facades\Redirect;
use App\Models\PackageD;
use App\Models\UserPackageD;
use App\Models\OrderPackages;
use App\Models\VNPay_Payment;



class UserController extends CoreController
{
    use Wallets;
    public $repository;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }
    public function profile(Request $request)
    {
        $result = User::find(auth()->user()->id);
        //1 is Permission super admin
        return ['result' => $result];
    }
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $limit = $request->limit ?   $request->limit : 15;
        //1 is Permission super admin
        return $this->repository->with(['profile', 'address', 'permissions'])->join('model_has_permissions','model_has_permissions.model_id','=','users.id')->where('model_has_permissions.permission_id','=',1)->paginate($limit);
    }

    public function getCustomers(Request $request)
    {
        $limit = $request->limit ?   $request->limit : 15;
        //1 is Permission super admin
        return $this->repository->with(['profile', 'address', 'permissions'])->join('model_has_permissions','model_has_permissions.model_id','=','users.id')->where('model_has_permissions.permission_id','!=',1)->groupByRaw('users.id')->paginate($limit);
    }

    /**
     * Store a newly created resource in storage.
     *Í
     * @param UserCreateRequest $request
     * @return bool[]
     */
    public function store(UserCreateRequest $request)
    {
        return $this->repository->storeUser($request);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return array
     */
    public function show($id)
    {
        try {
            return $this->repository->with(['profile', 'address', 'shops', 'managed_shop'])->findOrFail($id);
        } catch (Exception $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UserUpdateRequest $request
     * @param int $id
     * @return array
     */
    public function update(UserUpdateRequest $request, $id)
    {
        if ($request->user()->hasPermissionTo(Permission::SUPER_ADMIN)) {
            $user = $this->repository->findOrFail($id);
            return $this->repository->updateUser($request, $user);
        } elseif ($request->user()->id == $id) {
            $user = $request->user();
            return $this->repository->updateUser($request, $user);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return array
     */
    public function destroy($id)
    {
        try {
            return $this->repository->findOrFail($id)->delete();
        } catch (\Exception $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if (isset($user)) {
            return $this->repository->with(['profile', 'wallet', 'address', 'shops.balance', 'managed_shop.balance'])->find($user->id);
        }
        throw new MarvelException(NOT_AUTHORIZED);
    }

    public function token(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->where('is_active', true)->first();
        
        if (!$user || !Hash::check($request->password, $user->password)) {
            return ["token" => null, "permissions" => []];
        }
        return ["token" => $user->createToken('auth_token')->plainTextToken, "permissions" => $user->getPermissionNames()];
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return true;
        }
        return $request->user()->currentAccessToken()->delete();
    }

    public function encodeToken(int $userId, string $userType)
    {
        $hashId = Hashids::encode($userId);
        return uniqid() . $userType . $hashId;
    }
    public function vnpay_index(Request $request,$id){
    $price = 1200000;
    $date = Carbon::now();

     $order = OrderPackages::create([
        'user_id'=> $id,
        'price' => $price,
        'package_id' => $request->package_id,
        'expTime' => $date->addDays((int) $request->exp_day_time),
        'max_device' => $request->max_device,
        'exp_day_time' => $request->exp_day_time,
    ]);
    $order = OrderPackages::where('id',$order->id)->first();
       return view('vnpay.index',compact('order'));

   }

   public function create_vnpay(Request $request){

    $vnp_TmnCode = "GWC7RIQM"; //Website ID in VNPAY System
    $vnp_HashSecret = "TYMPTRHCTUOVTPUKONECCTOWHGBKSXPN"; //Secret key
    $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
    $vnp_Returnurl = route('vnpay.return');
    $vnp_apiUrl = "http://sandbox.vnpayment.vn/merchant_webapi/merchant.html";
    //Config input format
    //Expire
     $startTime = date("YmdHis");
  
    $vnp_TxnRef = $_POST['Ma_hd']; //Mã đơn hàng. Trong thực tế Merchant cần insert đơn hàng vào DB và gửi mã này sang VNPAY
    $vnp_OrderInfo = $_POST['order_desc'];
    $vnp_OrderType = $_POST['order_type'];
    $vnp_Amount = $_POST['amount'] * 100;
    $vnp_Locale = $_POST['language'];
    $vnp_BankCode = $_POST['bank_code'];
    $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
    //Add Params of 2.0.1 Version
    $vnp_ExpireDate = $_POST['txtexpire'];
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
public function return_vnpay(Request $request){
    
    $dt = Carbon::now('Asia/Ho_Chi_Minh');
   if($request->vnp_TxnRef != null && $request->vnp_ResponseCode=='00'){
   
    try{
        DB::beginTransaction();
    $order = OrderPackages::where('id',$request->vnp_TxnRef)->first();
     
    $havePack = UserPackageD::where('user_id',$order->user_id)->where('package_id',$order->package_id)->first();
    
  
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
    if(!$havePack)
    {
        UserPackageD::create([
            'user_id' => $order->user_id,
            'user_name' => $order->user_id.$order->package_id,
            'package_id' => $order->package_id,
            'max_device' => $order->max_device,
            // 'defaut_value' => $packageDefault->defaut_value,
            'license_key' => Str::random(16),
            'expTime' =>  $date->addDays((int) $order->exp_day_time),
            'exp_day_time' => $order->exp_day_time,
        ]);
       
    }
    else{
        $havePack->update([
            'max_device' => $havePack->max_device + $order->max_device,
            'defaut_value' => 0,
            //'license_key' => Str::random(16),
            'expTime' =>  Carbon::parse($havePack->expTime)->addDays((int) $order->exp_day_time),
            'exp_day_time' => $havePack->exp_day_time + $order->exp_day_time,
        ]);
    }
    $order->update(['status'=>1]);

    DB::commit();
    return ["done" => 'done'];
  }
   catch(Exception $exception){
      DB::rollBack();
      Log::error('Message'.$exception->getMessage().'Line'.$exception->getLine());

  }

   }
   return ['message' => SOMETHING_WENT_WRONG, 'success' => false];
  
}
    public function newPackage(Request $request){
        $date = Carbon::now();
        $pack = PackageD::create([
        'max_device' => auth()->user()->id,
        'max_device' => $request->max_device,
        'exp_day_time' => $request->exp_day_time,
        'defaut_value' => 0,]);
       $packDefault = UserPackageD::where('user_id',auth()->user()->id)->first();
       //$havePack = UserPackageD::where('user_id',auth()->user()->id)->where('defaut_value', 0)->whereRaw('license_key is not Null')->first();
       $result = $packDefault;
       return $this->vnpay_index($result, $pack->id);
    }
    public function confirmRegister(Request $request){
        $notAllowedPermissions = [Permission::SUPER_ADMIN];
        if ((isset($request->permission->value) && in_array($request->permission->value, $notAllowedPermissions)) || (isset($request->permission) && in_array($request->permission, $notAllowedPermissions))) {
            throw new MarvelException(NOT_AUTHORIZED);
        }
        $permissions = [Permission::CUSTOMER];
        if (isset($request->permission)) {
            $permissions[] = isset($request->permission->value) ? $request->permission->value : $request->permission;
        }
        $user = $this->repository->create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $packageDefault = PackageD::where('defaut_value',1)->first();

        $date = Carbon::now();
       
        UserPackageD::create([
            'user_id' => $user->id,
            'user_name' => $user->name.$user->id,
            'package_id' => $packageDefault->id,
            'max_device' => $packageDefault->max_device,
            'defaut_value' => $packageDefault->defaut_value,
            'license_key' => Str::random(16),
            'expTime' =>  $date->addDays((int) $packageDefault->exp_day_time),
            'exp_day_time' => $packageDefault->exp_day_time,
        ]);
      
        $user->givePermissionTo($permissions);
        $this->giveSignupPointsToCustomer($user->id);
        $url = config('shop.shop_url_pakage'); // from shop -rest
        $data = array(
            'token' =>$user->createToken('auth_token')->plainTextToken,
        );

        $href = $url . '?' . http_build_query($data);
        return  Redirect::away($href);
  
    }
    public function confirmRegisterDevice(Request $request){
        $notAllowedPermissions = [Permission::SUPER_ADMIN];
        if ((isset($request->permission->value) && in_array($request->permission->value, $notAllowedPermissions)) || (isset($request->permission) && in_array($request->permission, $notAllowedPermissions))) {
            throw new MarvelException(NOT_AUTHORIZED);
        }
        $permissions = [Permission::CUSTOMER];
        if (isset($request->permission)) {
            $permissions[] = isset($request->permission->value) ? $request->permission->value : $request->permission;
        }
        $user = $this->repository->create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $packageDefault = PackageD::where('defaut_value',1)->first();

        $date = Carbon::now();
       
        // UserPackageD::create([
        //     'user_id' => $user->id,
        //     'user_name' => $user->name.$user->id,
        //     'package_id' => $packageDefault->id,
        //     'max_device' => $packageDefault->max_device,
        //     'defaut_value' => $packageDefault->defaut_value,
        //     'license_key' => Str::random(16),
        //     'expTime' =>  $date->addDays((int) $packageDefault->exp_day_time),
        //     'exp_day_time' => $packageDefault->exp_day_time,
        // ]);
      
        $user->givePermissionTo($permissions);
        $this->giveSignupPointsToCustomer($user->id);
        $url = config('shop.shop_url_device'); // from shop -rest
        $data = array(
            'token' =>$user->createToken('auth_token')->plainTextToken,
        );

        $href = $url . '?' . http_build_query($data);
        return  Redirect::away($href);
  
    }
    public function register(UserCreateRequest $request)
    {
        $token= Hash::make(substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 6));
        $url = url('/').'/confirm-register?name='.$request->name.'&email='.$request->email.'&password='.$request->password;
        $transport = (new Swift_SmtpTransport('smtp.gmail.com', 587, 'tls'))
        ->setUsername(config('shop.admin_email'))
        ->setPassword(config('shop.admin_password'));

        // Create the Mailer using your created Transport
        $mailer = new Swift_Mailer($transport);

        // Create a message
        $message = (new Swift_Message('Confim Email For Register User'))
        ->setFrom(['kimlongxutede110499@gmail.com' => 'Admin Shop'])
        ->setTo([$request->email => $request->email])
        ->setBody(view('emails.register-user',  ['name' => $request->name, 'email' => $request->email,'password' => $request->password, 'url' => $url,])->render(),'text/html');

        // Send the message
        $result = $mailer->send($message);
        return ["done" => 'done'];

      
    }
    public function registerDevice(UserCreateRequest $request)
    {
        $token= Hash::make(substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 6));
        $url = url('/').'/confirm-register-device?name='.$request->name.'&email='.$request->email.'&password='.$request->password;
        $transport = (new Swift_SmtpTransport('smtp.gmail.com', 587, 'tls'))
        ->setUsername(config('shop.admin_email'))
        ->setPassword(config('shop.admin_password'));

        // Create the Mailer using your created Transport
        $mailer = new Swift_Mailer($transport);

        // Create a message
        $message = (new Swift_Message('Confim Email For Register User'))
        ->setFrom(['kimlongxutede110499@gmail.com' => 'Admin Shop'])
        ->setTo([$request->email => $request->email])
        ->setBody(view('emails.register-user',  ['name' => $request->name, 'email' => $request->email,'password' => $request->password, 'url' => $url,])->render(),'text/html');

        // Send the message
        $result = $mailer->send($message);
        return ["done" => 'done'];

      
    }

    /**
     * Get a id number from token
     *
     * @param string $token
     * @return array|null
     */
    public function decodeToken(string $token)
    {
        if (empty($token)) {
            return null;
        }

        $userType = substr($token, 13, 6);
        $hashId = substr($token, 19);

        $userId = 0;
        $hash = Hashids::decode($hashId);
        if (!empty($hash)) {
            $userId = $hash[0];
        }

        return compact('userType', 'userId');
    }

    public function banUser(Request $request)
    {
        $user = $request->user();
        if ($user && $user->hasPermissionTo(Permission::SUPER_ADMIN) && $user->id != $request->id) {
            $banUser =  User::find($request->id);
            $banUser->is_active = false;
            $banUser->save();
            return $banUser;
        } else {
            throw new MarvelException(NOT_AUTHORIZED);
        }
    }
    public function activeUser(Request $request)
    {
        $user = $request->user();
        if ($user && $user->hasPermissionTo(Permission::SUPER_ADMIN) && $user->id != $request->id) {
            $activeUser =  User::find($request->id);
            $activeUser->is_active = true;
            $activeUser->save();
            return $activeUser;
        } else {
            throw new MarvelException(NOT_AUTHORIZED);
        }
    }

    public function forgetPassword(Request $request)
    {
        $user = $this->repository->findByField('email', $request->email);
        if (count($user) < 1) {
            return ['message' => NOT_FOUND, 'success' => false];
        }
        $tokenData = DB::table('password_resets')
            ->where('email', $request->email)->first();
        if (!$tokenData) {
            DB::table('password_resets')->insert([
                'email' => $request->email,
                'token' => Str::random(16),
                'created_at' => Carbon::now()
            ]);
            $tokenData = DB::table('password_resets')
                ->where('email', $request->email)->first();
        }

        if ($this->repository->sendResetEmail($request->email, $tokenData->token)) {
            return ['message' => CHECK_INBOX_FOR_PASSWORD_RESET_EMAIL, 'success' => true];
        } else {
            return ['message' => SOMETHING_WENT_WRONG, 'success' => false];
        }
    }
    public function verifyForgetPasswordToken(Request $request)
    {
        $tokenData = DB::table('password_resets')->where('token', $request->token)->first();
        if (!$tokenData) {
            return ['message' => INVALID_TOKEN, 'success' => false];
        }
        $user = $this->repository->findByField('email', $request->email);
        if (count($user) < 1) {
            return ['message' => NOT_FOUND, 'success' => false];
        }
        return ['message' => TOKEN_IS_VALID, 'success' => true];
    }
    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'password' => 'required|string',
                'email' => 'email|required',
                'token' => 'required|string'
            ]);

            $user = $this->repository->where('email', $request->email)->first();
            $user->password = Hash::make($request->password);
            $user->save();

            DB::table('password_resets')->where('email', $user->email)->delete();

            return ['message' => PASSWORD_RESET_SUCCESSFUL, 'success' => true];
        } catch (\Exception $th) {
            return ['message' => SOMETHING_WENT_WRONG, 'success' => false];
        }
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        try {
            $user = $request->user();
            if (Hash::check($request->oldPassword, $user->password)) {
                $user->password = Hash::make($request->newPassword);
                $user->save();
                return ['message' => PASSWORD_RESET_SUCCESSFUL, 'success' => true];
            } else {
                return ['message' => OLD_PASSWORD_INCORRECT, 'success' => false];
            }
        } catch (\Exception $th) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }
    public function contactAdmin(Request $request)
    {
        try {
            $details = $request->only('subject', 'name', 'email', 'description');
            Mail::to(config('shop.admin_email'))->send(new ContactAdmin($details));
            return ['message' => EMAIL_SENT_SUCCESSFUL, 'success' => true];
        } catch (\Exception $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    public function fetchStaff(Request $request)
    {
        if (!isset($request->shop_id)) {
            throw new MarvelException(NOT_AUTHORIZED);
        }
        if ($this->repository->hasPermission($request->user(), $request->shop_id)) {
            return $this->repository->with(['profile'])->where('shop_id', '=', $request->shop_id);
        } else {
            throw new MarvelException(NOT_AUTHORIZED);
        }
    }

    public function staffs(Request $request)
    {
        $query = $this->fetchStaff($request);
        $limit = $request->limit ?? 15;
        return $query->paginate($limit);
    }

    public function socialLogin(Request $request)
    {
        $provider = $request->provider;
        $token = $request->access_token;
        $this->validateProvider($provider);

        try {
            $user = Socialite::driver($provider)->userFromToken($token);
            $userExist = User::where('email',  $user->email)->exists();

            $userCreated = User::firstOrCreate(
                [
                    'email' => $user->getEmail()
                ],
                [
                    'email_verified_at' => now(),
                    'name' => $user->getName(),
                ]
            );

            $userCreated->providers()->updateOrCreate(
                [
                    'provider' => $provider,
                    'provider_user_id' => $user->getId(),
                ]
            );

            $avatar = [
                'thumbnail' => $user->getAvatar(),
                'original' => $user->getAvatar(),
            ];

            $userCreated->profile()->updateOrCreate(
                [
                    'avatar' => $avatar
                ]
            );

            if (!$userCreated->hasPermissionTo(Permission::CUSTOMER)) {
                $userCreated->givePermissionTo(Permission::CUSTOMER);
            }

            if (empty($userExist)) {
                $this->giveSignupPointsToCustomer($userCreated->id);
            }

            return ["token" => $userCreated->createToken('auth_token')->plainTextToken, "permissions" => $userCreated->getPermissionNames()];
        } catch (\Exception $e) {
            throw new MarvelException(INVALID_CREDENTIALS);
        }
    }

    protected function validateProvider($provider)
    {
        if (!in_array($provider, ['facebook', 'google'])) {
            throw new MarvelException(PLEASE_LOGIN_USING_FACEBOOK_OR_GOOGLE);
        }
    }

    protected function getOtpGateway()
    {
        $gateway = config('auth.active_otp_gateway');
        $gateWayClass = "Marvel\\Otp\\Gateways\\" . ucfirst($gateway) . 'Gateway';
        return new OtpGateway(new $gateWayClass());
    }

    protected function verifyOtp(Request $request)
    {
        $id = $request->otp_id;
        $code = $request->code;
        $phoneNumber = $request->phone_number;
        try {
            $otpGateway = $this->getOtpGateway();
            $verifyOtpCode = $otpGateway->checkVerification($id, $code, $phoneNumber);
            if ($verifyOtpCode->isValid()) {
                return true;
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function sendOtpCode(Request $request)
    {
        $phoneNumber = $request->phone_number;
        try {
            $otpGateway = $this->getOtpGateway();
            $sendOtpCode = $otpGateway->startVerification($phoneNumber);
            if (!$sendOtpCode->isValid()) {
                return ['message' => OTP_SEND_FAIL, 'success' => false];
            }
            $profile = Profile::where('contact', $phoneNumber)->first();
            return [
                'message' => OTP_SEND_SUCCESSFUL,
                'success' => true,
                'provider' => config('auth.active_otp_gateway'),
                'id' => $sendOtpCode->getId(),
                'phone_number' => $phoneNumber,
                'is_contact_exist' => $profile ? true : false
            ];
        } catch (\Exception $e) {
            throw new MarvelException(INVALID_GATEWAY);
        }
    }

    public function verifyOtpCode(Request $request)
    {
        try {
            if ($this->verifyOtp($request)) {
                return [
                    "message" => OTP_SEND_SUCCESSFUL,
                    "success" => true,
                ];
            }
            throw new MarvelException(OTP_VERIFICATION_FAILED);
        } catch (\Throwable $e) {
            throw new MarvelException(OTP_VERIFICATION_FAILED);
        }
    }

    public function otpLogin(Request $request)
    {
        $phoneNumber = $request->phone_number;

        try {
            if ($this->verifyOtp($request)) {
                // check if phone number exist
                $profile = Profile::where('contact', $phoneNumber)->first();
                $user = '';
                if (!$profile) {
                    // profile not found so could be a new user
                    $name = $request->name;
                    $email = $request->email;
                    if ($name && $email) {
                        $user = User::firstOrCreate([
                            'email'     => $email
                        ], [
                            'name'    => $name,
                        ]);
                        $user->givePermissionTo(Permission::CUSTOMER);
                        $user->profile()->updateOrCreate(
                            ['customer_id' => $user->id],
                            [
                                'contact' => $phoneNumber
                            ]
                        );
                    } else {
                        return ['message' => REQUIRED_INFO_MISSING, 'success' => false];
                    }
                } else {
                    $user = User::where('id', $profile->customer_id)->first();
                }
                $this->giveSignupPointsToCustomer($user->id);
                return [
                    "token" => $user->createToken('auth_token')->plainTextToken,
                    "permissions" => $user->getPermissionNames()
                ];
            }
            return ['message' => OTP_VERIFICATION_FAILED, 'success' => false];
        } catch (\Throwable $e) {
            return response()->json(['error' => INVALID_GATEWAY], 422);
        }
    }

    public function updateContact(Request $request)
    {
        $phoneNumber = $request->phone_number;
        $user_id = $request->user_id;

        try {
            if ($this->verifyOtp($request)) {
                $user = User::find($user_id);
                $user->profile()->updateOrCreate(
                    ['customer_id' => $user_id],
                    [
                        'contact' => $phoneNumber
                    ]
                );
                return [
                    "message" => CONTACT_UPDATE_SUCCESSFUL,
                    "success" => true,
                ];
            }
            return ['message' => CONTACT_UPDATE_FAILED, 'success' => false];
        } catch (\Exception $e) {
            return response()->json(['error' => INVALID_GATEWAY], 422);
        }
    }

    public function addPoints(Request $request)
    {
        $request->validate([
            'points' => 'required|numeric',
            'customer_id' => ['required', 'exists:Marvel\Database\Models\User,id']
        ]);
        $points = $request->points;
        $customer_id = $request->customer_id;

        $wallet = Wallet::firstOrCreate(['customer_id' => $customer_id]);
        $wallet->total_points = $wallet->total_points + $points;
        $wallet->available_points = $wallet->available_points + $points;
        $wallet->save();
    }

    public function makeOrRevokeAdmin(Request $request)
    {
        $user = $request->user();
        if ($this->repository->hasPermission($user)) {
            $user_id = $request->user_id;
            try {
                $newUser = $this->repository->findOrFail($user_id);
                if ($newUser->hasPermissionTo(Permission::SUPER_ADMIN)) {
                    $newUser->revokePermissionTo(Permission::SUPER_ADMIN);
                    return true;
                }
            } catch (Exception $e) {
                throw new MarvelException(USER_NOT_FOUND);
            }
            $newUser->givePermissionTo(Permission::SUPER_ADMIN);

            return true;
        }

        throw new MarvelException(NOT_AUTHORIZED);
    }
    public function subscribeToNewsletter(Request $request)
    {
        try {
            $email = $request->email;
            Newsletter::subscribe($email);
            return true;
        } catch (\Throwable $th) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }
}
