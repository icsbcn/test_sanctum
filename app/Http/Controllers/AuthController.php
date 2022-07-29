<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller {
    
    /**
     * Register function.
     * 
     * @param request Illuminate\Http\Request
     * @return json with values: "result" ==> ok.
     */
    public function register(Request $request) {       
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'nickname' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|max:255'
        ]);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            
            return response()->json(
                [
                    'errors' => $errors->all()
                ],
                401
            );
        }
        
        $validatedData = $validator->validated();

        $user = User::create(
        [
            'name' => $validatedData['name'],
            'nickname' => $validatedData['nickname'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password'])
        ]);

        return response()->json(
            [
                'result' => 'ok'
            ]
        );
    }

    /**
     * Login function.
     * 
     * @param request Illuminate\Http\Request
     * @return json with values: "access_token", "token_type".
     */
    public function login(Request $request) {
        $validator = Validator::make($request->all(), [
            'email' => 'required',
            'password' => 'required'
        ]);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            
            return response()->json(
                [
                    'errors' => $errors->all()
                ],
                401
            );
        }
        
        $validatedData = $validator->validated();
        
        // Try login
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(
                [
                    'errors' => 'login KO'
                ],
                401
            );
        }

        $user = User::where('email', $request['email'])->firstOrFail();

        // Check only one token
        $token = $user->tokens()->first();
        if (isset($token->id) && intval($token->id) > 0) {
            return response()->json(
                [
                    'errors' => 'already token'
                ],
                401
            );
        }

        // Create and return token
        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json(
            [
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]
        );
    }
}
