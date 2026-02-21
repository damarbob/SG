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
        // 1. Parse Request
        $qp = \App\Libraries\RequestQueryParser::parse($this->request);

        // 2. Inject global search into the generic filter pipeline
        $filters = $qp['filters'];
        if ($qp['q'] !== null) {
            $filters['q'] = $qp['q'];
        }

        // 3. Create Search Criteria
        $criteria = new ModelSearchCriteria(
            searchQuery: $filters['q'] ?? null,
            createdAfter: $filters['created_at']['gt'] ?? null,
            createdBefore: $filters['created_at']['lt'] ?? null,
            updatedAfter: $filters['updated_at']['gt'] ?? null,
            updatedBefore: $filters['updated_at']['lt'] ?? null,
            ids: $filters['ids'] ?? null,
            sort: $qp['sort'] ?? null,
        );

        // 3b. Field Projection
        if (!empty($qp['fields'])) {
            $fieldsToSelect = array_unique(array_merge(['id'], $qp['fields']));
            $criteria = $criteria->withSelectedFields($fieldsToSelect);
        }

        // 4. Get Data
        $total = $this->manager->count($criteria);

        $data = $this->manager->paginate($qp['page'], $qp['limit'], $criteria);

        // 5. Construct Response Envelope
        $response = [
            'meta' => [
                'code' => 200,
                'timestamp' => time()
            ],
            'data' => $data,
            'pagination' => [
                'current_page' => $qp['page'],
                'per_page'     => $qp['limit'],
                'total_items'  => $total,
                'total_pages'  => (int) ceil($total / $qp['limit'])
            ]
        ];

        // 6. Echo Request ID (Concurrency Control)
        if ($qp['request_id']) {
            $response['request_id'] = $qp['request_id'];
        }

        return $this->respond($response);
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

        // 3. Update via Manager (Partial Update supported)
        $input = $this->request->getJSON(true);

        if (empty($input)) {
            return $this->respond(['id' => $id, 'message' => lang('StarGate.noChanges')]);
        }

        try {
            $this->manager->update($id, $input, auth()->id());
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
