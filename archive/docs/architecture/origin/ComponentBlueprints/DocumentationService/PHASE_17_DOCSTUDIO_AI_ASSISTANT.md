# Phase 17: DocStudio AI Assistant

## Goals
- Implement a chat interface for asking questions about the documentation.
- Leverage the RAG pipeline from Phase 16 to provide grounded answers.
- Support "Cite Source" links in AI responses.

## AI Assistant
The `<s:doc_ai_chat>` component provides a persistent chat bubble or sidebar. It sends queries to the `AIOrchestrationService`, which retrieves relevant doc chunks and generates a response based on the DGLab architecture.

## Deliverables
1.  Reactive AI chat component.
2.  `DocumentationAIController` for RAG-based query handling.
3.  Source-citation logic to link AI answers back to specific doc lines.
