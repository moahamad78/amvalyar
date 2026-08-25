<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;
use Modules\Core\Application\Security\Contracts\AuthenticationServiceInterface;

final class LoginController extends Controller
{
    public function __construct(
        private AuthenticationServiceInterface $authenticationService
    ) {
    }


    public function show(): View
    {
        return view('auth.login');
    }


    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'max:100',
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);


        try {

            $loginSession = $this->authenticationService->authenticate(
                username: $validated['username'],
                password: $validated['password'],
                ipAddress: $request->ip() ?? '0.0.0.0',
                userAgent: $request->userAgent() ?? 'Unknown',
                computerName: $request->header(
                    'X-Computer-Name',
                    'UNKNOWN'
                ),
            );


            Session::put(
                'domain_session_id',
                $loginSession->sessionId()->value()
            );


            Session::put(
                'domain_user_id',
                $loginSession->userId()->value()
            );


            Auth::loginUsingId(
                $loginSession->userId()->value()
            );


            return redirect('/dashboard');


        } catch (InvalidArgumentException $exception) {


            return back()
                ->withInput(
                    $request->only('username')
                )
                ->withErrors([
                    'username' => $exception->getMessage(),
                ]);

        }
    }
}
