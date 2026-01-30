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
        $page    = (int)($this->request->getVar('page') ?? 1);
        $perPage = 20;

        // Count total for pager
        $total = $this->manager->count();

        // Fetch data
        $data = $this->manager->paginate($page, $perPage);

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
        // Fetch current state to support partial updates
        $current = $this->manager->find($id);

        if (!$current) {
            return $this->failNotFound(lang('StarGate.modelNotFound', [$id]));
        }

        $rules = [
            'name'   => 'permit_empty|min_length[3]|max_length[255]',
            'fields' => 'permit_empty|valid_json'
        ];

        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }

        $input = $this->request->getJSON(true); // Get all input
        $updateData = [];

        // Map inputs to update array
        if (isset($input['name'])) $updateData['name'] = $input['name'];
        if (isset($input['slug'])) $updateData['slug'] = $input['slug'];

        if (isset($input['fields'])) {
            // Strict validation moved to ModelsManager
            $updateData['fields'] = $input['fields'];
        }

        if (empty($updateData)) {
            return $this->respond(['id' => $id, 'message' => 'Nothing to update']);
        }

        try {
            $this->manager->update($id, $updateData, auth()->id());
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
}
