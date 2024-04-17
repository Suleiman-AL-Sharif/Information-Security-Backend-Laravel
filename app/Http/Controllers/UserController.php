<?php

namespace App\Http\Controllers;

use App\Mail\TestMail;
use App\Models\Server;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use function openssl_encrypt;
use function openssl_decrypt;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;



class UserController extends Controller
{
    public function register(Request $request)
    {

        $request->validate([
            "email" => "required|email|unique:users",
            "password" => "required|confirmed",
        ]);

        $user = new User();
        $user->email = $request->email;
        $user->password = bcrypt($request->password);


        $user->save();
        return response()->json([
            "message" => "user created"
        ], 500);
    }

    public function login(Request $request)
    {

        $login_data = $request->validate([

            "email" => "required",
            "password" => "required",
        ]);

        if (!auth()->attempt($login_data)) {

            return response()->json([
                "status" => false,
                "message" => "invalid "
            ],400);
        }

        $token = auth()->user()->createToken("auth_token")->accessToken;
        $type = auth()->user()->type;
        $email = auth()->user()->email;
        Mail::to($email)->send(new TestMail(auth()->user()->name));
        return response()->json([
            "token" => $token,
            "type" => $type ,
        ],200);
    }

    public function logout(Request $request)
    {


        $token = $request->user()->token();

        $token->revoke();

        return response()->json([
            "status" => true,
            "message" => "logged out successfully ",

        ]);
    }


    public function info(Request $request)
    {

        $request->validate([
            "phone_n" => "required",
            "address" => "required",
            "name" => "required",
        ]);

        $key =  $request->key;
        $phone_n = $request->phone_n;
        $name = $request->name;
        $address = $request->address;

        $nameX = openssl_encrypt($name, 'AES-256-CBC', $key, 0, '0123456789abcdef');
        $phone_nX = openssl_encrypt($phone_n, 'AES-256-CBC', $key, 0, '0123456789abcdef');
        $addressX = openssl_encrypt($address, 'AES-256-CBC', $key, 0, '0123456789abcdef');


        $user = User::where('id', auth()->user()->id)->first();

        $user->name = $nameX;
        $user->phone_n = $phone_nX;
        $user->address = $addressX;

        $user->save();
        return response()->json([
            "message" => "data encoded",

        ]);
    }

    public function showInfo(Request $request)
    {

        $key =  $request->key;
        $user = User::where('id', auth()->user()->id)->first();

        $nameX = $user['name'];
        $name = openssl_decrypt($nameX, 'AES-256-CBC', $key, 0, '0123456789abcdef');

        $phone_nX = $user['phone_n'];
        $phone_n = openssl_decrypt($phone_nX, 'AES-256-CBC', $key, 0, '0123456789abcdef');

        $addressX = $user['address'];
        $address = openssl_decrypt($addressX, 'AES-256-CBC', $key, 0, '0123456789abcdef');


        return response()->json([
            "name" => $name,
            "phone_n" => $phone_n,
            "address" => $address,
        ]);
    }



    public function generateKeyPair(Request $request)
    {
        $request->validate([
            "id" => "required"
        ]);
        $related_user_id = $request->id;
        $user = User::where('id', auth()->user()->id)->first();

      //  if ($user->ServerPublicKey == null) {

            $server = new Server();

            $res = openssl_pkey_new();

            // استخراج المفتاح الخاص
            openssl_pkey_export($res, $userprivateKey);

            // استخراج المفتاح العام
            $userpublicKey = openssl_pkey_get_details($res);
            $userpublicKey = $userpublicKey["key"];

            $res1 = openssl_pkey_new();

            // استخراج المفتاح الخاص
            openssl_pkey_export($res1, $serverprivateKey);

            // استخراج المفتاح العام
            $serverpublicKey = openssl_pkey_get_details($res1);
            $serverpublicKey = $serverpublicKey["key"];



            $user->UserPrivateKey = $userprivateKey;
            $server->UserPublicKey = $userpublicKey;
            $server->user_id = auth()->user()->id;
            $server->related_user_id = $related_user_id;
            $user->ServerPublicKey = $serverpublicKey;
            $server->ServerPrivateKey = $serverprivateKey;
            $server->key = true;

            $user->save();
            $server->save();
            //$kk=mb_check_encoding ($data);
       // }

        $sessionKey = openssl_random_pseudo_bytes(32);


        $server = Server::where('user_id', auth()->user()->id)->first();
        $user = User::where('id', auth()->user()->id)->first();
        $serverpublicKey = $user->ServerPublicKey;

        openssl_public_encrypt($sessionKey, $encryptedData, $serverpublicKey);
        $encoded_data = utf8_encode($encryptedData);

        $user->SessionKey = $encoded_data;
        $user->save();


        $server->SessionKey = $encoded_data;
        $server->save();

        return response()->json([
            "message" =>  'done',
        ]);
    }



