<?php

namespace App\Controllers\API\v1;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Shield\Validation\ValidationRules;

class Auth extends ResourceController
{
    public function login()
    {
        // 1. Get the credentials
        // Use getJsonVar to enforce JSON, fallback to getPost only if necessary, but strictly void getVar (GET)
        $email    = $this->request->getJsonVar('email') ?? $this->request->getPost('email');
        $password = $this->request->getJsonVar('password') ?? $this->request->getPost('password');

        $rules = $this->getValidationRules();

        // Validate strictly using the inputs we just fetched, to ensure we validate what we use
        if (! $this->validateData(['email' => $email, 'password' => $password], $rules)) {
            return $this->fail($this->validator->getErrors());
        }

        // 2. Retrieve the User (without logging them in via Session)
        /** @var \CodeIgniter\Shield\Models\UserModel */
        $users = auth()->getProvider(); // Get the UserModel
        $user  = $users->findByCredentials(['email' => $email]);

        // 3. Verify the Password Manually (Mitigate Timing Attacks)
        // If user is not found, we still want to run the password verify to mimic the time it takes.
        // We use a dummy hash for this purpose. 
        // Note: The dummy hash should be a valid bcrypt hash.
        $dummyHash = '$2a$12$9xQtNhMMkCGYv.0z.PW1H.oyJZO76zfob8qdes3H/2LYqNdvrSCa2';

        $pHash = ($user) ? $user->password_hash : $dummyHash;
        $check = service('passwords')->verify($password, $pHash);

        if (! $user || ! $check) {
            \CodeIgniter\Events\Events::trigger('failedLogin', [
                'credentials' => ['email' => $email],
                'user'        => $user,
            ]);

            return $this->failUnauthorized(lang('Auth.badAttempt'));
        }

        // 4. Generate the Access Token
        // You can name the token anything, e.g., 'mobile-app'
        $token = $user->generateAccessToken('api-login');

        // 5. Record Login Attempt
        /** @var \CodeIgniter\Shield\Models\LoginModel $loginModel */
        $loginModel = model(\CodeIgniter\Shield\Models\LoginModel::class);
        $loginModel->recordLoginAttempt(
            'email_password',
            $email,
            true,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            $user->id
        );

        // 6. Trigger Login Event
        \CodeIgniter\Events\Events::trigger('login', $user);

        // 7. Return the Raw Token
        return $this->respond([
            'status' => 200,
            'access_token'  => $token->raw_token, // IMPORTANT: accessible only once here
            'user'   => [
                'id'       => $user->id,
                'username' => $user->username,
                'email'    => $user->email,
            ]
        ]);
    }

    /**
     * Returns the rules that should be used for validation.
     *
     * @return array<string, array<string, list<string>|string>>
     */
    protected function getValidationRules(): array
    {
        $rules = new ValidationRules();

        return $rules->getLoginRules();
    }
}
