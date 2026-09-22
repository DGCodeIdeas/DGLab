# Nexus: Horizontal Scaling & Pub/Sub Blueprint

Nexus is designed to be stateless at the application layer, allowing it to scale across multiple nodes or containers. Coordination is handled via Redis Pub/Sub.

## 1. The Redis Message Bus
Every Nexus instance maintains a persistent connection to Redis in a non-blocking coroutine.

### 1.1 Subscription Logic
Instances subscribe to the following internal Redis channels:
- `nexus_internal_broadcast`: Used for system-wide notifications.
- `nexus_node_specific_{node_id}`: For messages targeting an instance directly.

## 2. Global Event Propagation
When the main PHP application (HTTP or Worker) needs to send a real-time message:

1. **Dispatch**: `Dispatcher::dispatch(new JobProgressEvent($jobId))`.
2. **Broker**: The `BroadcastDriver` serializes the event into a Nexus packet.
3. **Publish**: The packet is published to the Redis channel `nexus_broadcast`.
4. **Fan-Out**:
   - ALL Nexus instances receive the message from Redis.
   - Each instance checks its local `ConnectionManager` for subscribers to the relevant topic.
   - Local matches are pushed via Swoole `$server->push()`.

## 3. Scalable Connection Tracking
While connections are held in-memory locally, a global presence can be maintained in Redis if needed:
- Key: `nexus:presence:user:{id}` -> Set of `node_id`s.
- This allows the system to know *which* nodes a user is connected to, reducing the fan-out noise if highly optimized delivery is required.

## 4. Resilience
- **Reconnection**: If the Redis connection drops, the Nexus listener coroutine will automatically attempt to reconnect with exponential backoff.
- **Heartbeats**: Nexus handles WebSocket Pings at the transport level to ensure stale connections on specific nodes are cleaned up, preventing "Ghost Subscriptions".
