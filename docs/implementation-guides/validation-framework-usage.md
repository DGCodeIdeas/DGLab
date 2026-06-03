# Implementation Guide: Validation Framework Usage

## Overview
This guide demonstrates how to use the Sovereign Stack's validation framework. The framework provides a three-layer validation pipeline (input sanitization, structural validation, business validation) with PHP 8.3 Attribute syntax for declarative rule definitions.

**Reference**: [ADR-003 Validation Framework Strategy](/docs/architecture/decisions/ADR-003-validation-framework-strategy.md)

## Prerequisites
- DI Container configured ([DI Setup Guide](./di-container-setup.md))
- Validator service registered

## Step 1: Define a DTO with Validation Attributes

Create `app/DTOs/UserRegistrationDTO.php`:

```php
<?php
namespace App\DTOs;

use Sovereign\Core\Validation\Rule;

class UserRegistrationDTO
{
    #[Rule('required')]
    #[Rule('string')]
    #[Rule('min', 2)]
    #[Rule('max', 255)]
    public string $name;

    #[Rule('required')]
    #[Rule('email')]
    #[Rule('unique', 'users.email')]
    public string $email;

    #[Rule('required')]
    #[Rule('min', 8)]
    #[Rule('regex', '/^(?=.*[A-Z])(?=.*[0-9])/')]
    public string $password;

    #[Rule('required')]
    #[Rule('confirmed')]
    public string $password_confirmation;

    #[Rule('numeric')]
    #[Rule('min', 18)]
    public ?int $age = null;

    #[Rule('in', ['newsletter', 'security', 'promotions'])]
    public array $preferences = [];
}
```

## Step 2: Create a Custom Validator

For business rules that span multiple fields, create a custom validator in `app/Validation/Rules/StrongPasswordRule.php`:

```php
<?php
namespace App\Validation\Rules;

use Sovereign\Core\Validation\Contracts\RuleInterface;

class StrongPasswordRule implements RuleInterface
{
    public function validate(string $field, mixed $value, mixed $options = null): ?string
    {
        // Custom check: password must not contain the username
        // This requires access to the full data set via context
        return null; // Return error message string or null for pass
    }

    public function message(): string
    {
        return 'The :field must not contain your username.';
    }
}
```

Register the custom rule in a Service Provider:

```php
<?php
// app/Providers/ValidationServiceProvider.php

public function register(): void
{
    $this->app->make(ValidatorInterface::class)->addRule(
        'strong_password',
        new StrongPasswordRule()
    );
}
```

## Step 3: Validate in a Controller

Validate an HTTP request in your controller:

```php
<?php
namespace App\Http\Controllers;

use Sovereign\Core\Validation\ValidatorInterface;
use App\DTOs\UserRegistrationDTO;

class UserController
{
    public function __construct(
        private ValidatorInterface $validator
    ) {}

    public function store(ServerRequestInterface $request): ResponseInterface
    {
        // Extract data from PSR-7 request
        $data = $request->getParsedBody();

        // Validate against DTO rules
        $result = $this->validator->validate($data, UserRegistrationDTO::class);

        if ($result->fails()) {
            return new JsonResponse([
                'errors' => $result->errors()
            ], 422);
        }

        // Use sanitized valid data
        $validData = $result->valid();

        // Proceed with registration using $validData
        // ...
    }
}
```

**Note**: The validator extracts attribute-based `#[Rule]` definitions from the DTO class automatically.

## Step 4: Validate CLI Command Arguments

Validation works identically for CLI commands:

```php
<?php
namespace App\Console\Commands;

use Sovereign\Core\Console\Command;

class RegisterUserCommand extends Command
{
    protected string $signature = 'user:register {name} {email} {password}';

    public function handle(): int
    {
        $data = [
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => $this->argument('password'),
        ];

        // Reuse the same DTO validation
        $result = $this->validator->validate($data, UserRegistrationDTO::class);

        if ($result->fails()) {
            foreach ($result->errors() as $field => $messages) {
                $this->error("{$field}: " . implode(', ', $messages));
            }
            return 1;
        }

        $this->info('Validation passed!');
        return 0;
    }
}
```

## Step 5: Validate Programmatically (Service Layer)

Inside a service, validate without any HTTP/CLI dependency:

```php
<?php
namespace App\Services;

class UserService
{
    public function register(array $userData): User
    {
        $result = $this->validator->validate($userData, UserRegistrationDTO::class);

        if ($result->fails()) {
            throw new ValidationException($result->errors());
        }

        // All data is validated and sanitized
        return $this->createUser($result->valid());
    }
}
```

## Step 6: Test Validation Rules

```bash
php s-forge test --filter=Validation
```

Example test:

```php
<?php
test('user registration requires valid email', function () {
    $validator = container()->make(ValidatorInterface::class);

    $result = $validator->validate(
        ['email' => 'not-an-email', 'name' => 'Test', 'password' => 'Abcd1234!'],
        UserRegistrationDTO::class
    );

    expect($result->fails())->toBeTrue();
    expect($result->errors())->toHaveKey('email');
});
```

## Common Patterns

### Conditional Validation
```php
#[Rule('required_if', 'role:admin')]  // Required only when role=admin
public string $department;
```

### Array Validation
```php
#[Rule('array')]
#[Rule('array.min', 1)]
#[Rule('array.max', 5)]
#[Rule('array.each', 'numeric')]
public array $tags = [];
```

### Nested Validation
```php
#[Rule('array')]
#[Rule('array.*.required')]
#[Rule('array.*.string')]
public array $addresses;
```

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| `Attribute not found` | Missing `use Sovereign\Core\Validation\Rule` import | Add the import statement |
| `Validation passed but data wrong` | Rule name typo | Check rule names against framework documentation |
| `Custom rule not recognized` | Rule not registered | Add `addRule()` call in ServiceProvider |
| `Error collection empty` | Exception thrown instead | Check if validator throws on first error vs. collecting |

## Verification Checklist
- [ ] DTO class uses `#[Rule]` attributes on properties
- [ ] HTTP validation returns 422 with all errors
- [ ] CLI validation prints field-level errors
- [ ] Service layer throws `ValidationException` on failure
- [ ] Custom rules registered and working
- [ ] Test coverage for validation scenarios

## Next Steps
Proceed to [Routing Configuration](./routing-configuration.md) to wire up controllers.