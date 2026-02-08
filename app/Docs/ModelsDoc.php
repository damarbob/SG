<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Models',
    description: 'Model Management Endpoints'
)]
interface ModelsDoc
{
    /**
     * List Models
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Get(
        path: '/models',
        summary: 'List and search models',
        tags: ['Models'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                description: 'Page number',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                description: 'Items per page (max 100)',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 20)
            ),
            new OA\Parameter(
                name: 'q',
                in: 'query',
                description: 'Search query for name or slug',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'created_after',
                in: 'query',
                description: 'Filter by creation date (after)',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'date-time')
            ),
            new OA\Parameter(
                name: 'created_before',
                in: 'query',
                description: 'Filter by creation date (before)',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'date-time')
            ),
            new OA\Parameter(
                name: 'updated_after',
                in: 'query',
                description: 'Filter by update date (after)',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'date-time')
            ),
            new OA\Parameter(
                name: 'updated_before',
                in: 'query',
                description: 'Filter by update date (before)',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'date-time')
            ),
            new OA\Parameter(
                name: 'ids',
                in: 'query',
                description: 'Filter by IDs (comma separated)',
                required: false,
                schema: new OA\Schema(type: 'string', example: '1,2,3')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of models',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'models',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Blog Post'),
                                    new OA\Property(property: 'slug', type: 'string', example: 'blog-post'),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'pager',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'currentPage', type: 'integer', example: 1),
                                new OA\Property(property: 'perPage', type: 'integer', example: 20),
                                new OA\Property(property: 'total', type: 'integer', example: 50),
                                new OA\Property(property: 'lastPage', type: 'integer', example: 3)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ],
        security: [['bearerAuth' => []]]
    )]
    public function index();

    /**
     * Get a specific model
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Get(
        path: '/models/{id}',
        summary: 'Get model details',
        tags: ['Models'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Model details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Blog Post'),
                        new OA\Property(property: 'slug', type: 'string', example: 'blog-post'),
                        new OA\Property(property: 'fields', type: 'string', example: '[{"id":"title","type":"text"}]'),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Model not found'),
            new OA\Response(response: 401, description: 'Unauthorized')
        ],
        security: [['bearerAuth' => []]]
    )]
    public function show($id = null);

    /**
     * Create a new model
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Post(
        path: '/models',
        summary: 'Create a new model',
        tags: ['Models'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Blog Post'),
                    new OA\Property(property: 'slug', type: 'string', example: 'blog-post'),
                    new OA\Property(property: 'fields', type: 'string', example: '[{"id":"title","type":"text"}]')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Model created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'message', type: 'string', example: 'Model created successfully')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation Error'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden (Superadmin only)')
        ],
        security: [['bearerAuth' => []]]
    )]
    public function create();

    /**
     * Update a model
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Put(
        path: '/models/{id}',
        summary: 'Update an existing model',
        tags: ['Models'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Updated Model Name'),
                    new OA\Property(property: 'slug', type: 'string', example: 'updated-model-slug'),
                    new OA\Property(property: 'fields', type: 'string', example: '[{"id":"title","type":"text"}]')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Model updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'message', type: 'string', example: 'Model updated successfully')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation Error'),
            new OA\Response(response: 404, description: 'Model not found'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden (Superadmin only)')
        ],
        security: [['bearerAuth' => []]]
    )]
    public function update($id = null);

    /**
     * Delete a model
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Delete(
        path: '/models/{id}',
        summary: 'Delete a model',
        tags: ['Models'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Model deleted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Model deleted successfully')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden (Superadmin only)')
        ],
        security: [['bearerAuth' => []]]
    )]
    public function delete($id = null);
}
