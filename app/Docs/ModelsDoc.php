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
                name: 'limit',
                in: 'query',
                description: 'Items per page (max 100)',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 20)
            ),
            new OA\Parameter(
                name: 'q',
                in: 'query',
                description: 'Global search query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'sort',
                in: 'query',
                description: 'Sort fields (e.g. -created_at,name)',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'filter',
                in: 'query',
                description: 'Filter array (e.g. filter[status]=published)',
                required: false,
                style: 'deepObject',
                explode: true,
                schema: new OA\Schema(
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(
                        type: 'string'
                    ),
                    example: ['status' => 'published']
                )
            ),
            new OA\Parameter(
                name: 'fields',
                in: 'query',
                description: 'Sparse fieldset (e.g. id,name)',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'request_id',
                in: 'query',
                description: 'Client-side request identifier for concurrency control (e.g. DataTables draw parameter)',
                required: false,
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of models',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'meta',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'code', type: 'integer', example: 200),
                                new OA\Property(property: 'timestamp', type: 'integer')
                            ]
                        ),
                        new OA\Property(
                            property: 'data',
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
                            property: 'pagination',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'per_page', type: 'integer', example: 20),
                                new OA\Property(property: 'total_items', type: 'integer', example: 50),
                                new OA\Property(property: 'total_pages', type: 'integer', example: 3)
                            ]
                        ),
                        new OA\Property(property: 'request_id', type: 'string', example: 'req_123')
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
