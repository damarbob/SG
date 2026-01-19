<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Shield\Authentication\Authenticators\AccessTokens;
use CodeIgniter\Shield\Models\UserIdentityModel;

class AccessTokenController extends BaseController
{
    use ResponseTrait;

    /**
     * List all access tokens for the current user.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function index()
    {
        $tokens = auth()->user()->accessTokens();

        return $this->respond(['tokens' => $tokens]);
    }

    /**
     * Generate a new access token.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function generate()
    {
        // Validate
        if (! $this->validate(['name' => 'required|string|min_length[1]|max_length[255]'])) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $name  = $this->request->getVar('name');
        $token = auth()->user()->generateAccessToken($name);

        return $this->respondCreated(['token' => $token->raw_token]);
    }

    /**
     * Revoke an access token by name.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function revoke()
    {
        // Validate
        if (! $this->validate(['name' => 'required|string|min_length[1]|max_length[255]'])) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $name = $this->request->getVar('name');

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->where('user_id', auth()->user()->id)
            ->where('type', AccessTokens::ID_TYPE_ACCESS_TOKEN)
            ->where('name', $name)
            ->delete();

        return $this->respondNoContent();
    }

    /**
     * Revoke an access token by raw token (secret).
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function revokeToken()
    {
        // Validate
        if (! $this->validate(['token' => 'required|string'])) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        auth()->user()->revokeAccessToken($this->request->getVar('token'));

        return $this->respondNoContent();
    }

    /**
     * Revoke all access tokens.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function revokeAll()
    {
        auth()->user()->revokeAllAccessTokens();

        return $this->respondNoContent();
    }
}
