<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Showcase\Domain\ValueObject;
final readonly class Price
{
    private function __construct(
        private int $cents,
        private string $currency,
    ) {}
    public static function fromCents(int $cents, string $currency = 'USD'): self
    {
        if ($cents < 0) throw new \InvalidArgumentException('Price cannot be negative');
        if (strlen($currency) !== 3) throw new \InvalidArgumentException('Currency must be 3-char ISO 4217');
        return new self($cents, strtoupper($currency));
    }
    public function cents(): int { return $this->cents; }
    public function currency(): string { return $this->currency; }
    public function formatted(): string { return number_format($this->cents / 100, 2) . ' ' . $this->currency; }
}
