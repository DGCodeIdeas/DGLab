# Message Ordering Guarantees

> **Navigation:** [Queue Patterns Index](index.md) | [Dead-Letter Handling](dead-letter-handling.md) | [Throughput Optimization](throughput-optimization.md)
>
> **Decision Trees:** [Queue Solution Selector](../hub-taxonomy/queue-solution-selector.md)

---

## Overview

Message ordering in distributed queue systems involves trade-offs between strict ordering, throughput, and fault tolerance. This guide covers the ordering guarantees available in the DGLab Hub queue infrastructure and how to select the right model.

**Primary Blueprint:** [HUB-10: Sovereign Queue](../../ApprovedBlueprints/Hub/HUB-10.md)

---

## Ordering Models

### FIFO Queue (Strict Ordering)

Messages are delivered in the exact order they were sent. At-most-once or exactly-once delivery enforced.

```mermaid
sequenceDiagram
    participant P1 as Producer
    participant P2 as Producer
    participant Q as FIFO Queue
    participant C1 as Consumer 1
    participant C2 as Consumer 2

    P1->>Q: SEND msg A (seq=1)
    P1->>Q: SEND msg B (seq=2)
    P2->>Q: SEND msg C (seq=3)
    P1->>Q: SEND msg D (seq=4)

    Note over Q: Messages stored in order: A, B, C, D

    Q->>C1: DELIVER A (seq=1)
    C1-->>Q: ACK A
    Q->>C1: DELIVER B (seq=2)
    C1-->>Q: ACK B
    Note over C2: Waiting... cannot consume out of order
    Q->>C1: DELIVER C (seq=3)
    Q->>C1: DELIVER D (seq=4)
```

**Characteristics:**
- **Delivery order:** Exact (insertion order)
- **Throughput:** Limited (single-consumer FIFO per partition)
- **Use case:** Financial transactions, audit trails, sequential state machines
- **HUB-10 support:** When ordered group IDs are specified

### Standard Queue (At-Least-Once)

Messages are delivered at least once. Ordering is best-effort; messages may be delivered out of order due to retries or parallel consumers.

```mermaid
sequenceDiagram
    participant P as Producer
    participant Q as Standard Queue
    participant C1 as Consumer 1
    participant C2 as Consumer 2

    P->>Q: SEND msg A
    P->>Q: SEND msg B
    P->>Q: SEND msg C
    P->>Q: SEND msg D

    Note over Q: Messages: A, B, C, D

    par Parallel delivery
        Q->>C1: DELIVER A
        Q->>C2: DELIVER B
    end

    C1-->>Q: ACK A
    Note over C2: Processing... timeout!
    Q->>C1: DELIVER C
    Note over Q: B becomes visible again (visibility timeout)
    Q->>C2: DELIVER B (retry, out of order!)
    C2-->>Q: ACK B
    Q->>C2: DELIVER D
```

**Characteristics:**
- **Delivery order:** Best-effort (retries may reorder)
- **Throughput:** High (parallel consumers)
- **Use case:** Email sending, report generation, image processing
- **HUB-10 support:** Default mode

---

## Delivery Guarantees

### At-Most-Once

| Property | Value |
|----------|-------|
| **Delivery** | Each message delivered 0 or 1 times |
| **Ordering** | Not guaranteed |
| **Ack required** | No |
| **Retry** | Never |
| **Use case** | Telemetry, metrics (loss acceptable) |

### At-Least-Once (Default for HUB-10)

| Property | Value |
|----------|-------|
| **Delivery** | Each message delivered 1+ times |
| **Ordering** | Best-effort (FIFO per partition if configured) |
| **Ack required** | Yes |
| **Retry** | Until ack or retry limit |
| **Use case** | Background jobs, async processing |

### Exactly-Once

| Property | Value |
|----------|-------|
| **Delivery** | Each message delivered exactly 1 time |
| **Ordering** | Strict (per partition) |
| **Ack required** | Yes (with deduplication) |
| **Retry** | Deduplication prevents double processing |
| **Use case** | Payment processing, critical events |

---

## Sequence ID Patterns

### Monotonic Sequence IDs

Assign a globally unique sequence number to each message. Consumers can detect gaps and reorder.

```php
<?php
namespace Sovereign\Hub\Queue\Ordering;

class MonotonicSequencer
{
    private RedisClient $redis;

    /**
     * Generate a monotonic sequence ID using Redis atomic increment.
     *
     * Format: {timestamp_ms}-{counter:06d}-{shard_id}
     * Example: 1706119234567-000042-s1
     */
    public function nextId(string $partitionKey, string $shardId = 's1'): string
    {
        $counter = $this->redis->incr("seq:{$partitionKey}");
        // Reset counter daily to keep IDs bounded
        $this->redis->expire("seq:{$partitionKey}", 86400);

        return sprintf(
            '%d-%06d-%s',
            hrtime(true) / 1_000_000, // millisecond timestamp
            $counter,
            $shardId
        );
    }
}

class OrderedMessage
{
    public function __construct(
        public readonly string $sequenceId,
        public readonly string $body,
        public readonly string $partitionKey
    ) {}
}
```

### Partition Keys

Messages within the same partition are guaranteed ordered. Partitions enable parallel consumption while maintaining per-group ordering.

```php
// All messages for user_id=42 go to the same partition
$partitionKey = "user:{$userId}";
$queue->send(new OrderedMessage(
    sequenceId: $sequencer->nextId($partitionKey),
    body: json_encode($payload),
    partitionKey: $partitionKey
));
```

### Ordering by Partition

