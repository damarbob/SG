<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Access Token',
    description: 'Management of Access Tokens'
)]
interface AccessTokenDoc
{
    /**
     * List all access tokens for the current user.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Get(
        path: '/access-token/list',
        summary: 'List user access tokens',
        tags: ['Access Token'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of tokens',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'tokens',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'name', type: 'string'),
                                    new OA\Property(property: 'last_used_at', type: 'string', format: 'date-time')
                                ]
                            )
                        )
                    ]
                )
            )
        ],
        security: [['bearerAuth' => []]]
    )]
    public function index();

    /**
     * Generate a new access token.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Post(
        path: '/access-token/generate',
        summary: 'Generate a new access token',
        tags: ['Access Token'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Mobile App')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Token generated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string', example: 'plain-text-token')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation error')
        ],
        security: [['bearerAuth' => []]]
    )]
    public function generate();

    /**
     * Delete an access token by ID.
     *
     * @param int|string|null $id
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Delete(
        path: '/access-token/{id}',
        summary: 'Delete an access token',
        tags: ['Access Token'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Token deleted'
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid ID'
            )
        ],
        security: [['bearerAuth' => []]]
    )]
    public function delete($id = null);

    /**
     * Revoke a specific token by secret.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Post(
        path: '/access-token/revoke-token',
        summary: 'Revoke a specific token by secret',
        tags: ['Access Token'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'token', type: 'string', description: 'Raw token to revoke')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Token revoked'),
            new OA\Response(response: 400, description: 'Validation error')
        ],
        security: [['bearerAuth' => []]]
    )]
    public function revokeToken();

    #[OA\Post(
        path: '/access-token/revoke-all',
        summary: 'Revoke all tokens for current user',
        tags: ['Access Token'],
        responses: [
            new OA\Response(response: 204, description: 'All tokens revoked')
        ],
        security: [['bearerAuth' => []]]
    )]
    public function revokeAll();
}
