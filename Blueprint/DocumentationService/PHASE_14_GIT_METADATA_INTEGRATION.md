# Phase 14: Git Metadata Integration

## Goals
- Display "Last Updated" timestamps and author information for each page.
- Link directly to the source file on GitHub/GitLab.
- Show an "Edit this page" button.

## Implementation
The service will use `git log -1` to fetch the latest commit data for each file during the metadata extraction phase. This data is then passed to the `DocPage` DTO.

## Deliverables
1.  Git metadata extraction utility.
2.  UI components for "Page Info" (authors, date).
3.  Configurable repository URL patterns for "Edit" links.
