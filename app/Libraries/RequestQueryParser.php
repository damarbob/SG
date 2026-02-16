<?php

namespace App\Libraries;

/**
 * Standardizes parsing of API query parameters.
 */
class RequestQueryParser
{
    /**
     * Parse standard grid parameters.
     *
     * @param \CodeIgniter\HTTP\IncomingRequest $request
     * @return array{page: int, limit: int, q: ?string, sort: array, filters: array, fields: array, request_id: ?string}
     */
    public static function parse(\CodeIgniter\HTTP\IncomingRequest $request): array
    {
        // 1. Pagination
        $page = (int)($request->getVar('page') ?? 1);
        $limit = (int)($request->getVar('limit') ?? $request->getVar('per_page') ?? 20); // 'limit' is standard, 'per_page' is fallback

        if ($limit < 1) $limit = 20;
        if ($limit > 100) $limit = 100;

        // 2. Search
        $q = trim($request->getVar('q') ?? '');

        // 3. Sorting (sort=-created_at,name)
        $sortParam = $request->getVar('sort');
        $sorts = [];
        if ($sortParam) {
            $fields = explode(',', $sortParam);
            foreach ($fields as $field) {
                $field = trim($field);
                if (empty($field)) continue;

                if (str_starts_with($field, '-')) {
                    $sorts[substr($field, 1)] = 'DESC';
                } else {
                    $sorts[$field] = 'ASC';
                }
            }
        }

        // 4. Filtering (filter[status]=published, filter[price][gt]=10)
        // Request::getVar('filter') returns array if sent as array
        $filters = $request->getVar('filter') ?? [];
        if (!is_array($filters)) {
            $filters = [];
            // Or throw exception? Spec says "recommended 400", but soft fail is safer for now.
        }

        // 5. Fields Projection (fields=id,name)
        $fieldsParam = $request->getVar('fields');
        $fields = [];
        if ($fieldsParam) {
            $fields = array_map('trim', explode(',', $fieldsParam));
        }

        // 6. Request ID (Concurrency Control)
        $requestId = $request->getVar('request_id'); // old 'draw'

        return [
            'page'       => $page,
            'limit'      => $limit,
            'q'          => empty($q) ? null : $q,
            'sort'       => $sorts,
            'filters'    => $filters,
            'fields'     => $fields,
            'request_id' => $requestId
        ];
    }
}