```mermaid
graph TD
    P[Producer] --> Router[Partition Router]
    Router -->|hash(user_id) mod 3| P0[Partition 0]
    Router -->|hash(user_id) mod 3| P1[Partition 1]
    Router -->|hash(user_id) mod 3| P2[Partition 2]

    P0 --> C0[Consumer Group A]
    P1 --> C1[Consumer Group B]
    P2 --> C2[Consumer Group C]

    Note over P0: user:1, user:4, user:7 → ORDERED
    Note over P1: user:2, user:5, user:8 → ORDERED
    Note over P2: user:3, user:6, user:9 → ORDERED

    classDef partition fill:#e8f5e9,stroke:#2e7d32
    class P0,P1,P2 partition
```

### Deduplication

Even with at-least-once delivery, deduplication IDs provide exactly-once semantics for idempotent consumers:

```php
<?php
namespace Sovereign\Hub\Queue\Ordering;

class DeduplicationService
{
    private RedisClient $redis;

    /**
     * Check if a message has already been processed.
     * Uses Redis SET NX with TTL matching the deduplication window.
     */
    public function isDuplicate(string $dedupId, int $windowSeconds = 3600): bool
    {
        // SET NX — returns false if key already exists
        if (!$this->redis->set("dedup:{$dedupId}", '1', ['NX', 'EX' => $windowSeconds])) {
            return true; // Already processed
        }
        return false;
    }
}
```

---

## Global vs. Per-Partition Ordering

### Global Ordering

All messages processed in strict sequence across all consumers.

| Aspect | Detail |
|--------|--------|
| **Scalability** | Single-consumer bottleneck |
| **Throughput** | ~1,000 msg/s (Redis), ~100 msg/s (DB) |
| **Use case** | Event sourcing, append-only logs |
| **Cost** | High (serialized processing) |

### Per-Partition Ordering

Messages ordered within a partition; partitions consumed in parallel.

| Aspect | Detail |
|--------|--------|
| **Scalability** | Scales with partition count |
| **Throughput** | Partitions × per-partition throughput |
| **Use case** | User-scoped processing, tenant-isolated workflows |
| **Cost** | Moderate (partition management overhead) |

### Decision Framework

```mermaid
flowchart TD
    Start(["Do you need message ordering?"]) --> Q1{Is strict<br/>global order<br/>required?}

    Q1 -->|"Yes"| Q2{Throughput<br/>requirement?}
    Q1 -->|"No"| Q3{Order within<br/>a group sufficient?}

    Q2 -->|"Low (<1K msg/s)"| GlobalFIFO["Use FIFO Queue<br/>Global serial ordering"]
    Q2 -->|"High (>1K msg/s)"| PartitionOrder["Use Partitioned Queue<br/>Key-based ordering"]

    Q3 -->|"Yes"| PartitionByKey["Partition by entity key<br/>e.g., user_id, tenant_id"]
    Q3 -->|"No"| StandardQueue["Use Standard Queue<br/>Best-effort ordering"]

    GlobalFIFO --> Monitor[Monitor ordering lag]
    PartitionOrder --> Monitor
    PartitionByKey --> Monitor
    StandardQueue --> Monitor

    Monitor --> Impact{Reorder acceptable?}
    Impact -->|"No"| AddSequence["Add sequence IDs<br/>Detect & reject out-of-order"]
    Impact -->|"Yes"| Accept["Accept out-of-order delivery"]

    classDef result fill:#e8f5e9,stroke:#2e7d32,stroke-width:2px
    classDef decision fill:#fff3e0,stroke:#f57c00,stroke-width:2px
    classDef start fill:#f3e5f5,stroke:#7b1fa2,stroke-width:2px

    class GlobalFIFO,PartitionOrder,PartitionByKey,StandardQueue,AddSequence,Accept result
    class Q1,Q2,Q3,Impact decision
    class Start start
```

---

## Configuration: HUB-10 Queue Ordering

```php
<?php
namespace Sovereign\Hub\Queue\Config;

class OrderingConfig
{
    /**
     * Returns the queue configuration for the given ordering mode.
     */
    public static function forMode(string $mode): array
    {
        return match ($mode) {
            'fifo' => [
                'driver'          => 'redis',
                'ordered'         => true,
                'partition_count' => 1,
                'visibility_timeout' => 30, // seconds
                'max_retries'     => 3,
                'deduplication'   => false,
            ],
            'partitioned' => [
                'driver'          => 'redis',
                'ordered'         => true,
                'partition_count' => 16,
                'visibility_timeout' => 60,
                'max_retries'     => 5,
                'deduplication'   => true,
            ],
            'standard' => [
                'driver'          => 'database', // More throughput headroom
                'ordered'         => false,
                'partition_count' => 1,
                'visibility_timeout' => 120,
                'max_retries'     => 10,
                'deduplication'   => false,
            ],
        };
    }
}
```

---

## Monitoring Ordering Health

| Metric | What It Tells | Warning | Critical |
|--------|--------------|---------|----------|
| **Reordered messages** | Consumer detected sequence gap | >0.1% of messages | >1% of messages |
| **Deduplication rate** | Duplicate deliveries | >1% | >5% |
| **Partition lag** | Slowest partition depth vs. average | 2× average | 5× average |
| **Consumer rebalance** | Consumers joining/leaving group | >1/min | >5/min |

---

## Related Blueprints

| Blueprint | Role in Ordering |
|-----------|-----------------|
| [HUB-10](../../ApprovedBlueprints/Hub/HUB-10.md) | Queue driver with ordering modes |
| [HUB-09](../../ApprovedBlueprints/Hub/HUB-09.md) | Event Bus — ordering considerations for pub/sub |
| [HUB-06](../../ApprovedBlueprints/Hub/HUB-06.md) | Audit logging for ordering violations |
| [CORE-03](../../ApprovedBlueprints/Core/CORE-03.md) | Event dispatcher (PSR-14) foundations |