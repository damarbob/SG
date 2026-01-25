<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Auth',
    description: 'Authentication Endpoints'
)]
interface AuthDoc
{
    /**
     * Register a new user
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Post(
        path: '/auth/register',
        summary: 'Register a new user',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            description: 'User registration data',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'johndoe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'securePassword123'),
                    new OA\Property(property: 'password_confirm', type: 'string', format: 'password', example: 'securePassword123')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User registered successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 201),
                        new OA\Property(property: 'message', type: 'string', example: 'Registration successful'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'username', type: 'string', example: 'johndoe'),
                                new OA\Property(property: 'email', type: 'string', example: 'john@example.com')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation error')
        ]
    )]
    public function register();

    #[OA\Post(
        path: '/auth/login',
        summary: 'User Login',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            description: 'User login credentials',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'securePassword123')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'access_token', type: 'string', example: 'ab123...'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'username', type: 'string', example: 'johndoe'),
                                new OA\Property(property: 'email', type: 'string', example: 'john@example.com')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid credentials')
        ]
    )]
    public function login();

    /**
     * Log the user out (revoke current token)
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Post(
        path: '/auth/logout',
        summary: 'Log the user out',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 204, description: 'Logged out successfully')
        ],
        security: [['bearerAuth' => []]]
    )]
    public function logout();

    /**
     * Get current user details
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Get(
        path: '/auth/me',
        summary: 'Get current user details',
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'username', type: 'string', example: 'johndoe'),
                        new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ],
        security: [['bearerAuth' => []]]
    )]
    public function me();

    /**
     * Request a Magic Link
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Post(
        path: '/auth/magic-link',
        summary: 'Request a Magic Link',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Magic link sent',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Check your email')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 403, description: 'Magic link login disabled')
        ]
    )]
    public function magicLink();

    /**
     * Verify Magic Link and Return Access Token
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Post(
        path: '/auth/magic-link/verify',
        summary: 'Verify Magic Link',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'token', type: 'string', example: 'magic_link_token_123')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'access_token', type: 'string', example: 'ab123...'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'username', type: 'string', example: 'johndoe'),
                                new OA\Property(property: 'email', type: 'string', example: 'john@example.com')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid or expired token')
        ]
    )]
    public function verifyMagicLink();
}
