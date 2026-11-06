#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use SovereignStack\Core\Container\Container;
use SovereignStack\Core\Config\ConfigRepository;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Application\IdentityInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\{RoleIdentifier, UserId};
use SovereignStack\Internal\Lms\Application\Service\CourseApplicationService;
use SovereignStack\Internal\Lms\Application\Service\EnrollmentApplicationService;
use SovereignStack\Internal\Lms\Application\Service\ProgressApplicationService;
use SovereignStack\Internal\Lms\Domain\Entity\{CourseStatus, ModuleStatus};
use SovereignStack\Internal\Lms\Domain\Repository\{CourseRepositoryInterface, ModuleRepositoryInterface, EnrollmentRepositoryInterface, ProgressRepositoryInterface};
use SovereignStack\Internal\Lms\Infrastructure\Persistence\{MySQLCourseRepository, MySQLModuleRepository, MySQLEnrollmentRepository, MySQLProgressRepository};
use SovereignStack\Internal\Lms\Domain\Entity\{Course, Module, Enrollment, Progress};
use SovereignStack\Internal\Lms\Domain\ValueObject\{CourseId, CourseTitle, CourseSlug, ModuleId, ModuleTitle, EnrollmentId, UserId as LmsUserId, ModuleSortOrder};

require __DIR__ . '/../vendor/autoload.php';

$container = new \SovereignStack\Core\Container\Container();
$config = new \SovereignStack\Core\Config\ConfigRepository([
    'db' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => (int)($_ENV['DB_PORT'] ?? 3306),
        'name' => $_ENV['DB_NAME'] ?? 'dglab_test',
        'user' => $_ENV['DB_USER'] ?? 'dglab_test',
        'pass' => $_ENV['DB_PASS'] ?? 'test_password',
    ],
]);

$container->instance(\SovereignStack\Core\Config\ConfigInterface::class, $config);

// Build DB connection
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $config->get('db.host'),
    $config->get('db.port'),
    $config->get('db.name')
);

$pdo = new \PDO($dsn, $config->get('db.user'), $config->get('db.pass'), [
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    \PDO::ATTR_EMULATE_PREPARES => false,
]);

// Wrap PDO in ConnectionInterface
$connection = new class($pdo) implements \SovereignStack\Core\Database\ConnectionInterface {
    private \PDO $pdo;
    public function __construct(\PDO $pdo) { $this->pdo = $pdo; }
    public function prepare(string $sql): \PDOStatement { return $this->pdo->prepare($sql); }
    public function query(string $sql): \PDOStatement { return $this->pdo->query($sql); }
    public function exec(string $sql): int|false { return $this->pdo->exec($sql); }
    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool { return $this->pdo->commit(); }
    public function rollBack(): bool { return $this->pdo->rollBack(); }
    public function inTransaction(): bool { return $this->pdo->inTransaction(); }
    public function lastInsertId(?string $name = null): string { return $this->pdo->lastInsertId($name); }
};

$container->instance(\SovereignStack\Core\Database\ConnectionInterface::class, $connection);

// Build repositories
$container->instance(\SovereignStack\Internal\Lms\Domain\Repository\CourseRepositoryInterface::class,
    new \SovereignStack\Internal\Lms\Infrastructure\Persistence\MySQLCourseRepository($container->get(\SovereignStack\Core\Database\ConnectionInterface::class)));
$container->instance(\SovereignStack\Internal\Lms\Domain\Repository\ModuleRepositoryInterface::class,
    new \SovereignStack\Internal\Lms\Infrastructure\Persistence\MySQLModuleRepository($container->get(\SovereignStack\Core\Database\ConnectionInterface::class)));
$container->instance(\SovereignStack\Internal\Lms\Domain\Repository\EnrollmentRepositoryInterface::class,
    new \SovereignStack\Internal\Lms\Infrastructure\Persistence\MySQLEnrollmentRepository($container->get(\SovereignStack\Core\Database\ConnectionInterface::class)));
$container->instance(\SovereignStack\Internal\Lms\Domain\Repository\ProgressRepositoryInterface::class,
    new \SovereignStack\Internal\Lms\Infrastructure\Persistence\MySQLProgressRepository($container->get(\SovereignStack\Core\Database\ConnectionInterface::class)));

