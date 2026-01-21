<?php

namespace App\Controllers\API\v1;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Shield\Validation\ValidationRules;
use CodeIgniter\Events\Events;
use CodeIgniter\Shield\Models\UserModel;

class Auth extends ResourceController
{
    /**
     * Register a new user
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function register()
    {
        // 1. Check if registration is allowed
        if (! setting('Auth.allowRegistration')) {
            return $this->failForbidden(lang('Auth.registerDisabled'));
        }

        // 2. Get the validation rules
        $rules = $this->getValidationRules(true);

        // 3. Validate Input
        // We validate the JSON input directly
        $input = $this->request->getJsonVar(null, true); // Get all JSON as array

        if (! $this->validateData($input, $rules)) {
            return $this->fail($this->validator->getErrors());
        }

        // 4. Create the User
        /** @var UserModel $users */
        $users = auth()->getProvider(); // Get Configured User Provider

        // Create the user entity with allowed fields
        $allowedFields = array_keys($rules);
        $userData = array_intersect_key($input, array_flip($allowedFields));

        $user = $users->createNewUser($userData);

        try {
            // Save the user
            if (! $users->save($user)) {
                return $this->fail($users->errors());
            }
        } catch (\CodeIgniter\Shield\Exceptions\ValidationException $e) {
            return $this->fail($users->errors());
        }

        // 5. Post-Registration Setup

        // Re-fetch user to get the ID and complete object
        $user = $users->findById($users->getInsertID());

        // Add to default group
        $users->addToDefaultGroup($user);

        // Trigger the standard 'register' event.
        Events::trigger('register', $user);

        // Activate user
        $user->activate();

        // 6. Return Response
        return $this->respondCreated([
            'status' => 201,
            'message' => lang('Auth.registerSuccess'),
            'user'   => [
                'id'       => $user->id,
                'username' => $user->username,
                'email'    => $user->email,
            ]
        ]);
    }

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

        // 4. Check User Status
        if (! $user->active) {
            return $this->failUnauthorized(lang('Auth.notActivated'));
        }

        if ($user->isBanned()) {
            return $this->failUnauthorized(lang('Auth.userBanned'));
        }

        // 4. Generate the Access Token
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
     * @param bool $isRegistration
     * @return array<string, array<string, list<string>|string>>
     */
    protected function getValidationRules(bool $isRegistration = false): array
    {
        $rules = new ValidationRules();

        if ($isRegistration) {
            return $rules->getRegistrationRules();
        }

        return $rules->getLoginRules();
    }
}
