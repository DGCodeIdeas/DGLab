# Studio Expansion: EpubFontChanger 2.0 (Batch & Google Fonts)

## 1. Google Fonts Integration (GoogleFontsService)
The `GoogleFontsService` provides a unified interface for discovery and injection of Google Fonts into EPUB documents.

### 1.1 Discovery & Download
- **Search API**: `search(string $query): array`. Returns a list of matching fonts from the Google Fonts API.
- **Font Metadata Cache**: Locally cached font lists (TTL 1 week) in `storage/fonts/google_index.json`.
- **Dynamic Fetching**: `download(string $fontFamily, array $variants = ['regular', 'bold']): array`. Fetches the `woff2` files from the Google CDN and stores them in `storage/fonts/google/{family}/`.

### 1.2 Injection Engine
- **Font Mapping UI**: A workspace to define a "Replacement Map" (e.g., "Times New Roman" -> "Roboto").
- **CSS Injection**: The `FontInjector` class is enhanced to:
    - Automatically add `@font-face` rules for the target Google Font.
    - Replace all occurrences of the source font-family name in CSS files and inline styles.
    - Update the EPUB manifest (`content.opf`) to include the new font files.

## 2. Batch Processing Engine (BatchReplaceService)
The `BatchReplaceService` enables the processing of multiple EPUB files in the background.

### 2.1 Job Management
- **Table**: `batch_jobs` (id, user_id, type, mapping_json, epub_ids_json, status, progress, total_items).
- **Execution Workflow**:
    1. User selects multiple EPUBs from the MediaLibrary.
    2. Defines the Font Mapping (or Text Replace Mapping).
    3. Service creates a `batch_job` record and dispatches a background job for each selected EPUB.
- **Sequential Feedback**: The Batch Processor updates the global Live Console after each EPUB is completed: `[Batch] Processed 3/15 epubs...`.

### 2.2 Secondary Mode: Text Replace
- **Toggle Mode**: User can switch the batch job type from "Font Family Replace" to "Arbitrary Text Search & Replace".
- **Regex Support**: Optional support for regular expression replacements across the entire EPUB content.

## 3. Storage & Result Delivery
- **Persistence**: Processed EPUBs are saved as new files with the suffix `_modified` (or a user-specified suffix).
- **Download Link**: A "Download All (ZIP)" option is provided in the Batch Queue once all jobs are complete.
- **Audit Logging**: Each replacement action is logged in the `AuditService` with details on the source/target strings and affected EPUB.

## 4. Verification
- [ ] Verify that selecting "Roboto" from the Google Fonts UI correctly downloads and caches the font files on the server.
- [ ] Confirm that the "Batch Replace" process correctly modifies the `font-family` in both `main.css` and inline `<style>` tags within the EPUB's HTML files.
- [ ] Test the text search-and-replace feature on a set of 5 EPUBs simultaneously.