// Build services
$identity = new class implements \SovereignStack\Hub\Identity\Application\IdentityInterface {
    public function hasAnyRole(\SovereignStack\Hub\Identity\Domain\ValueObject\UserId $userId, \SovereignStack\Hub\Identity\Domain\ValueObject\RoleIdentifier ...$roles): bool {
        return true; // Seed user has all roles
    }
    public function getUser(\SovereignStack\Hub\Identity\Domain\ValueObject\UserId $id): ?array { return null; }
    public function authenticate(\SovereignStack\Hub\Identity\Domain\ValueObject\Email $email, string $password): ?\SovereignStack\Hub\Identity\Domain\ValueObject\UserId { return null; }
    public function hasRole(\SovereignStack\Hub\Identity\Domain\ValueObject\UserId $userId, \SovereignStack\Hub\Identity\Domain\ValueObject\RoleIdentifier $role): bool { return true; }
};

$connection = $container->get(\SovereignStack\Core\Database\ConnectionInterface::class);
$courseRepo = new \SovereignStack\Internal\Lms\Infrastructure\Persistence\MySQLCourseRepository($connection);
$moduleRepo = new \SovereignStack\Internal\Lms\Infrastructure\Persistence\MySQLModuleRepository($connection);
$enrollmentRepo = new \SovereignStack\Internal\Lms\Infrastructure\Persistence\MySQLEnrollmentRepository($connection);
$progressRepo = new \SovereignStack\Internal\Lms\Infrastructure\Persistence\MySQLProgressRepository($connection);

$courseService = new \SovereignStack\Internal\Lms\Application\Service\CourseApplicationService(
    $container->get(\SovereignStack\Internal\Lms\Domain\Repository\CourseRepositoryInterface::class),
    $container->get(\SovereignStack\Internal\Lms\Domain\Repository\ModuleRepositoryInterface::class),
    $container->get(\SovereignStack\Hub\Identity\Application\IdentityInterface::class),
    $container->get(\SovereignStack\Core\Database\ConnectionInterface::class),
);

$enrollmentService = new \SovereignStack\Internal\Lms\Application\Service\EnrollmentApplicationService(
    $container->get(\SovereignStack\Internal\Lms\Domain\Repository\EnrollmentRepositoryInterface::class),
    $container->get(\SovereignStack\Internal\Lms\Domain\Repository\CourseRepositoryInterface::class),
    $container->get(\SovereignStack\Hub\Identity\Application\IdentityInterface::class),
    $container->get(\SovereignStack\Core\Database\ConnectionInterface::class),
);

$progressService = new \SovereignStack\Internal\Lms\Application\Service\ProgressApplicationService(
    $container->get(\SovereignStack\Internal\Lms\Domain\Repository\ProgressRepositoryInterface::class),
    $container->get(\SovereignStack\Internal\Lms\Domain\Repository\EnrollmentRepositoryInterface::class),
    $container->get(\SovereignStack\Internal\Lms\Domain\Repository\CourseRepositoryInterface::class),
    $container->get(\SovereignStack\Internal\Lms\Domain\Repository\ModuleRepositoryInterface::class),
    $container->get(\SovereignStack\Hub\Identity\Application\IdentityInterface::class),
    $container->get(\SovereignStack\Core\Database\ConnectionInterface::class),
);

echo "Seeding database...\n";

// Create demo users
$learnerId = \SovereignStack\Hub\Identity\Domain\ValueObject\UserId::generate();
$instructorId = \SovereignStack\Hub\Identity\Domain\ValueObject\UserId::generate();
$adminId = \SovereignStack\Hub\Identity\Domain\ValueObject\UserId::generate();

// Create demo courses
echo "Creating courses...\n";
$courseService = new \SovereignStack\Internal\Lms\Application\Service\CourseApplicationService(
    $container->get(\SovereignStack\Internal\Lms\Domain\Repository\CourseRepositoryInterface::class),
    $container->get(\SovereignStack\Internal\Lms\Domain\Repository\ModuleRepositoryInterface::class),
    $identity,
    $container->get(\SovereignStack\Core\Database\ConnectionInterface::class),
);

