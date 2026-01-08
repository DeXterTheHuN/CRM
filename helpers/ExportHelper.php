<?php
/**
 * Export Helper
 * Utility functions for exporting data to CSV (Excel-compatible)
 */

class ExportHelper
{
    /**
     * Export data to CSV file with UTF-8 BOM for Excel compatibility
     * Uses SEMICOLON delimiter for Hungarian Excel
     */
    public static function exportToCSV(string $filename, array $headers, array $data): void
    {
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM for Excel to recognize encoding correctly
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Write headers - use SEMICOLON delimiter for Hungarian Excel
        fputcsv($output, $headers, ';');

        // Write data rows - use SEMICOLON delimiter for Hungarian Excel
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }

        fclose($output);
        exit;
    }

    /**
     * Generate filename with timestamp
     */
    public static function generateFilename(string $prefix): string
    {
        return $prefix . '_' . date('Y-m-d_H-i-s') . '.csv';
    }

    /**
     * Sanitize data for CSV export
     */
    public static function sanitizeRow(array $row): array
    {
        return array_map(function ($value) {
            // Remove any special characters that might break CSV
            if (is_string($value)) {
                return trim($value);
            }
            return $value;
        }, $row);
    }
}
