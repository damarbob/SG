<?php

namespace App\Controllers\API\v1;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use StarDust\Services\ModelsManager;
use StarDust\Data\ModelSearchCriteria;

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
        $perPage = (int)($this->request->getVar('per_page') ?? 20);

        // Extract filter parameters
        $query         = trim($this->request->getVar('q') ?? '');
        $createdAfter  = $this->request->getVar('created_after');
        $createdBefore = $this->request->getVar('created_before');
        $updatedAfter  = $this->request->getVar('updated_after');
        $updatedBefore = $this->request->getVar('updated_before');
        $idsParam      = $this->request->getVar('ids');
        $ids           = !empty($idsParam) ? array_map('intval', explode(',', $idsParam)) : null;

        // Hard cap to prevent abuse
        if ($perPage > 100) {
            $perPage = 100;
        } elseif ($perPage < 1) {
            $perPage = 20;
        }

        $criteria = new ModelSearchCriteria(
            searchQuery: !empty($query) ? $query : null,
            createdAfter: $createdAfter,
            createdBefore: $createdBefore,
            updatedAfter: $updatedAfter,
            updatedBefore: $updatedBefore,
            ids: $ids
        );

        // Count total for pager (Note: count() doesn't support filtering yet, known limitation)
        $total = $this->manager->count();

        // Fetch data
        $data = $this->manager->paginate($page, $perPage, $criteria);

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
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        } catch (\Exception $e) {
            log_message('error', '[Models::create] ' . $e->getMessage());
            return $this->failServerError(lang('StarGate.genericError'));
        }
    }

    public function update($id = null)
    {
        // 1. Fetch current (Existence Check)
        $current = $this->manager->find($id);

        if (!$current) {
            return $this->failNotFound(lang('StarGate.modelNotFound', [$id]));
        }

        // 2. Validation
        $rules = [
            'name'   => 'permit_empty|min_length[3]|max_length[255]',
            'fields' => 'permit_empty|valid_json'
        ];

        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }

        // 3. Filter Input (Partial Update)
        $input = $this->request->getJSON(true);
        // TODO: We still whitelist fields to prevent pollution, but this could be moved to Manager.
        $allowed = ['name', 'slug', 'fields'];
        $updateData = array_intersect_key($input, array_flip($allowed));

        if (empty($updateData)) {
            return $this->respond(['id' => $id, 'message' => lang('StarGate.noChanges')]);
        }

        try {
            $this->manager->update($id, $updateData, auth()->id());
            return $this->respond([
                'id' => $id,
                'message' => lang('StarGate.modelUpdated')
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        } catch (\Exception $e) {
            log_message('error', '[Models::update] ' . $e->getMessage());
            return $this->failServerError(lang('StarGate.genericError'));
        }
    }

    public function delete($id = null)
    {
        try {
            // Idempotent Delete: Just try to delete. 
            // If it existed, it's gone. If it didn't exist, it's also gone.
            $this->manager->deleteModels([$id], auth()->id());

            return $this->respondDeleted(['message' => lang('StarGate.modelDeleted')]);
        } catch (\Exception $e) {
            log_message('error', '[Models::delete] ' . $e->getMessage());
            return $this->failServerError(lang('StarGate.genericError'));
        }
    }
}