// Course 1: Intro to PHP
$course1 = \SovereignStack\Internal\Lms\Domain\Entity\Course::create(
    title: \SovereignStack\Internal\Lms\Domain\ValueObject\CourseTitle::fromString('Introduction to PHP'),
    slug: \SovereignStack\Internal\Lms\Domain\ValueObject\CourseSlug::fromString('intro-to-php'),
    description: 'Learn the fundamentals of PHP programming from scratch.',
);
$course1->setRequirementsSnapshot(json_encode([
    'requiredModuleIds' => [],
    'moduleMembership' => [],
    'learningContent' => [],
]));
$courseService->createCourse(new \SovereignStack\Internal\Lms\Application\Command\CreateCourseCommand(
    title: 'Introduction to PHP',
    slug: 'intro-to-php',
    description: 'Learn the fundamentals of PHP programming from scratch.',
    createdBy: $instructorId,
));

// Publish course 1
$courseService->publishCourse($course1->id(), $instructorId);

// Add modules to course 1
$module1 = \SovereignStack\Internal\Lms\Domain\Entity\Module::restoreFromPersistence(
    id: \SovereignStack\Internal\Lms\Domain\ValueObject\ModuleId::generate(),
    courseId: $course1->id(),
    title: \SovereignStack\Internal\Lms\Domain\ValueObject\ModuleTitle::fromString('Getting Started with PHP'),
    sortOrder: 0,
    content: 'Welcome to PHP! This module covers installation, basic syntax, and running your first script.',
    createdAt: new \DateTimeImmutable(),
);
$moduleRepo = new \SovereignStack\Internal\Lms\Infrastructure\Persistence\MySQLModuleRepository($container->get(\SovereignStack\Core\Database\ConnectionInterface::class));
$moduleRepo->save($module1);

$module2 = \SovereignStack\Internal\Lms\Domain\Entity\Module::restoreFromPersistence(
    id: \SovereignStack\Internal\Lms\Domain\ValueObject\ModuleId::generate(),
    courseId: $course1->id(),
    title: \SovereignStack\Internal\Lms\Domain\ValueObject\ModuleTitle::fromString('Variables and Data Types'),
    sortOrder: 1,
    content: 'Learn about variables, data types, and type juggling in PHP.',
    createdAt: new \DateTimeImmutable(),
);
$moduleRepo->save($module2);

$module3 = \SovereignStack\Internal\Lms\Domain\Entity\Module::restoreFromPersistence(
    id: \SovereignStack\Internal\Lms\Domain\ValueObject\ModuleId::generate(),
    courseId: $course1->id(),
    title: \SovereignStack\Internal\Lms\Domain\ValueObject\ModuleTitle::fromString('Control Structures'),
    sortOrder: 2,
    content: 'If statements, loops, and control flow in PHP.',
    createdAt: new \DateTimeImmutable(),
);
$moduleRepo->save($module3);

// Course 2: Advanced PHP
$course2 = \SovereignStack\Internal\Lms\Domain\Entity\Course::create(
    title: \SovereignStack\Internal\Lms\Domain\ValueObject\CourseTitle::fromString('Advanced PHP Patterns'),
    slug: \SovereignStack\Internal\Lms\Domain\ValueObject\CourseSlug::fromString('advanced-php-patterns'),
    description: 'Advanced patterns and best practices for PHP development.',
);
$course2->setRequirementsSnapshot(json_encode([
    'requiredModuleIds' => [],
    'moduleMembership' => [],
    'learningContent' => [],
]));
$courseService->createCourse(new \SovereignStack\Internal\Lms\Application\Command\CreateCourseCommand(
    title: 'Advanced PHP Patterns',
    slug: 'advanced-php-patterns',
    description: 'Advanced patterns and best practices for PHP development.',
    createdBy: $instructorId,
));
$courseService->publishCourse($course2->id(), $instructorId);

// Add modules to course 2
$module4 = \SovereignStack\Internal\Lms\Domain\Entity\Module::restoreFromPersistence(
    id: \SovereignStack\Internal\Lms\Domain\ValueObject\ModuleId::generate(),
    courseId: $course2->id(),
    title: \SovereignStack\Internal\Lms\Domain\ValueObject\ModuleTitle::fromString('Design Patterns in PHP'),
    sortOrder: 0,
    content: 'Factory, Singleton, Observer, and Strategy patterns in PHP.',
    createdAt: new \DateTimeImmutable(),
);
$moduleRepo->save($module4);

