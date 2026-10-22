<?php
declare(strict_types=1);

namespace SovereignStack\Core\Cache;

use Psr\Cache\CacheItemInterface;

/**
 * PSR-6 cache item.
 *
 * A CacheItem is constructed in one of two ways:
 *  - By {@see CachePool::getItem()} on a read — the item carries the
 *    fetched value (if any) and an isHit flag.
 *  - By application code calling $item->set($value) on a miss,
 *    then passing the item to {@see CachePool::save()} or
 *    {@see CachePool::saveDeferred()}.
 *
 * The TTL is set via {@see self::expiresAfter()} (relative seconds) or
 * {@see self::expiresAt()} (absolute DateTime). Internally we store the
 * TTL in seconds, computed at the time of save().
 *
 * @internal The {@see self::getTtl()} and {@see self::asHit()} methods
 *           are NOT part of the PSR-6 surface. They exist for the
 *           pool's internal use only and must not be relied on by
 *           application code.
 */
final class CacheItem implements CacheItemInterface
{
    /** @var int|null TTL in seconds; null = never expires. */
    private ?int $ttl = null;

    public function __construct(
        private readonly string $key,
        private mixed $value,
        private bool $isHit,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->isHit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        if ($expiration === null) {
            $this->ttl = null;
            return $this;
        }
        $this->ttl = max(0, (int) $expiration->getTimestamp() - time());
        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        if ($time === null) {
            $this->ttl = null;
        } elseif ($time instanceof \DateInterval) {
            $now = new \DateTimeImmutable();
            $this->ttl = max(0, (int) $now->add($time)->getTimestamp() - $now->getTimestamp());
        } else {
            $this->ttl = max(0, $time);
        }
        return $this;
    }

    /**
     * Return the TTL in seconds, or null if the item never expires.
     *
     * Used internally by {@see CachePool::save()} to pass to the
     * adapter. Not part of PSR-6; callers should not call this directly.
     */
    public function getTtl(): ?int
    {
        return $this->ttl;
    }

    /**
     * Return a copy of this item marked as a hit.
     *
     * Used internally by {@see CachePool::save()} to update the
     * inflight cache after a successful write.
     */
    public function asHit(): self
    {
        return new self($this->key, $this->value, true);
    }
}