    public function data(Request $request)
    {

        $data = $request->data;
        $user = User::where('id', auth()->user()->id)->first();
        $server = Server::where('user_id', auth()->user()->id)->first();

        $userprivateKey = $user['UserPrivateKey'];
        openssl_sign($data, $signature, $userprivateKey, OPENSSL_ALGO_SHA256);
        $serverPrivateKey = $server['ServerPrivateKey'];
        $sessionKey = $server['SessionKey'];

        $encoded_data1 = utf8_decode($sessionKey);
        $signature1 = utf8_encode($signature);
        openssl_private_decrypt($encoded_data1, $sessionKey1, $serverPrivateKey);

        $data1 = openssl_encrypt($data, 'AES-256-CBC', $sessionKey1, 0, '0123456789abcdef');

        $server->signature = $signature1;
        $server->date =  $data1;
        $server->save();

        return response()->json([
            "message" => "done",
        ]);
    }

    public function showData()
    {
        $server = Server::where('related_user_id', auth()->user()->id)->get();
        $myList = collect();
        foreach ($server as $server) {

            $id1 =$server['user_id'];
            $id = $server['id'];
            $userpublicKey = $server['UserPublicKey'];

            $signature = $server['signature'];
            $data = $server['date'];

           // $server= Server::where('user_id', auth()->user()->id)->first();
            $user = User::where('id', $id1)->first();
            $name = $user['email'];

            $serverPrivateKey = $server['ServerPrivateKey'];
            $sessionKey = $server['SessionKey'];
            $encoded_data = utf8_decode($sessionKey);
            $signature1 = utf8_decode($signature);


            openssl_private_decrypt($encoded_data, $sessionKey1, $serverPrivateKey);
            $data1 = openssl_decrypt($data, 'AES-256-CBC', $sessionKey1, 0, '0123456789abcdef');

            $verified = openssl_verify($data1, $signature1, $userpublicKey, OPENSSL_ALGO_SHA256);

            if ($verified == 1) {

                $myObject = (object) [
                    'name' => $name,
                    'data' =>  $data1,
                ];
                $myList->push($myObject);
            } elseif ($verified == 0) {

                $myObject = (object) [
                    'name' => $name,
                    'data' =>  'data is changed',
                ];
                $myList->push($myObject);
            }
        }
        return response()->json([
            "data" => $myList,
        ]);
    }

    public function doctorKeys(Request $request)
    { $res = openssl_pkey_new();
      openssl_pkey_export($res, $userprivateKey);
        $userpublicKey = openssl_pkey_get_details($res);
        $userpublicKey = $userpublicKey["key"];
        $user = User::where('id', auth()->user()->id)->first();
        $user->UserPrivateKey = $userprivateKey;
        $user->save();
        $dn = [
            'countryName' => 'US',
            'stateOrProvinceName' => 'California',
            'localityName' => 'Los Angeles',
            'organizationName' =>  $user->address,
            'organizationalUnitName' => 'IT',
            'commonName' =>  $user->name,
            'emailAddress' => $user->email,
        ];
        $csr = openssl_csr_new($dn, $res);
        openssl_csr_export($csr, $csrString);
        $equation = $user->equation;
        if ($request->equation == $equation) {
            $certificate = openssl_csr_sign($csr, null, $userprivateKey, 365);
            openssl_x509_export($certificate, $certificateString);
            $user->CSR = $certificateString;
            $user->save();
            return response()->json([
                "message" => " Digital certificate generated and sent to the doctor.",
            ],200);
        } else {
            return response()->json([
                "message" => "CSR verification failed. Please check your CSR or contact CA for assistance.",
            ],201);
        }
    }

    public function equation()
    {

        $number1 = rand(1, 10);
        $number2 = rand(1, 10);
        $equation = $number1 + $number2;

        $user = User::where('id', auth()->user()->id)->first();

        $user->equation = $equation;
        $user->save();

        return response()->json([
            'message' => $number1 . ' + ' . $number2 . '= ??',
        ]);
    }
}