$module5 = \SovereignStack\Internal\Lms\Domain\Entity\Module::restoreFromPersistence(
    id: \SovereignStack\Internal\Lms\Domain\ValueObject\ModuleId::generate(),
    courseId: $course2->id(),
    title: \SovereignStack\Internal\Lms\Domain\ValueObject\ModuleTitle::fromString('Dependency Injection and Service Containers'),
    sortOrder: 1,
    content: 'Modern PHP dependency injection patterns and PSR-11 containers.',
    createdAt: new \DateTimeImmutable(),
);
$moduleRepo->save($module5);

echo "Creating demo enrollments...\n";
// Create enrollments for learner
$enrollmentService = new \SovereignStack\Internal\Lms\Application\Service\EnrollmentApplicationService(
    new \SovereignStack\Internal\Lms\Infrastructure\Persistence\MySQLEnrollmentRepository($container->get(\SovereignStack\Core\Database\ConnectionInterface::class)),
    new \SovereignStack\Internal\Lms\Infrastructure\Persistence\MySQLCourseRepository($container->get(\SovereignStack\Core\Database\ConnectionInterface::class)),
    $identity,
    $container->get(\SovereignStack\Core\Database\ConnectionInterface::class),
);

// Enroll learner in course 1
$enrollmentService->enroll(new \SovereignStack\Internal\Lms\Application\Command\EnrollCommand(
    courseId: $course1->id(),
    learnerId: $learnerId,
));

// Enroll learner in course 2
$enrollmentService->enroll(new \SovereignStack\Internal\Lms\Application\Command\EnrollCommand(
    courseId: $course2->id(),
    learnerId: $learnerId,
));

echo "Recording progress...\n";
// Record some progress
$progressService = new \SovereignStack\Internal\Lms\Application\Service\ProgressApplicationService(
    new \SovereignStack\Internal\Lms\Infrastructure\Persistence\MySQLProgressRepository($container->get(\SovereignStack\Core\Database\ConnectionInterface::class)),
    $container->get(\SovereignStack\Internal\Lms\Domain\Repository\EnrollmentRepositoryInterface::class),
    $container->get(\SovereignStack\Internal\Lms\Domain\Repository\CourseRepositoryInterface::class),
    $container->get(\SovereignStack\Internal\Lms\Domain\Repository\ModuleRepositoryInterface::class),
    $container->get(\SovereignStack\Hub\Identity\Application\IdentityInterface::class),
    $container->get(\SovereignStack\Core\Database\ConnectionInterface::class),
);

// Get enrollments for learner
$enrollments = $container->get(\SovereignStack\Internal\Lms\Domain\Repository\EnrollmentRepositoryInterface::class)->findByLearner($learnerId);

// Mark first module as completed for first enrollment
if (!empty($enrollments)) {
    $enrollment = $enrollments[0];
    $progressService->recordProgress(new \SovereignStack\Internal\Lms\Application\Command\RecordProgressCommand(
        enrollmentId: $enrollment->id(),
        moduleId: \SovereignStack\Internal\Lms\Domain\ValueObject\ModuleId::fromString($module1->id()),
        newStatus: \SovereignStack\Internal\Lms\Domain\ValueObject\ModuleStatus::Completed,
    ), $learnerId);

    // Mark second module as in progress
    $progressService->recordProgress(new \SovereignStack\Internal\Lms\Application\Command\RecordProgressCommand(
        enrollmentId: $enrollment->id(),
        moduleId: \SovereignStack\Internal\Lms\Domain\ValueObject\ModuleId::fromString($module2->id()),
        newStatus: \SovereignStack\Internal\Lms\Domain\ValueObject\ModuleStatus::InProgress,
    ), $learnerId);
}

echo "Seeding complete!\n";
echo "Created:\n";
echo "  - 2 courses (1 published, 1 published)\n";
echo "  - 5 modules total\n";
echo "  - 1 learner with 2 enrollments\n";
echo "  - Progress recorded for 2 modules\n";