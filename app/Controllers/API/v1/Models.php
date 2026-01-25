<?php

namespace App\Controllers\API\v1;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use StarDust\Services\ModelsManager;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: "Models",
    description: "Management of Data Models (Blueprints)"
)]
class Models extends ResourceController
{
    use ResponseTrait;

    protected ModelsManager $manager;

    public function __construct()
    {
        $this->manager = service('modelsManager');
    }

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
    public function index()
    {
        // Direct Model access for efficient pagination
        /** @var \StarDust\Models\ModelsModel $model */
        $model = model('StarDust\Models\ModelsModel');

        $page    = (int)($this->request->getVar('page') ?? 1);
        $perPage = 20;

        // Count total for pager
        $total = $model->stardust()->countAllResults();

        // Fetch data
        $data = $model->stardust()
            ->orderBy('created_at', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return $this->respond([
            'models' => $data,
            'pager'  => [
                'currentPage' => $page,
                'perPage'     => $perPage,
                'total'       => $total,
                'lastPage'    => ceil($total / $perPage)
            ]
        ]);
    }

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
    public function show($id = null)
    {
        $model = $this->manager->find($id);

        if (!$model) {
            return $this->failNotFound(lang('StarGate.modelNotFound', [$id]));
        }

        return $this->respond($model);
    }

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
    public function create()
    {
        $rules = [
            'name'   => 'required|min_length[3]|max_length[255]',
            'fields' => 'required|valid_json' // Strict check for 'fields' Key
        ];

        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }

        $input = $this->request->getJSON(true);
        $input['fields'] = $this->validateFieldsJson($input['fields']);

        if ($input['fields'] === false) {
            return $this->fail(['fields' => lang('StarGate.modelInvalidFields')]);
        }

        try {
            $modelId = $this->manager->create($input, auth()->id());
            return $this->respondCreated([
                'id' => $modelId,
                'message' => lang('StarGate.modelCreated')
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

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
    public function update($id = null)
    {
        if (!$this->manager->find($id)) {
            return $this->failNotFound(lang('StarGate.modelNotFound', [$id]));
        }

        $rules = [
            'name'   => 'permit_empty|min_length[3]|max_length[255]',
            'fields' => 'permit_empty|valid_json'
        ];

        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }

        $input = $this->request->getJSON(true); // Get all input, including JSON raw

        // Fetch current state to support partial updates
        $current = $this->manager->find($id);

        // Merge input with current data
        $finalData = [];
        $finalData['name']   = $input['name'] ?? $current['name'];
        // Fields needs special handling: if input has it, validate it. If not, use current.
        if (isset($input['fields'])) {
            $checked = $this->validateFieldsJson($input['fields']);
            if ($checked === false) {
                return $this->fail(['fields' => lang('StarGate.modelInvalidFields')]);
            }
            $finalData['fields'] = $checked;
        } else {
            // Use existing fields. Note: find() returns fields as JSON string usually? 
            // Let's verify. ModelsManager returns ->get()->getResultArray().
            // DB returns JSON string from fields column.
            $finalData['fields'] = $current['fields'];
        }

        // Also carry over slug if it existed in input (though valid rules removed it, manager might use it)
        // But for update, we usually don't change slug in this simple logic.
        // Let's ignore slug update for now to avoid complexity or allow if input has it.
        // We removed validation for slug, so it's safe to pass if present.
        if (isset($input['slug'])) $finalData['slug'] = $input['slug'];

        try {
            $this->manager->update($id, $finalData, auth()->id());
            return $this->respond([
                'id' => $id,
                'message' => lang('StarGate.modelUpdated')
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

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
    public function delete($id = null)
    {
        if (!$this->manager->find($id)) {
            return $this->failNotFound(lang('StarGate.modelNotFound', [$id]));
        }

        try {
            $this->manager->deleteModels([$id], auth()->id());
            return $this->respondDeleted(['message' => lang('StarGate.modelDeleted')]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * Helper to ensure the JSON fields contain minimum required attributes.
     * StarDust requires 'id' and 'type' for every field.
     */
    private function validateFieldsJson($jsonString)
    {
        $data = json_decode($jsonString, true);
        if (!is_array($data)) return false;

        foreach ($data as $field) {
            if (!isset($field['id']) || !isset($field['type'])) {
                return false;
            }
        }

        // Return re-encoded valid JSON (sanitized) to be safe, 
        // or just return the original if we trust it.
        // Returning original string since we just needed to validate structure.
        return $jsonString;
    }
}
