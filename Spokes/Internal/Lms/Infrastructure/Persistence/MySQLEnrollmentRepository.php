<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Lms\Infrastructure\Persistence;

use DateTimeImmutable;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Internal\Lms\Domain\Entity\Enrollment;
use SovereignStack\Internal\Lms\Domain\Repository\EnrollmentRepositoryInterface;
use SovereignStack\Internal\Lms\Domain\ValueObject\{CourseId, EnrollmentId, EnrollmentStatus};

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

    public function findActiveByLearnerAndCourse(UserId $learnerId, CourseId $courseId): ?Enrollment
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM lms_enrollments '
            . 'WHERE learner_id = :learner_id AND course_id = :course_id '
            . 'AND status = :status',
        );
        $stmt->execute([
            'learner_id' => (string) $learnerId,
            'course_id' => (string) $courseId,
            'status' => EnrollmentStatus::Active->value,
        ]);
        /** @var array<string, string|null>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function findByIdWithLock(EnrollmentId $id): ?Enrollment
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM lms_enrollments WHERE id = :id FOR UPDATE',
        );
        $stmt->execute(['id' => (string) $id]);
        /** @var array<string, string|null>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function save(Enrollment $enrollment): void
    {
        $exists = $this->get($enrollment->id()) !== null;
        $data = [
            'id'             => (string) $enrollment->id(),
            'course_id'      => (string) $enrollment->courseId(),
            'course_version' => $enrollment->courseVersion(),
            'learner_id'     => (string) $enrollment->learnerId(),
            'status'         => $enrollment->status()->value,
            'enrolled_at'    => $enrollment->createdAt()->format('Y-m-d H:i:s.u'),
            'completed_at'   => $enrollment->completedAt()?->format('Y-m-d H:i:s.u'),
        ];

        if ($exists) {
            $stmt = $this->connection->prepare(
                'UPDATE lms_enrollments SET status = :status, completed_at = :completed_at '
                . 'WHERE id = :id',
            );
            $updateData = [
                'id'           => $data['id'],
                'status'       => $data['status'],
                'completed_at' => $data['completed_at'],
            ];
            $stmt->execute($updateData);
        } else {
            $stmt = $this->connection->prepare(
                'INSERT INTO lms_enrollments (id, course_id, course_version, learner_id, status, enrolled_at, completed_at) '
                . 'VALUES (:id, :course_id, :course_version, :learner_id, :status, :enrolled_at, :completed_at)',
            );
            $data['course_version'] = $enrollment->courseVersion();
            $stmt->execute($data);
        }
    }

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

    private function hydrate(array $row): Enrollment
    {
        $completedAt = isset($row['completed_at'])
            ? new DateTimeImmutable($row['completed_at'])
            : null;

        return Enrollment::restoreFromPersistence(
            id: new EnrollmentId((string) $row['id']),
            courseId: new CourseId((string) $row['course_id']),
            courseVersion: isset($row['course_version']) ? (int) $row['course_version'] : 1,
            learnerId: new UserId((string) $row['learner_id']),
            status: EnrollmentStatus::from((string) $row['status']),
            createdAt: new DateTimeImmutable((string) $row['enrolled_at']),
            completedAt: $completedAt,
        );
    }
}