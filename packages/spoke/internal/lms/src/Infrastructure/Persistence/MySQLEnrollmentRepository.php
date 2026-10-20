<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Infrastructure\Persistence;

use DateTimeImmutable;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Spoke\Lms\Domain\Entity\Enrollment;
use SovereignStack\Spoke\Lms\Domain\Repository\EnrollmentRepositoryInterface;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseId, EnrollmentId, EnrollmentStatus};

/**
 * MySQL implementation of EnrollmentRepositoryInterface.
 *
 * Uses the actual ConnectionInterface API (prepare/execute/fetch/fetchAll) —
 * same pattern as MySQLCourseRepository (PR #355) and MySQLModuleRepository
 * (PR #356). Per SAAI directive: "Do not expand the public DBAL interface
 * merely to accommodate an LMS repository that uses nonexistent methods."
 *
 * Maps to lms_enrollments table (migration 003).
 *
 * Column-mapping note: the DB column is `enrolled_at` (past-tense verb form),
 * but the entity property is `createdAt` (generic creation timestamp). The
 * hydrate() and save() methods translate between these names. This is the
 * kind of impedance mismatch that happens when migrations and entities are
 * authored by different agents at different times — preserved here rather
 * than "fixed" by renaming either side (which would require frozen-contract
 * amendment on the entity side).
 *
 * Progress persistence: this repository does NOT persist the Enrollment's
 * in-memory `progress` array. Progress is a separate aggregate root with
 * its own table (lms_progress, migration 004) and will be persisted by a
 * separate ProgressRepository (PR-A2). The save() method here persists only
 * the enrollment row itself.
 */
final class MySQLEnrollmentRepository implements EnrollmentRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {
    }

    public function get(EnrollmentId $id): ?Enrollment
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM lms_enrollments WHERE id = :id',
        );
        $stmt->execute(['id' => (string) $id]);
        /** @var array<string, string|null>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function findByCourseAndLearner(CourseId $courseId, UserId $learnerId): ?Enrollment
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM lms_enrollments '
            . 'WHERE course_id = :course_id AND learner_id = :learner_id '
            . 'ORDER BY enrolled_at DESC LIMIT 1',
        );
        $stmt->execute([
            'course_id'   => (string) $courseId,
            'learner_id'  => (string) $learnerId,
        ]);
        /** @var array<string, string|null>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function save(Enrollment $enrollment): void
    {
        $exists = $this->get($enrollment->id()) !== null;
        $data = [
            'id'           => (string) $enrollment->id(),
            'course_id'    => (string) $enrollment->courseId(),
            'learner_id'   => (string) $enrollment->learnerId(),
            'status'       => $enrollment->status()->value,
            'enrolled_at'  => $enrollment->createdAt()->format('Y-m-d H:i:s.u'),
            'completed_at' => $enrollment->completedAt()?->format('Y-m-d H:i:s.u'),
        ];

        if ($exists) {
            // UPDATE: only mutable columns. course_id, learner_id, enrolled_at
            // are immutable post-creation (would invalidate the unique key
            // uq_lms_enrollments_course_learner and break audit history).
            $stmt = $this->connection->prepare(
                'UPDATE lms_enrollments SET status = :status, completed_at = :completed_at '
                . 'WHERE id = :id',
            );
            // Re-bind id explicitly for the UPDATE WHERE clause
            $updateData = [
                'id'           => $data['id'],
                'status'       => $data['status'],
                'completed_at' => $data['completed_at'],
            ];
            $stmt->execute($updateData);
        } else {
            $stmt = $this->connection->prepare(
                'INSERT INTO lms_enrollments (id, course_id, learner_id, status, enrolled_at, completed_at) '
                . 'VALUES (:id, :course_id, :learner_id, :status, :enrolled_at, :completed_at)',
            );
            $stmt->execute($data);
        }
    }

    /**
     * Find all enrollments for a given learner, ordered by most-recently-enrolled first.
     *
     * Used by EnrollmentApplicationService::listEnrollmentsForLearner().
     * Not on the frozen EnrollmentRepositoryInterface (which is intentionally
     * minimal) — added as a public method on the concrete implementation.
     * The application service depends on the concrete MySQL implementation
     * for this method; if a future refactor promotes this to the interface,
     * it becomes a SemVer-minor interface addition.
     *
     * @return Enrollment[]
     */
    public function findByLearner(UserId $learnerId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM lms_enrollments WHERE learner_id = :learner_id '
            . 'ORDER BY enrolled_at DESC',
        );
        $stmt->execute(['learner_id' => (string) $learnerId]);
        /** @var array<int, array<string, string|null>> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $enrollments = [];
        foreach ($rows as $row) {
            $enrollments[] = $this->hydrate($row);
        }

        return $enrollments;
    }

    /**
     * Find all enrollments for a given course, ordered by most-recently-enrolled first.
     *
     * Used by EnrollmentApplicationService::listEnrollmentsForCourse().
     * Same SemVer-minor considerations as findByLearner().
     *
     * @return Enrollment[]
     */
    public function findByCourse(CourseId $courseId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM lms_enrollments WHERE course_id = :course_id '
            . 'ORDER BY enrolled_at DESC',
        );
        $stmt->execute(['course_id' => (string) $courseId]);
        /** @var array<int, array<string, string|null>> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $enrollments = [];
        foreach ($rows as $row) {
            $enrollments[] = $this->hydrate($row);
        }

        return $enrollments;
    }

    /**
     * @param array<string, string|null> $row
     */
    private function hydrate(array $row): Enrollment
    {
        $completedAt = isset($row['completed_at'])
            ? new DateTimeImmutable((string) $row['completed_at'])
            : null;

        return Enrollment::restoreFromPersistence(
            id: new EnrollmentId((string) $row['id']),
            courseId: new CourseId((string) $row['course_id']),
            learnerId: new UserId((string) $row['learner_id']),
            status: EnrollmentStatus::from((string) $row['status']),
            createdAt: new DateTimeImmutable((string) $row['enrolled_at']),
            completedAt: $completedAt,
        );
    }
}
