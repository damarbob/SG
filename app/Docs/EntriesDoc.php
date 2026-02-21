<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Entries',
    description: 'Entry Management Endpoints'
)]
interface EntriesDoc
{
    /**
     * List Entries
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Get(
        path: '/entries',
        summary: 'List and search entries',
        tags: ['Entries'],
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
                description: 'Filter array (e.g. filter[model_id]=1, filter[created_at][gt]=2024-01-01)',
                required: false,
                style: 'deepObject',
                explode: true,
                schema: new OA\Schema(
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(
                        type: 'string'
                    ),
                    example: ['model_id' => '1']
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
                description: 'Client-side request identifier for concurrency control',
                required: false,
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of entries',
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
                                    new OA\Property(property: 'model_id', type: 'integer', example: 5),
                                    new OA\Property(property: 'name', type: 'string', example: 'My First Post'),
                                    new OA\Property(property: 'fields', type: 'string', example: '{"title":"Hello World"}'),
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
     * Get a specific entry
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Get(
        path: '/entries/{id}',
        summary: 'Get entry details',
        tags: ['Entries'],
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
                description: 'Entry details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'model_id', type: 'integer', example: 5),
                        new OA\Property(property: 'name', type: 'string', example: 'My First Post'),
                        new OA\Property(property: 'fields', type: 'string', example: '{"title":"Hello World"}'),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Entry not found'),
            new OA\Response(response: 401, description: 'Unauthorized')
        ],
        security: [['bearerAuth' => []]]
    )]
    public function show($id = null);

    /**
     * Create a new entry
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Post(
        path: '/entries',
        summary: 'Create a new entry',
        tags: ['Entries'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['model_id', 'name', 'fields'],
                properties: [
                    new OA\Property(property: 'model_id', type: 'integer', example: 5),
                    new OA\Property(property: 'name', type: 'string', example: 'My First Post'),
                    new OA\Property(property: 'fields', type: 'string', example: '{"title":"Hello World"}')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Entry created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'message', type: 'string', example: 'Entry created successfully.')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation Error'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden (requires entries.manage permission)')
        ],
        security: [['bearerAuth' => []]]
    )]
    public function create();

    /**
     * Update an entry
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Put(
        path: '/entries/{id}',
        summary: 'Update an existing entry',
        tags: ['Entries'],
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
                    new OA\Property(property: 'name', type: 'string', example: 'Updated Post Title'),
                    new OA\Property(property: 'fields', type: 'string', example: '{"title":"Updated Title"}')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Entry updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'message', type: 'string', example: 'Entry updated successfully.')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation Error'),
            new OA\Response(response: 404, description: 'Entry not found'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden (requires entries.manage permission)')
        ],
        security: [['bearerAuth' => []]]
    )]
    public function update($id = null);

    /**
     * Delete an entry
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    #[OA\Delete(
        path: '/entries/{id}',
        summary: 'Delete an entry (soft delete)',
        tags: ['Entries'],
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
                description: 'Entry deleted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Entry deleted successfully.')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden (requires entries.manage permission)')
        ],
        security: [['bearerAuth' => []]]
    )]
    public function delete($id = null);
}
