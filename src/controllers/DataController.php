<?php

class DataController
{
    /**
     * Get the raw data.json file content
     * GET/POST /data.json (requires admin authentication)
     */
    public function indexAction(): void
    {
        $dataFile = __DIR__ . '/../../data.json';

        // Check if file exists
        if (!file_exists($dataFile)) {
            JsonResponse::error('Data file not found', 404);
            return;
        }

        // Read and decode the JSON file
        $jsonContent = file_get_contents($dataFile);
        $data = json_decode($jsonContent, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            JsonResponse::serverError('Failed to parse data file: ' . json_last_error_msg());
            return;
        }

        // Return the data
        JsonResponse::success($data);
    }
}
