# Studio Expansion: MangaScript & MangaImage AI Studio

## 1. MangaScript AI Workflow
MangaScript is a premier Studio app for transforming novels into manga scripts.

### 1.1 Phase 1: Upload & Identify (Hybrid Mode)
- **Automatic Analysis**: AI scans the EPUB TOC and spinal structure.
- **Classification**: Identifying Chapters, Prologues, Interludes, and Epilogues.
- **User Override**: Display a table with identified sections for manual rename/merge/split/re-type.

### 1.2 Phase 2: Chunky Transformation
- **Threshold**: Trigger chunking if the section's tokens > (model context limit * 0.8).
- **Strategy**: Break long chapters into overlapping segments (200-character overlap) for narrative continuity.
- **Execution**: Each chunk is proofread/transformed separately, then merged into a single script.

### 1.3 Phase 3: Collaborative Approval Queue
- **Roles**:
    - **Writer**: Can upload, trigger AI proofreading, and request regeneration.
    - **Editor**: Can perform human proofreading, approve/reject segments, and send back for changes.
- **Interface**: Side-by-side view (Original vs. AI-Proofread). User can edit AI-proofread text before approval.
- **Statuses**: `Awaiting AI`, `Awaiting Human Proofread`, `Changes Requested`, `Approved`, `Packing`.

### 1.4 Phase 4: Result Packing
- **Output**: After all sections are approved, pack into a new EPUB (`_proofread.epub`).
- **Transfer**: One-click "Send to MangaImage" to begin the visual generation process.

## 2. MangaImage Service (Twin Service)
MangaImage uses the same workflow engine but with a different generator implementation.

### 2.1 Twin Service Architecture
- **Generator Interface**:
    - `TextGeneratorInterface` (MangaScript: LLMs).
    - `ImageGeneratorInterface` (MangaImage: Stable Diffusion, DALL-E, ComfyUI).
- **Scene Detection**: AI parses the MangaScript output to identify panels and visual descriptions (e.g., `[Panel 1: A dark forest...]`).

### 2.2 Image Generation Workflow
- **Prompting**: Generate per-panel prompts based on visual descriptions.
- **Progress Tracking**:
    - **Overall Progress**: Percentage of panels generated.
    - **Panel Progress**: Visual progress bar for the currently generating image.
- **Regeneration**: User can approve each image individually or request a "re-roll" with different prompts/models.

## 3. Storage & Result Delivery
- **Persistence**: Store generated images and scripts in the configured storage (Local/Drive/S3).
- **Export**: Options to export as CBZ (Comic Book Zip), PDF, or a modified EPUB with embedded images.

## 4. Verification
- [ ] Verify that chunked segments correctly overlap to maintain narrative flow.
- [ ] Confirm that a "Writer" role cannot move a script to the "Approved" state without "Editor" intervention (if multi-user mode is active).
- [ ] Test the "Send to MangaImage" transfer, ensuring all script panels are correctly parsed for image generation.
