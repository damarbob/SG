<?php

namespace App\Controllers\API\v1;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use StarDust\Services\ModelsManager;

class Models extends ResourceController
{
    use ResponseTrait;

    protected ModelsManager $manager;

    public function __construct()
    {
        $this->manager = service('modelsManager');
    }

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

    public function show($id = null)
    {
        $model = $this->manager->find($id);

        if (!$model) {
            return $this->failNotFound(lang('StarGate.modelNotFound', [$id]));
        }

        return $this->respond($model);
    }

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
