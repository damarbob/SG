<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: "Models",
    description: "Management of Data Models (Blueprints)"
)]
interface ModelsDoc
{
    #[OA\Get(
        path: "/models",
        summary: "List Models",
        description: "Retrieve a paginated list of all active data models.",
        tags: ["Models"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Successful operation",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "models", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "pager", type: "object")
                    ]
                )
            )
        ],
        security: [['bearerAuth' => []]]
    )]
    public function index();

    #[OA\Get(
        path: "/models/{id}",
        summary: "Get Model",
        description: "Retrieve a specific model by ID.",
        tags: ["Models"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Model details",
                content: new OA\JsonContent(type: "object")
            ),
            new OA\Response(response: 404, description: "Model not found")
        ],
        security: [['bearerAuth' => []]]
    )]
    public function show($id = null);

    #[OA\Post(
        path: "/models",
        summary: "Create Model",
        description: "Create a new data model blueprint. Note: The definition key must be 'fields'.",
        tags: ["Models"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "slug", "fields"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Products"),
                    new OA\Property(property: "slug", type: "string", example: "products"),
                    new OA\Property(
                        property: "fields",
                        type: "string",
                        description: "JSON string defining the fields (NOT 'model_fields')",
                        example: "[{\"id\":\"price\",\"type\":\"number\"},{\"id\":\"sku\",\"type\":\"text\"}]"
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Model created successfully"),
            new OA\Response(response: 400, description: "Invalid input")
        ],
        security: [['bearerAuth' => []]]
    )]
    public function create();

    #[OA\Put(
        path: "/models/{id}",
        summary: "Update Model",
        description: "Update an existing model blueprint.",
        tags: ["Models"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "fields", type: "string", description: "Updated JSON fields definition")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Model updated successfully"),
            new OA\Response(response: 404, description: "Model not found")
        ],
        security: [['bearerAuth' => []]]
    )]
    public function update($id = null);

    #[OA\Delete(
        path: "/models/{id}",
        summary: "Delete Model",
        description: "Soft delete a model and its entries.",
        tags: ["Models"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Model deleted successfully"),
            new OA\Response(response: 404, description: "Model not found")
        ],
        security: [['bearerAuth' => []]]
    )]
    public function delete($id = null);
}
