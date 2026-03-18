# Phase 4: Content Lifecycle & "Pro-Tool" Editor

## Goals
Implement a robust content lifecycle (Draft, Review, Publish) with a high-density, "IDE-feel" editor. This phase introduces the "Content App" for managing all content-driven data.

## 4.1 Content Lifecycle Management
- **Workflow Engine**: Enforce a strict state machine:
    1. **Draft**: Content is being edited; not visible on the frontend.
    2. **Under Review**: Content is locked; waiting for approval.
    3. **Published**: Content is live and accessible via the API.
    4. **Archived**: Content is hidden but preserved for historical reasons.
- **Workflow Transitions**: Managed by `WorkflowService` and protected by RBAC (e.g., only "Admins" can transition from "Under Review" to "Published").

## 4.2 Versioning Engine
- **Content Snapshots**: Every save action creates a new `ContentVersion`.
- **Immutability**: Once a version is finalized, it cannot be modified. Changes create a new Draft.
- **Version History Schema**:
    - `content_versions`: ID, entry_id, author_id, schema_version_id, data (JSON), status, version_number, created_at.

## 4.3 Schema Versioning
- **Structural Integrity**: When a `ContentType` is updated, the system uses the `schema_version_id` to interpret old content snapshots correctly.
- **Rollbacks**: Reverting to an old version ensures it is rendered against the correct structural schema.

## 4.4 User Interface: The "Content App"
- **"Pro-Tool" Vibe**: Think VS Code or Linear.
- **High-Density Data**: Tabbed interface, split-pane views, and keyboard-first navigation (Cmd+K).
- **Activity Sidebar**: Real-time audit log of edits for the current entry.
- **Visual Diffing**: Side-by-side comparison of two content versions.
- **Instant Preview**: Real-time rendering of the content within the actual PWA environment using an iframe/message-bus bridge.

## 4.5 Performance & Reliability
- **Concurrent Editing**: Optimistic locking or real-time presence (e.g., "User X is also editing this").
- **Auto-Save**: Background saving of drafts to prevent data loss.
- **Conflict Resolution**: Logic to handle two users saving to the same version simultaneously.
