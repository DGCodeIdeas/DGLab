# Phase 16: AI Search Pipeline (RAG)

## Goals
- Implement a document chunking strategy for LLM consumption.
- Integrate with an embedding provider (via MangaScript infra).
- Establish a vector search pipeline alongside full-text search.

## RAG Pipeline
1.  **Chunking**: Split long Markdown files into semantic chunks (e.g., by headers).
2.  **Embedding**: Generate vector embeddings for each chunk.
3.  **Storage**: Store vectors in a vector-capable database (or a lightweight local alternative).
4.  **Retrieval**: Perform semantic search to find relevant context for user queries.

## Deliverables
1.  `DocumentChunker` utility.
2.  Vector storage driver integration.
3.  Hybrid search logic (Full-text + Semantic).
