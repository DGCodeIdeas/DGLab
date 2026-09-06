# sovereign-stack/core-http-message

**CORE-04: PSR-7 HTTP Message & PSR-17 Factory implementations.**

Immutable PSR-7 value objects (`Request`, `Response`, `ServerRequest`, `Stream`, `Uri`, `UploadedFile`) plus the six PSR-17 factory interfaces and an aggregate `MessageFactoryInterface` for convenient DI binding.

## Reference

- Blueprint: `Architecture/Core/CORE-04.md`
- PSR-7: https://www.php-fig.org/psr/psr-7/
- PSR-17: https://www.php-fig.org/psr/psr-17/

## Status

📝 In development (Milestone 0, Task 16).

## Security properties

- **Immutability** — every `with*()` method returns a new instance; enforced by PHP 8.3 `readonly` properties.
- **Header injection prevention** — `withHeader()` / `withAddedHeader()` reject names or values containing `\r` or `\n` (CWE-113 / CWE-93).
- **Resource lifecycle** — `Stream` owns its resource; `__destruct()` calls `close()` unconditionally; `detach()` is the only way to release ownership.
- **Status range validation** — `Response` rejects codes outside 100–599.

## License

MIT.
