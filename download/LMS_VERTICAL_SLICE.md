# DGLab / NeoSPX — LMS Vertical Slice

## What this is

The smallest demonstrable LMS workflow: **create a course and list courses via HTTP**.

This vertical slice reuses the existing LMS spoke components (Course entity, MySQLCourseRepository) and wires them into the application's HTTP entrypoint. No new abstractions are introduced.

## Prerequisites

- PHP 8.4+
- Composer 2+
- MySQL 8.0+ (with InnoDB)

## Setup

### 1. Install dependencies

```bash
composer install
```

### 2. Create the database + install the LMS schema

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS dglab"
mysql -u root -p dglab < packages/spoke/internal/lms/migrations/001_create_courses_table.sql
mysql -u root -p dglab < packages/spoke/internal/lms/migrations/002_create_modules_table.sql
mysql -u root -p dglab < packages/spoke/internal/lms/migrations/005_add_module_content.sql
```

(Only the courses table is needed for this slice; modules are for the follow-up.)

### 3. Configure the database connection

Copy `.env.example` to `.env` and set:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=dglab
DB_USER=root
DB_PASS=yourpassword
```

Or export them in your shell:

```bash
export DB_HOST=127.0.0.1 DB_NAME=dglab DB_USER=root DB_PASS=yourpassword
```

### 4. Run the application

```bash
php -S 127.0.0.1:8080 -t public/
```

The app is now serving at `http://127.0.0.1:8080`.

## Demonstration

### Create a course

```bash
curl -X POST http://127.0.0.1:8080/courses \
  -H "Content-Type: application/json" \
  -d '{"title":"Introduction to NeoSPX","slug":"intro-neospx","description":"A first course"}'
```

Expected response (201 Created):

```json
{
  "id": "a1b2c3d4e5f6g7h8i9j0k1l2m3",
  "title": "Introduction to NeoSPX",
  "slug": "intro-neospx",
  "description": "A first course",
  "status": "draft",
  "createdAt": "2026-10-29T12:00:00+00:00"
}
```

### List all courses

```bash
curl http://127.0.0.1:8080/courses
```

Expected response (200 OK):

```json
[
  {
    "id": "a1b2c3d4e5f6g7h8i9j0k1l2m3",
    "title": "Introduction to NeoSPX",
    "slug": "intro-neospx",
    "description": "A first course",
    "status": "draft",
    "createdAt": "2026-10-29T12:00:00+00:00"
  }
]
```

### Verify the existing skeleton still works

```bash
curl http://127.0.0.1:8080/        # → "Hello World"
curl http://127.0.0.1:8080/health  # → JSON health status
```

## Tests

### Unit tests (no MySQL required)

```bash
cd packages/spoke/internal/lms
../../../../vendor/bin/phpunit --testsuite Unit --testdox
```

### Integration tests (MySQL required)

```bash
export TEST_DB_HOST=127.0.0.1 TEST_DB_NAME=dglab_test TEST_DB_USER=root TEST_DB_PASS=yourpassword
cd packages/spoke/internal/lms
../../../../vendor/bin/phpunit --testsuite Unit --group mysql-required --testdox
```

## What works

- ✅ Course creation via HTTP POST /courses
- ✅ Course listing via HTTP GET /courses
- ✅ MySQL persistence (InnoDB)
- ✅ JSON API responses
- ✅ Input validation (title, slug format)
- ✅ Existing skeleton (/, /health) unchanged

## What's incomplete

- No enrollment workflow (EnrollmentRepository interface exists, no MySQL implementation)
- No progress tracking (Progress entity exists, no repository)
- No course publishing via HTTP (use the CourseApplicationService for that — requires IdentityInterface wiring)
- No authentication (the create endpoint is open; a follow-up will wire Hub Identity)
- No module management via HTTP (use the service for now)

## Next milestone

1. Wire CourseApplicationService (with a demo IdentityService) for course publishing
2. Implement EnrollmentRepository (MySQL) + enrollment use case
3. Add GET /courses/{slug} (single course detail)
4. Wire Hub Identity for authenticated course creation
