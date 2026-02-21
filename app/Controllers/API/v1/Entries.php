<?php

namespace App\Controllers\API\v1;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use StarDust\Services\EntriesManager;
use StarDust\Data\EntrySearchCriteria;

class Entries extends ResourceController
{
    use ResponseTrait;

    protected EntriesManager $manager;

    public function __construct()
    {
        $this->manager = service('entriesManager');
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
        $criteria = new EntrySearchCriteria(
            searchQuery: $filters['q'] ?? null,
            modelId: isset($filters['model_id']) ? (int) $filters['model_id'] : null,
            ids: $filters['ids'] ?? null,
            createdAfter: $filters['created_at']['gt'] ?? null,
            createdBefore: $filters['created_at']['lt'] ?? null,
            updatedAfter: $filters['updated_at']['gt'] ?? null,
            updatedBefore: $filters['updated_at']['lt'] ?? null,
            sort: $qp['sort'] ?? null,
        );

        // 3b. Forward remaining filters as custom (virtual column) filters
        $reservedKeys = ['q', 'model_id', 'ids', 'created_at', 'updated_at'];
        foreach ($filters as $key => $value) {
            if (! in_array($key, $reservedKeys, true) && is_string($value)) {
                $criteria->addCustomFilter($key, $value);
            }
        }

        // 3c. Field Projection
        if (! empty($qp['fields'])) {
            // Always ensure primary keys or routing requirements are fetched
            $fieldsToSelect = array_unique(array_merge(['id'], $qp['fields']));
            $criteria->selectFields($fieldsToSelect);
        }

        // 4. Get Data
        $total = $this->manager->count($criteria);
        $data  = $this->manager->paginate($qp['page'], $qp['limit'], $criteria);

        // 5. Construct Response Envelope
        $response = [
            'meta'       => [
                'code'      => 200,
                'timestamp' => time(),
            ],
            'data'       => $data,
            'pagination' => [
                'current_page' => $qp['page'],
                'per_page'     => $qp['limit'],
                'total_items'  => $total,
                'total_pages'  => (int) ceil($total / $qp['limit']),
            ],
        ];

        // 6. Echo Request ID (Concurrency Control)
        if ($qp['request_id']) {
            $response['request_id'] = $qp['request_id'];
        }

        return $this->respond($response);
    }

    public function show($id = null)
    {
        $entry = $this->manager->find((int) $id);

        if (! $entry) {
            return $this->failNotFound(lang('StarGate.entryNotFound', [$id]));
        }

        return $this->respond($entry);
    }

    public function create()
    {
        $rules = [
            'model_id' => 'required|numeric',
            'name'     => 'required|min_length[3]|max_length[255]',
            'fields'   => 'required|valid_json',
        ];

        $input = $this->request->getJSON(true);

        if (! $this->validateData($input, $rules)) {
            return $this->fail($this->validator->getErrors());
        }

        // Cast explicitly to an integer (Security Standard: Type Casting)
        $input['model_id'] = (int) $input['model_id'];

        try {
            $entryId = $this->manager->create($input, auth()->id());
            return $this->respondCreated([
                'id'      => $entryId,
                'message' => lang('StarGate.entryCreated'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        } catch (\Exception $e) {
            log_message('error', '[Entries::create] ' . $e->getMessage());
            return $this->failServerError(lang('StarGate.genericError'));
        }
    }

    public function update($id = null)
    {
        // 1. Existence Check
        $current = $this->manager->find((int) $id);

        if (! $current) {
            return $this->failNotFound(lang('StarGate.entryNotFound', [$id]));
        }

        // 2. Validation
        $rules = [
            'name'   => 'permit_empty|min_length[3]|max_length[255]',
            'fields' => 'permit_empty|valid_json',
        ];

        $input = $this->request->getJSON(true);

        if (! $this->validateData($input, $rules)) {
            return $this->fail($this->validator->getErrors());
        }

        // 3. Update via Manager (Partial Update supported)

        if (empty($input)) {
            return $this->respond(['id' => $id, 'message' => lang('StarGate.noChanges')]);
        }

        try {
            $this->manager->update((int) $id, $input, auth()->id());
            return $this->respond([
                'id'      => $id,
                'message' => lang('StarGate.entryUpdated'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        } catch (\Exception $e) {
            log_message('error', '[Entries::update] ' . $e->getMessage());
            return $this->failServerError(lang('StarGate.genericError'));
        }
    }

    public function delete($id = null)
    {
        try {
            // Idempotent Delete: Just try to delete.
            // If it existed, it's gone. If it didn't exist, it's also gone.
            $this->manager->deleteEntries([(int) $id], auth()->id());

            return $this->respondDeleted(['message' => lang('StarGate.entryDeleted')]);
        } catch (\Exception $e) {
            log_message('error', '[Entries::delete] ' . $e->getMessage());
            return $this->failServerError(lang('StarGate.genericError'));
        }
    }
}
