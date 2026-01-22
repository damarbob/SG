<?php

namespace App\Controllers\API\v1;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Shield\Validation\ValidationRules;
use CodeIgniter\Events\Events;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\I18n\Time;

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
        // 1. Get the validation rules
        $rules = $this->getValidationRules();

        // 2. Get the input
        // We validate the JSON input directly
        $input = $this->request->getJsonVar(null, true);

        // 3. Validate Input
        if (! $this->validateData($input, $rules)) {
            return $this->fail($this->validator->getErrors());
        }

        // 4. Retrieve Credentials
        // We get the validated data to ensure we only use fields that were in the rules
        $validData = $this->validator->getValidated();

        // Separate the password from the other credentials
        $password = $validData['password'];
        $credentials = $validData;
        unset($credentials['password']);

        // 5. Retrieve the User (without logging them in via Session)
        /** @var \CodeIgniter\Shield\Models\UserModel */
        $users = auth()->getProvider(); // Get Configured User Provider
        $user  = $users->findByCredentials($credentials);

        // 6. Verify the Password Manually (Mitigate Timing Attacks)
        // If user is not found, we still want to run the password verify to mimic the time it takes.
        // We use a dummy hash for this purpose. 
        // Note: The dummy hash should be a valid bcrypt hash.
        $dummyHash = '$2a$12$9xQtNhMMkCGYv.0z.PW1H.oyJZO76zfob8qdes3H/2LYqNdvrSCa2';

        $pHash = ($user) ? $user->password_hash : $dummyHash;
        $check = service('passwords')->verify($password, $pHash);

        if (! $user || ! $check) {
            \CodeIgniter\Events\Events::trigger('failedLogin', [
                'credentials' => $credentials,
                'user'        => $user,
            ]);

            return $this->failUnauthorized(lang('Auth.badAttempt'));
        }

        // 7. Check User Status
        if (! $user->active) {
            return $this->failUnauthorized(lang('StarGate.notActivated'));
        }

        if ($user->isBanned()) {
            return $this->failUnauthorized(lang('StarGate.userBanned'));
        }

        // 8. Generate the Access Token
        $token = $user->generateAccessToken('api-login');

        // 9. Record Login Attempt
        /** @var \CodeIgniter\Shield\Models\LoginModel $loginModel */
        $loginModel = model(\CodeIgniter\Shield\Models\LoginModel::class);
        $loginModel->recordLoginAttempt(
            'email_password',
            implode('|', $credentials), // Use credentials as identifier (e.g. email)
            true,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            $user->id
        );

        // 10. Trigger Login Event
        \CodeIgniter\Events\Events::trigger('login', $user);

        // 11. Return the Raw Token
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

    /**
     * Log the user out (revoke current token)
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function logout()
    {
        $source = $this->request->getHeaderLine('Authorization');
        // pattern: Bearer <token>
        if (preg_match('/Bearer\s(\S+)/', $source, $matches)) {
            $rawToken = $matches[1];
            if ($user = auth()->user()) {
                $user->revokeAccessToken($rawToken);
            }
        }

        return $this->respondNoContent();
    }

    /**
     * Get current user details
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function me()
    {
        $user = auth()->user();
        return $this->respond([
            'id'       => $user->id,
            'username' => $user->username,
            'email'    => $user->email,
        ]);
    }

    /**
     * Request a Magic Link
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function magicLink()
    {
        if (! setting('Auth.allowMagicLinkLogins')) {
            return $this->failForbidden(lang('Auth.magicLinkDisabled'));
        }

        $rules = [
            'email' => 'required|valid_email|max_length[254]',
        ];

        $input = $this->request->getJsonVar(null, true);

        if (! $this->validateData($input, $rules)) {
            return $this->fail($this->validator->getErrors());
        }

        $callbackUrl = config('StarGate')->magicLinkCallbackUrl;

        if (empty($callbackUrl)) {
            return $this->failServerError(lang('StarGate.magicLinkNoCallback'));
        }

        $email = $input['email'];

        /** @var UserModel $users */
        $users = auth()->getProvider();
        $user  = $users->findByCredentials(['email' => $email]);

        // If user not found, we silently return success to prevent email enumeration
        if ($user === null) {
            return $this->respond(['message' => lang('Auth.checkYourEmail')]);
        }

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        // Delete any previous magic-link identities
        $identityModel->deleteIdentitiesByType($user, Session::ID_TYPE_MAGIC_LINK);

        // Generate the code and save it as an identity
        $token = bin2hex(random_bytes(10));

        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => Session::ID_TYPE_MAGIC_LINK,
            'secret'  => $token,
            'expires' => Time::now()->addSeconds(setting('Auth.magicLinkLifetime')),
        ]);

        // Send Email
        helper('email');
        $helper = emailer(['mailType' => 'html']);
        $email  = $helper->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '');
        $email->setTo($user->email);
        $email->setSubject(lang('Auth.magicLinkSubject'));

        $email->setMessage(view(
            'Auth/Email/magic_link_email',
            [
                'token'        => $token,
                'user'         => $user,
                'callback_url' => $callbackUrl,
                'ipAddress'    => $this->request->getIPAddress(),
            ]
        ));

        if ($email->send(false) === false) {
            log_message('error', $email->printDebugger(['headers']));
            return $this->failServerError(lang('Auth.unableSendEmailToUser', [$user->email]));
        }

        return $this->respond(['message' => lang('Auth.checkYourEmail')]);
    }

    /**
     * Verify Magic Link and Return Access Token
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function verifyMagicLink()
    {
        if (! setting('Auth.allowMagicLinkLogins')) {
            return $this->failForbidden(lang('Auth.magicLinkDisabled'));
        }

        $input = $this->request->getJsonVar(null, true);
        $rules = [
            'token' => [
                'rules' => 'required|string',
                'errors' => [
                    'required' => lang('StarGate.tokenRequired')
                ]
            ]
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $token = $input['token'];

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        // Check for token
        $identity = $identityModel->getIdentityBySecret(Session::ID_TYPE_MAGIC_LINK, $token);

        if ($identity === null) {
            return $this->failUnauthorized(lang('Auth.magicTokenNotFound'));
        }

        // Check expiration
        if (Time::now()->isAfter($identity->expires)) {
            return $this->failUnauthorized(lang('Auth.magicLinkExpired'));
        }

        // Get the user
        /** @var UserModel $users */
        $users = auth()->getProvider();
        $user  = $users->findById($identity->user_id);

        if (! $user) {
            return $this->failUnauthorized(lang('StarGate.userNotFound'));
        }

        // Delete the identity so it cannot be used again
        $identityModel->delete($identity->id);

        // Check if user is active/banned
        if (! $user->active) {
            return $this->failUnauthorized(lang('StarGate.notActivated'));
        }
        if ($user->isBanned()) {
            return $this->failUnauthorized(lang('StarGate.userBanned'));
        }

        // Generate Access Token
        $accessToken = $user->generateAccessToken('magic-link-login');

        // Record Login Attempt
        /** @var \CodeIgniter\Shield\Models\LoginModel $loginModel */
        $loginModel = model(\CodeIgniter\Shield\Models\LoginModel::class);
        $loginModel->recordLoginAttempt(
            Session::ID_TYPE_MAGIC_LINK,
            $user->email, // Identifier
            true,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            $user->id
        );

        Events::trigger('magicLogin', $user);
        Events::trigger('login', $user);

        return $this->respond([
            'status'       => 200,
            'access_token' => $accessToken->raw_token,
            'user'         => [
                'id'       => $user->id,
                'username' => $user->username,
                'email'    => $user->email,
            ]
        ]);
    }
}
