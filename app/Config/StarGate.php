<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class StarGate extends BaseConfig
{
    /**
     * --------------------------------------------------------------------
     * App Name
     * --------------------------------------------------------------------
     * The name of your application.
     */
    public string $appName = 'StarGate';

    /**
     * --------------------------------------------------------------------
     * Magic Link Callback URL
     * --------------------------------------------------------------------
     * The URL to redirect the user to after they click the magic link
     * in their email. This should be a URL in your Frontend Application.
     * The token will be appended as a query parameter: ?token=...
     */
    public string $magicLinkCallbackUrl = '';
}
