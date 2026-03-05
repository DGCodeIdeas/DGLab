<?php
/**
 * DGLab Async Service Interface
 * 
 * Interface for services that support asynchronous processing.
 * Async services dispatch jobs to be processed in the background,
 * allowing the API to return immediately with a job ID.
 * 
 * @package DGLab\Services\Contracts
 */

namespace DGLab\Services\Contracts;

/**
 * Interface AsyncServiceInterface
 * 
 * Services implementing this interface can dispatch long-running
 * tasks to be processed asynchronously, improving API response times.
 * 
 * Implementation Requirements:
 * - dispatch() must create a job and return job ID immediately
 * - checkStatus() must return current job status
 * - retrieveResult() must return final results when complete
 * - cancel() must stop a running job if possible
 */
interface AsyncServiceInterface extends ServiceInterface
{
    /**
     * Dispatch an async job
     * 
     * Creates a job record and queues it for background processing.
     * Returns immediately with a job ID for status tracking.
     * 
     * @param array $input Validated input data
     * @return array Job information including:
     *   - job_id: string Unique job identifier
     *   - status: string Initial status (usually 'pending')
     *   - estimated_time: int Estimated processing time in seconds
     *   - status_url: string URL to check job status
     */
    public function dispatch(array $input): array;

    /**
     * Check job status
     * 
     * Returns the current status of an async job.
     * 
     * @param string $jobId The job identifier
     * @return array Status information including:
     *   - job_id: string Job identifier
     *   - status: string Current status (pending, processing, completed, failed, cancelled)
     *   - progress: int Progress percentage (0-100)
     *   - message: string Status message or error description
     *   - created_at: string Job creation timestamp
     *   - started_at: string Processing start timestamp (if started)
     *   - completed_at: string Completion timestamp (if finished)
     */
    public function checkStatus(string $jobId): array;

    /**
     * Retrieve job result
     * 
     * Returns the final result of a completed job.
     * 
     * @param string $jobId The job identifier
     * @return array Job results (same format as process() return)
     * @throws \RuntimeException If job is not complete or failed
     */
    public function retrieveResult(string $jobId): array;

    /**
     * Cancel a job
     * 
     * Attempts to cancel a pending or processing job.
     * 
     * @param string $jobId The job identifier
     * @return bool True if cancellation was successful
     */
    public function cancel(string $jobId): bool;

    /**
     * Get job queue position
     * 
     * Returns the position in queue for pending jobs.
     * 
     * @param string $jobId The job identifier
     * @return int|null Queue position (null if not in queue)
     */
    public function getQueuePosition(string $jobId): ?int;

    /**
     * List user's jobs
     * 
     * Returns a list of jobs for the current user/session.
     * 
     * @param string $status Filter by status (optional)
     * @param int $limit Maximum number of jobs to return
     * @return array List of job summaries
     */
    public function listJobs(?string $status = null, int $limit = 10): array;

    /**
     * Retry a failed job
     * 
     * Creates a new job based on a failed one.
     * 
     * @param string $jobId The original job identifier
     * @return array New job information
     */
    public function retry(string $jobId): array;

    /**
     * Clean up old jobs
     * 
     * Removes completed/failed jobs older than specified days.
     * 
     * @param int $days Age in days
     * @return int Number of jobs removed
     */
    public function cleanup(int $days = 30): int;
}
