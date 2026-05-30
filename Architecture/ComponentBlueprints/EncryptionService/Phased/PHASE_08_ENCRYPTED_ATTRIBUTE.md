# Phase 8: #[Encrypted] Attribute & Reflection API

## Objective
Define the PHP 8.2+ attribute for marking model properties as encrypted, providing a declarative way to handle data protection.

## Prerequisites
- Phase 1

## Technical Specification

### Attribute Definition
```php
namespace DGLab\Services\Encryption\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Encrypted
{
    public function __construct(
        public bool $searchable = false,
        public string $algorithm = 'aes-256-gcm',
        public ?string $keyId = null,
        public array $context = []
    ) {}
}
```

## Implementation Steps
1. Create the `Encrypted` attribute class.
2. Develop `AttributeResolver` utility to extract metadata from model properties using Reflection.
3. Update `CMS Studio` Content model to include `#[Encrypted]` on draft titles/bodies.

## Integration Points
- **Base Model**: Will use this attribute to identify fields for encryption/decryption.

## Completion Gate
- Attribute defined and `AttributeResolver` successfully reading metadata from sample classes.
